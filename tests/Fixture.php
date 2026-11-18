<?php

namespace suprim\geomaps\tests;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Addresses;
use craft\helpers\Db;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use suprim\geomaps\events\GeocodeEvent;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\services\GeoService;
use yii\base\Event;
use yii\queue\PushEvent;
use yii\queue\Queue;

/**
 * The field, entry type and section the tests save entries into. They’re made when the tests start and removed
 * when they finish, along with everything saved in them; nothing else on the site is touched.
 */
final class Fixture
{
    public const FIELD = 'geoTestLocation';
    public const ADDRESSES_FIELD = 'geoTestAddresses';
    public const SECTION = 'geoTestPlaces';
    public const ENTRY_TYPE = 'geoTestPlace';

    /** Addresses the tests can search for, answered without calling a geocoding service. */
    public const ADDRESSES = [
        'adelaide' => ['lat' => -34.9285, 'lng' => 138.6007],
        'glenelg' => ['lat' => -34.9802, 'lng' => 138.5113],
        'norwood' => ['lat' => -34.9213, 'lng' => 138.6305],
        'melbourne' => ['lat' => -37.8136, 'lng' => 144.9631],
        'sydney' => ['lat' => -33.8688, 'lng' => 151.2093],
    ];

    /** @var array<int, mixed> Jobs the tests would have queued. */
    public static array $pushed = [];

    /** @var string[] Addresses the tests looked up, so a test can check whether a lookup happened. */
    public static array $lookups = [];

    private static ?int $fieldId = null;
    private static ?int $sectionId = null;
    private static ?int $entryTypeId = null;

    public static function setUp(): void
    {
        // Saving the fixture mustn't rewrite the project's config files.
        Craft::$app->getProjectConfig()->writeYamlAutomatically = false;

        // Nothing the tests do may leave jobs in the site's queue, such as pruning revisions or finding missing
        // coordinates. Tests that need a job see it with $pushed.
        Craft::$app->getQueue()->on(Queue::EVENT_BEFORE_PUSH, function(PushEvent $event) {
            self::$pushed[] = $event->job;
            $event->handled = true;
        });

        // Never call a real geocoding service: answer from ADDRESSES, and say nothing was found otherwise.
        foreach ([GeoService::EVENT_BEFORE_GEOCODE] as $name) {
            Event::on(GeoService::class, $name, function(GeocodeEvent $event) {
                self::$lookups[] = $event->address;
                $event->result = self::ADDRESSES[strtolower(trim($event->address))] ?? null;
                $event->handled = true;
            });
        }

        self::removeLeftovers();

        $field = new GeoField(['name' => 'Geo test location', 'handle' => self::FIELD]);
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \RuntimeException('Couldn’t save the test field: ' . implode(' ', $field->getFirstErrors()));
        }
        self::$fieldId = $field->id;

        $addresses = new Addresses(['name' => 'Geo test addresses', 'handle' => self::ADDRESSES_FIELD]);
        if (!Craft::$app->getFields()->saveField($addresses)) {
            throw new \RuntimeException('Couldn’t save the test addresses field: ' . implode(' ', $addresses->getFirstErrors()));
        }

        $layout = new FieldLayout(['type' => Entry::class]);
        $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
        $tab->setElements([new EntryTitleField(), new CustomField($field), new CustomField($addresses)]);
        $layout->setTabs([$tab]);

        $entryType = new EntryType(['name' => 'Geo test place', 'handle' => self::ENTRY_TYPE]);
        $entryType->setFieldLayout($layout);
        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \RuntimeException('Couldn’t save the test entry type: ' . implode(' ', $entryType->getFirstErrors()));
        }
        self::$entryTypeId = $entryType->id;

        $section = new Section([
            'name' => 'Geo test places',
            'handle' => self::SECTION,
            'type' => Section::TYPE_CHANNEL,
            'entryTypes' => [$entryType],
        ]);
        $section->setSiteSettings(array_map(fn($site) => new Section_SiteSettings([
            'siteId' => $site->id,
            'hasUrls' => false,
            'enabledByDefault' => true,
        ]), Craft::$app->getSites()->getAllSites()));
        if (!Craft::$app->getEntries()->saveSection($section)) {
            throw new \RuntimeException('Couldn’t save the test section: ' . implode(' ', $section->getFirstErrors()));
        }
        self::$sectionId = $section->id;
    }

    public static function tearDown(): void
    {
        self::removeLeftovers();
        // A queued job is remembered so saves don't queue another; none was queued.
        Craft::$app->getCache()->delete('geomaps:geocode-queued');
    }

    public static function field(): GeoField
    {
        $field = Craft::$app->getFields()->getFieldById((int)self::$fieldId);
        assert($field instanceof GeoField);

        return $field;
    }

    public static function addressesField(): Addresses
    {
        $field = Craft::$app->getFields()->getFieldByHandle(self::ADDRESSES_FIELD);
        assert($field instanceof Addresses);

        return $field;
    }

    public static function section(): Section
    {
        return Craft::$app->getEntries()->getSectionById((int)self::$sectionId);
    }

    public static function entryType(): EntryType
    {
        return Craft::$app->getEntries()->getEntryTypeById((int)self::$entryTypeId);
    }

    /**
     * Removes the fixture, and anything left by an earlier run that stopped part way.
     */
    private static function removeLeftovers(): void
    {
        $entries = Craft::$app->getEntries();
        $section = $entries->getSectionByHandle(self::SECTION);
        if ($section) {
            foreach (Entry::find()->sectionId($section->id)->status(null)->site('*')->unique()->all() as $entry) {
                Craft::$app->getElements()->deleteElement($entry, true);
            }
            $entries->deleteSection($section);
        }
        $entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE);
        if ($entryType) {
            $entries->deleteEntryType($entryType);
        }
        foreach ([self::FIELD, self::ADDRESSES_FIELD] as $handle) {
            $field = Craft::$app->getFields()->getFieldByHandle($handle);
            if ($field) {
                Craft::$app->getFields()->deleteField($field);
            }
        }

        // Craft keeps deleted ones for a while; remove the tests’ own for good, by their handles.
        $layoutIds = (new Query())->select('fieldLayoutId')->from(Table::ENTRYTYPES)->where(['handle' => self::ENTRY_TYPE])->column();
        Db::delete(Table::ENTRYTYPES, ['and', ['handle' => self::ENTRY_TYPE], ['not', ['dateDeleted' => null]]]);
        Db::delete(Table::SECTIONS, ['and', ['handle' => self::SECTION], ['not', ['dateDeleted' => null]]]);
        Db::delete(Table::FIELDS, ['and', ['handle' => [self::FIELD, self::ADDRESSES_FIELD]], ['not', ['dateDeleted' => null]]]);
        if ($layoutIds) {
            Db::delete(Table::FIELDLAYOUTS, ['and', ['id' => array_filter($layoutIds)], ['not', ['dateDeleted' => null]]]);
        }
    }
}
