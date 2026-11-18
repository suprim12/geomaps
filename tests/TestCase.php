<?php

namespace suprim\geomaps\tests;

use Craft;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use PHPUnit\Framework\TestCase as BaseTestCase;
use suprim\geomaps\models\Settings;
use suprim\geomaps\Plugin;

/**
 * Saves entries into the fixture section and deletes them afterwards, and puts the edition and settings back.
 */
abstract class TestCase extends BaseTestCase
{
    /** @var Entry[] */
    private array $saved = [];
    private string $edition;
    private Settings $original;

    protected function setUp(): void
    {
        parent::setUp();
        $this->edition = $this->plugin()->edition;
        $this->original = clone $this->settings();
        Fixture::$lookups = [];
        Fixture::$pushed = [];
        Craft::$app->getCache()->delete('geomaps:geocode-queued');
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $entry) {
            Craft::$app->getElements()->deleteElement($entry, true);
        }
        $this->saved = [];
        $this->plugin()->edition = $this->edition;
        $settings = $this->settings();
        foreach (get_object_vars($this->original) as $name => $value) {
            $settings->$name = $value;
        }
        parent::tearDown();
    }

    protected function plugin(): Plugin
    {
        return Plugin::getInstance();
    }

    protected function settings(): Settings
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();

        return $settings;
    }

    protected function pro(): void
    {
        $this->plugin()->edition = Plugin::EDITION_PRO;
    }

    protected function lite(): void
    {
        $this->plugin()->edition = Plugin::EDITION_LITE;
    }

    /**
     * Saves an entry in the fixture section with a location: `[lat, lng]`, an address name from
     * Fixture::ADDRESSES, or the field’s raw value as an array.
     */
    protected function place(string $title, mixed $location): Entry
    {
        if (is_string($location)) {
            $location = Fixture::ADDRESSES[$location] + ['full_address' => ucfirst($location) . ' SA'];
        } elseif (is_array($location) && array_is_list($location)) {
            $location = ['lat' => $location[0], 'lng' => $location[1]];
        }

        $entry = new Entry([
            'sectionId' => Fixture::section()->id,
            'typeId' => Fixture::entryType()->id,
            'title' => $title,
        ]);
        $entry->setFieldValue(Fixture::FIELD, $location);

        if (!Craft::$app->getElements()->saveElement($entry)) {
            $this->fail('Couldn’t save the entry: ' . implode(' ', $entry->getFirstErrors()));
        }
        $this->saved[] = $entry;

        return $entry;
    }

    protected function query(): EntryQuery
    {
        return Entry::find()->sectionId(Fixture::section()->id)->status(null);
    }

    /**
     * Titles of the entries a search finds, in order.
     *
     * @return string[]
     */
    protected function titles(mixed $param, ?EntryQuery $query = null): array
    {
        $query ??= $this->query();
        $handle = Fixture::FIELD;

        return array_map(fn(Entry $e) => (string)$e->title, $query->$handle($param)->all());
    }
}
