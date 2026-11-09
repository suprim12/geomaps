<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */


namespace suprim\geomaps;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\db\Query;
use craft\events\FieldEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Dashboard;
use craft\services\Fields;
use craft\services\Gql;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\integrations\feedme\fields\GeoFeedMeField as FieldsGeoFeedMeField;
use suprim\geomaps\integrations\graphql\types\GeoCoordsInput;
use suprim\geomaps\integrations\graphql\types\GeoQueryArgument;
use suprim\geomaps\integrations\graphql\types\GeoType;
use suprim\geomaps\jobs\RebuildLookup;
use suprim\geomaps\models\Settings;
use suprim\geomaps\records\GeoRecord;
use suprim\geomaps\services\GeoService;
use suprim\geomaps\services\ImportService;
use suprim\geomaps\services\LocationsMap;
use suprim\geomaps\services\MapService;
use suprim\geomaps\services\MissingCoordinates;
use suprim\geomaps\services\ZoneService;
use suprim\geomaps\utilities\ImportUtility;
use suprim\geomaps\variables\GeoVariable;
use suprim\geomaps\widgets\LocationsMapWidget;
use yii\base\Event;

/**
 * @property-read GeoService $geo
 * @property-read MapService $maps
 * @property-read ImportService $import
 * @property-read ZoneService $zones
 * @property-read LocationsMap $locationsMap
 * @property-read MissingCoordinates $missingCoordinates
 * @method Settings getSettings()
 */

class Plugin extends BasePlugin
{
    /** The free edition. Its handle matches the edition already in the Plugin Store. */
    public const EDITION_STANDARD = 'standard';
    public const EDITION_PRO = 'pro';

    public string $schemaVersion = '1.0.2';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function editions(): array
    {
        return [
            self::EDITION_STANDARD,
            self::EDITION_PRO,
        ];
    }

    /**
     * Whether Geo Maps Pro is active. Pro features can be tried free on local and staging sites.
     */
    public function isPro(): bool
    {
        return $this->is(self::EDITION_PRO);
    }

    /**
     * The Map page in the control panel nav, with Pro only.
     */
    public function getCpNavItem(): ?array
    {
        if (!$this->isPro()) {
            return null;
        }

        $item = parent::getCpNavItem();
        $item['label'] = Craft::t('geomaps', 'Map');
        $item['icon'] = 'map-location-dot';

        return $item;
    }

    public static function config(): array
    {
        return [
            'components' => [
                'geo' => GeoService::class,
                'maps' => MapService::class,
                'import' => ImportService::class,
                'zones' => ZoneService::class,
                'missingCoordinates' => MissingCoordinates::class,
                'locationsMap' => LocationsMap::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();
    }

    public function createInstallMigration(): ?\craft\db\Migration
    {
        return new \suprim\geomaps\migrations\Install();
    }

    public function createUninstallMigration(): ?\craft\db\Migration
    {
        return new \suprim\geomaps\migrations\Uninstall();
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }


    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('geomaps/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'config' => Craft::$app->config->getConfigFromFile('geomaps'),
        ]);
    }


    public function onRegisterFeedMeFields(\craft\feedme\events\RegisterFeedMeFieldsEvent $event)
    {
        $event->fields[] = FieldsGeoFeedMeField::class;
    }

    private function registerGqlTypes(): void
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            function(RegisterGqlTypesEvent $event) {
                $event->types[] = GeoType::class;
                $event->types[] = GeoQueryArgument::class;
                $event->types[] = GeoCoordsInput::class;
            }
        );
    }

    public function onRegisterVariable(Event $event)
    {
        /** @var CraftVariable $variable */
        $variable = $event->sender;
        $variable->set('geomaps', GeoVariable::class);
    }


    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = GeoField::class;
        });

        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = ImportUtility::class;
        });

        // Pro: the Map page and Dashboard widget.
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['geomaps'] = 'geomaps/map/index';
        });
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = LocationsMapWidget::class;
        });

        // When an existing field becomes a Geo Maps field (e.g. a Maps import arriving via project config),
        // index its saved values so location search works straight away.
        $queued = [];
        Event::on(Fields::class, Fields::EVENT_AFTER_SAVE_FIELD, function(FieldEvent $event) use (&$queued) {
            if (
                !$event->isNew &&
                !isset($queued[$event->field->id]) &&
                $event->field instanceof GeoField &&
                !(new Query())->from(GeoRecord::TABLE_NAME)->where(['fieldId' => $event->field->id])->exists()
            ) {
                $queued[$event->field->id] = true;
                Craft::$app->getQueue()->push(new RebuildLookup(['fieldId' => $event->field->id]));
            }
        });



        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            [$this, 'onRegisterVariable']
        );


        if (class_exists(\craft\feedme\Plugin::class)) {
            Event::on(
                \craft\feedme\services\Fields::class,
                \craft\feedme\services\Fields::EVENT_REGISTER_FEED_ME_FIELDS,
                [$this, 'onRegisterFeedMeFields']
            );
        }
        // add gql intergations
        $this->registerGqlTypes();
    }
}
