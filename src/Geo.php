<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use sup\craftgeo\fields\GeoField;
use sup\craftgeo\models\Settings;
use yii\base\Event;
use sup\craftgeo\integrations\feedme\fields\GeoFeedMeField as FieldsGeoFeedMeField;
use craft\events\RegisterGqlTypesEvent;
use craft\services\Gql;
use craft\web\twig\variables\CraftVariable;
use sup\craftgeo\integrations\graphql\types\GeoCoordsInput;
use sup\craftgeo\integrations\graphql\types\GeoQueryArgument;
use sup\craftgeo\integrations\graphql\types\GeoType;
use sup\craftgeo\services\GeoService;
use sup\craftgeo\services\MapService;
use sup\craftgeo\services\ZoneService;
use sup\craftgeo\variables\GeoVariable;

/**
 * @property-read GeoService $geo
 * @property-read MapService $maps
 * @property-read ZoneService $zones
 */

class Geo extends BasePlugin
{
    public string $schemaVersion = '1.0.1';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'geo' => GeoService::class,
                'maps' => MapService::class,
                'zones' => ZoneService::class,
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
        return new \sup\craftgeo\migrations\Install();
    }

    public function createUninstallMigration(): ?\craft\db\Migration
    {
        return new \sup\craftgeo\migrations\Uninstall();
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    /**
     * @return Settings
     */
    public function getSettings(): ?Model
    {
        return parent::getSettings();
    }


    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('geo/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'config'=> Craft::$app->config->getConfigFromFile('geo'),
        ]);
    }


    public function onRegisterFeedMeFields (\craft\feedme\events\RegisterFeedMeFieldsEvent $event)
    {
      $event->fields[] = FieldsGeoFeedMeField::class;
    }

    private function registerGqlTypes(): void
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            function (RegisterGqlTypesEvent $event) {
                $event->types[] = GeoType::class;
                $event->types[] = GeoQueryArgument::class;
                $event->types[] = GeoCoordsInput::class;
            }
        );
    }

    public function onRegisterVariable (Event $event)
	{
		/** @var CraftVariable $variable */
		$variable = $event->sender;
        $variable->set('geomaps', GeoVariable::class);
	}


    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = GeoField::class;
        });



        Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			[$this, 'onRegisterVariable']
		);


        if (class_exists(\craft\feedme\Plugin::class))
		{
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
