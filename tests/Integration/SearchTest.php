<?php

namespace suprim\geomaps\tests\Integration;

use Craft;
use craft\db\Query;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\records\GeoRecord;
use suprim\geomaps\tests\Fixture;
use suprim\geomaps\tests\TestCase;

class SearchTest extends TestCase
{
    public function testNearestFirstWithinTheRadius(): void
    {
        $this->place('Glenelg', 'glenelg');
        $this->place('Norwood', 'norwood');
        $this->place('Melbourne', 'melbourne');

        $this->assertSame(['Norwood', 'Glenelg'], $this->titles(['location' => Fixture::ADDRESSES['adelaide'], 'radius' => 20]));
        $this->assertSame(['Norwood', 'Glenelg', 'Melbourne'], $this->titles(['location' => Fixture::ADDRESSES['adelaide'], 'radius' => 1000]));
        $this->assertSame([], $this->titles(['location' => Fixture::ADDRESSES['sydney'], 'radius' => 10]));
    }

    public function testResultsSayHowFarAwayTheyAre(): void
    {
        $this->place('Melbourne', 'melbourne');
        $handle = Fixture::FIELD;

        $km = $this->query()->$handle(['location' => Fixture::ADDRESSES['adelaide'], 'radius' => 1000])->one()->$handle;
        $mi = $this->query()->$handle(['location' => Fixture::ADDRESSES['adelaide'], 'radius' => 1000, 'unit' => 'mi'])->one()->$handle;

        $this->assertInstanceOf(GeoValue::class, $km);
        $this->assertEqualsWithDelta(654, $km->distance, 3);
        $this->assertEqualsWithDelta(406, $mi->distance, 3);
        $this->assertSame([], $this->titles(['location' => Fixture::ADDRESSES['adelaide'], 'radius' => 400, 'unit' => 'mi']), 'The radius is in the unit too.');
    }

    public function testSearchesFromAnAddress(): void
    {
        $this->place('Norwood', 'norwood');

        $this->assertSame(['Norwood'], $this->titles(['location' => 'Adelaide', 'radius' => 10]));
        $this->assertContains('Adelaide', Fixture::$lookups);
        $this->assertSame([], $this->titles(['location' => 'Nowhere at all', 'radius' => 10000]), 'An address that isn’t found matches nothing.');
    }

    public function testATemplatesOwnOrderWins(): void
    {
        $this->place('B Glenelg', 'glenelg');
        $this->place('A Norwood', 'norwood');

        $this->assertSame(['A Norwood', 'B Glenelg'], $this->titles(['location' => Fixture::ADDRESSES['glenelg'], 'radius' => 50], $this->query()->orderBy('title')));
    }

    public function testEmptyAndNotEmpty(): void
    {
        $this->place('Pinned', 'adelaide');
        $this->place('Address only', ['full_address' => 'Somewhere']);

        $this->assertSame(['Pinned'], $this->titles(':notempty:'));
        $this->assertSame(['Address only'], $this->titles(':empty:'));
    }

    public function testWhichZoneContainsAPlace(): void
    {
        $this->place('Inner', ['geoJson' => ['type' => 'Polygon', 'coordinates' => [[[138.55, -34.95], [138.65, -34.95], [138.65, -34.90], [138.55, -34.90], [138.55, -34.95]]]]]);
        $this->place('Outer with a hole', ['geoJson' => ['type' => 'Polygon', 'coordinates' => [
            [[138.0, -35.5], [139.0, -35.5], [139.0, -34.5], [138.0, -34.5], [138.0, -35.5]],
            [[138.5, -35.0], [138.7, -35.0], [138.7, -34.85], [138.5, -34.85], [138.5, -35.0]],
        ]]]);

        $this->assertSame(['Inner'], $this->titles(['contains' => Fixture::ADDRESSES['adelaide']]), 'Adelaide is in the hole of the outer zone.');
        $this->assertSame(['Outer with a hole'], $this->titles(['contains' => ['lat' => -35.2, 'lng' => 138.2]]));
        $this->assertSame([], $this->titles(['contains' => Fixture::ADDRESSES['glenelg']]), 'Glenelg is in the hole too.');
        $this->assertSame([], $this->titles(['contains' => Fixture::ADDRESSES['sydney']]));
        $this->assertSame(['Inner'], $this->titles(['contains' => 'Adelaide']), 'By address.');
    }

    public function testWithinAnAreaNeedsPro(): void
    {
        $this->place('Glenelg', 'glenelg');
        $this->place('Melbourne', 'melbourne');
        $box = '-35.5,138,-34.5,139';

        $this->lite();
        $this->assertCount(2, $this->titles(['within' => $box]), 'Ignored on Lite.');

        $this->pro();
        $this->assertSame(['Glenelg'], $this->titles(['within' => $box]));
        $this->assertSame(['Glenelg'], $this->titles(['within' => ['type' => 'Polygon', 'coordinates' => [[[138, -35.5], [139, -35.5], [139, -34.5], [138, -34.5], [138, -35.5]]]]]));
        $this->assertSame([], $this->titles(['within' => '-36,150,-35,151']));
    }

    public function testWithinSortsByDistanceFromALocation(): void
    {
        $this->pro();
        $this->place('Glenelg', 'glenelg');
        $this->place('Norwood', 'norwood');

        $this->assertSame(['Glenelg', 'Norwood'], $this->titles(['within' => '-35.5,138,-34.5,139', 'location' => Fixture::ADDRESSES['glenelg']]));
        $this->assertSame(['Norwood', 'Glenelg'], $this->titles(['within' => '-35.5,138,-34.5,139', 'location' => Fixture::ADDRESSES['norwood']]));
    }

    public function testOnlyLiveContentIsSearchable(): void
    {
        $entry = $this->place('Live', 'adelaide');
        $draft = Craft::$app->getDrafts()->createDraft($entry);
        $draft->setFieldValue(Fixture::FIELD, Fixture::ADDRESSES['sydney']);
        Craft::$app->getElements()->saveElement($draft);

        try {
            $this->assertSame(['Live'], $this->titles(['location' => Fixture::ADDRESSES['adelaide'], 'radius' => 5]));
            $this->assertSame([], $this->titles(['location' => Fixture::ADDRESSES['sydney'], 'radius' => 5]), 'A draft’s location isn’t searched.');
        } finally {
            Craft::$app->getElements()->deleteElement($draft, true);
        }
    }

    public function testRebuildingTheLookupGivesTheSameRows(): void
    {
        $this->place('Glenelg', 'glenelg');
        $this->place('Zone', ['geoJson' => ['type' => 'Polygon', 'coordinates' => [[[138, -35], [139, -35], [139, -34], [138, -34], [138, -35]]]]]);
        $rows = fn() => (new Query())->select(['ownerId', 'ownerSiteId', 'lat', 'lng', 'shape', 'minLat', 'maxLat', 'minLng', 'maxLng'])
            ->from(GeoRecord::TABLE_NAME)->where(['fieldId' => Fixture::field()->id])->orderBy('ownerId')->all();
        $before = $rows();

        $this->plugin()->maps->rebuildLookup(Fixture::field());

        $this->assertCount(2, $before);
        $this->assertEquals($before, $rows());
    }

    public function testClearingTheLocationRemovesItFromSearch(): void
    {
        $entry = $this->place('Glenelg', 'glenelg');
        $entry->setFieldValue(Fixture::FIELD, null);
        Craft::$app->getElements()->saveElement($entry);

        $this->assertSame([], $this->titles(':notempty:'));
    }
}
