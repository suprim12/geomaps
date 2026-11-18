<?php

namespace suprim\geomaps\tests\Integration;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\records\GeoRecord;
use suprim\geomaps\tests\Fixture;
use suprim\geomaps\tests\TestCase;

class SeveralLocationsTest extends TestCase
{
    /** Head office in Melbourne, branches in Glenelg and Norwood. */
    private function branches(): array
    {
        return Fixture::ADDRESSES['melbourne'] + [
            'full_address' => 'Head office, Melbourne',
            'label' => 'Head office',
            'locations' => [
                Fixture::ADDRESSES['glenelg'] + ['full_address' => 'Jetty Rd, Glenelg', 'label' => 'Glenelg'],
                Fixture::ADDRESSES['norwood'] + ['full_address' => 'The Parade, Norwood', 'label' => 'Norwood'],
            ],
        ];
    }

    private function rows(Entry $entry): array
    {
        return (new Query())->select(['locationIndex', 'lat', 'lng'])->from(GeoRecord::TABLE_NAME)
            ->where(['ownerId' => $entry->id, 'fieldId' => Fixture::field()->id])->orderBy('locationIndex')->all();
    }

    public function testOneLocationIsStoredExactlyAsBefore(): void
    {
        $value = GeoValue::fromArray(Fixture::ADDRESSES['adelaide'] + ['full_address' => 'Adelaide']);

        $this->assertSame(['lat', 'lng', 'zoom', 'full_address', 'address1', 'suburb', 'postcode', 'country', 'countryCode', 'geoJson'], array_keys($value->toArray()));
        $this->assertCount(1, $value->locations);
    }

    public function testMoreLocationsAreStoredAndReadBack(): void
    {
        $entry = $this->place('Chain', $this->branches());
        $value = Entry::find()->id($entry->id)->status(null)->one()->getFieldValue(Fixture::FIELD);

        $this->assertSame('Head office', $value->label);
        $this->assertSame(['Head office', 'Glenelg', 'Norwood'], array_map(fn($l) => $l->label, $value->locations));
        $this->assertSame('Head office, Melbourne', (string)$value, 'Templates written for one location show the first.');
        $this->assertSame(Fixture::ADDRESSES['melbourne']['lat'], $value->lat);
        $stored = $value->toArray();
        $this->assertSame(['lat', 'lng', 'full_address', 'address1', 'suburb', 'postcode', 'country', 'countryCode', 'label'], array_keys($stored['locations'][0]));
        $this->assertSame([0, 1, 2], array_map('intval', array_column($this->rows($entry), 'locationIndex')));
    }

    public function testDistanceSearchFindsTheNearestLocation(): void
    {
        $this->place('Chain', $this->branches());
        $this->place('Single shop', 'adelaide');
        $handle = Fixture::FIELD;

        $results = $this->query()->$handle(['location' => Fixture::ADDRESSES['glenelg'], 'radius' => 20])->all();

        $this->assertSame(['Chain', 'Single shop'], array_map(fn($e) => $e->title, $results), 'Each entry once, nearest first: the chain has a branch in Glenelg.');
        $chain = $results[0]->getFieldValue($handle);
        $this->assertSame('Glenelg', $chain->nearest->label);
        $this->assertEqualsWithDelta(0.0, $chain->distance, 0.01);
        $this->assertSame('Head office', $chain->label, 'The value itself is unchanged.');

        $this->assertSame(['Chain'], $this->titles(['location' => Fixture::ADDRESSES['melbourne'], 'radius' => 5]));
        $this->assertSame('Head office', $this->query()->$handle(['location' => Fixture::ADDRESSES['melbourne'], 'radius' => 5])->one()->getFieldValue($handle)->nearest->label);
        $this->assertSame([], $this->titles(['location' => Fixture::ADDRESSES['sydney'], 'radius' => 50]));
        $this->assertSame(2, (int)$this->query()->$handle(['location' => Fixture::ADDRESSES['glenelg'], 'radius' => 20])->count());
    }

    public function testAreasFindAnyLocation(): void
    {
        $this->pro();
        $this->place('Chain', $this->branches());

        $this->assertSame(['Chain'], $this->titles(['within' => '-35.1,138.4,-34.9,138.55']), 'Only the Glenelg branch is in this box.');
        $this->assertSame(['Chain'], $this->titles(':notempty:'));
    }

    public function testRemovingALocationRemovesItFromSearch(): void
    {
        $entry = $this->place('Chain', $this->branches());
        $value = $entry->getFieldValue(Fixture::FIELD);
        $value->setMoreLocations([$value->getMoreLocations()[1]]); // keep Norwood only
        $entry->setFieldValue(Fixture::FIELD, $value);
        Craft::$app->getElements()->saveElement($entry);

        $this->assertCount(2, $this->rows($entry));
        $this->assertSame([], $this->titles(['location' => Fixture::ADDRESSES['glenelg'], 'radius' => 3]));
        $this->assertSame(['Chain'], $this->titles(['location' => Fixture::ADDRESSES['norwood'], 'radius' => 3]));
    }

    public function testRebuildingTheLookupKeepsEveryLocation(): void
    {
        $entry = $this->place('Chain', $this->branches());
        $before = $this->rows($entry);

        $this->plugin()->maps->rebuildLookup(Fixture::field());

        $this->assertCount(3, $before);
        $this->assertEquals($before, $this->rows($entry));
    }

    public function testProblemsInAnotherLocationSayWhichOne(): void
    {
        $value = GeoValue::fromArray(['lat' => 1, 'lng' => 1, 'locations' => [['lat' => 2, 'lng' => 2], ['lat' => 95, 'lng' => 2]]]);

        $this->assertSame(['Location 3: Latitude must be between -90 and 90.'], $value->getInputErrors());
    }

    public function testLocationsPostedAsJson(): void
    {
        $value = GeoValue::fromArray(['lat' => 1, 'lng' => 1, 'locations' => json_encode([['lat' => 2, 'lng' => 3, 'full_address' => 'Two'], ['lat' => '', 'lng' => '']])]);

        $this->assertCount(2, $value->locations, 'Empty rows are left out.');
        $this->assertSame('Two', $value->locations[1]->full_address);
    }

    public function testExportAndImportKeepEveryLocation(): void
    {
        $this->pro();
        $entry = $this->place('Chain', $this->branches());
        $tmp = sys_get_temp_dir() . '/geomaps-test-' . bin2hex(random_bytes(4));
        mkdir($tmp);
        $args = ['section' => Fixture::SECTION, 'field' => Fixture::FIELD, 'interactive' => false];

        try {
            foreach (['csv', 'geojson'] as $format) {
                // Forget the branches, then bring them back from the file.
                $file = "$tmp/chain.$format";
                Craft::$app->runAction('geomaps/locations/export', $args + ['output' => $file]);
                $single = Entry::find()->id($entry->id)->status(null)->one();
                $single->setFieldValue(Fixture::FIELD, Fixture::ADDRESSES['melbourne']);
                Craft::$app->getElements()->saveElement($single);
                $this->assertCount(1, $this->rows($entry));

                ob_start();
                Craft::$app->runAction('geomaps/locations/import', [$file] + $args);
                ob_end_clean();

                $value = Entry::find()->id($entry->id)->status(null)->one()->getFieldValue(Fixture::FIELD);
                $this->assertSame(['Head office', 'Glenelg', 'Norwood'], array_map(fn($l) => $l->label, $value->locations), $format);
                $this->assertCount(3, $this->rows($entry), $format);
            }
        } finally {
            array_map('unlink', glob("$tmp/*"));
            rmdir($tmp);
        }
    }

    public function testMissingCoordinatesAreFoundForEachLocation(): void
    {
        $this->pro();
        $entry = $this->place('Chain', Fixture::ADDRESSES['melbourne'] + ['locations' => [['full_address' => 'Glenelg', 'label' => 'Glenelg']]]);
        $missing = $this->plugin()->missingCoordinates;

        $items = array_values(array_filter(iterator_to_array($missing->find(Fixture::FIELD, true), false), fn($i) => $i['id'] === $entry->id));
        $this->assertSame([['Glenelg', 1]], array_map(fn($i) => [$i['address'], $i['index']], $items));
        $this->assertSame('saved', $missing->fill($items[0]));

        $this->assertSame(['Chain'], $this->titles(['location' => Fixture::ADDRESSES['glenelg'], 'radius' => 1]));
        $this->assertSame(Fixture::ADDRESSES['melbourne']['lat'], $this->query()->id($entry->id)->one()->getFieldValue(Fixture::FIELD)->lat, 'The first location is untouched.');
    }

    public function testMapsShowEveryLocation(): void
    {
        $value = $this->place('Chain', $this->branches())->getFieldValue(Fixture::FIELD);

        $value->embed();
        $js = implode("\n", array_merge(...array_values(Craft::$app->getView()->js)));

        foreach (['Head office', 'Glenelg', 'Norwood'] as $label) {
            $this->assertStringContainsString('"label":"' . $label . '"', $js);
        }
        $this->assertStringContainsString('"fitMarkers":true', $js);
    }
}
