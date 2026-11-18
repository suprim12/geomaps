<?php

namespace suprim\geomaps\tests\Integration;

use Craft;
use craft\elements\Address;
use craft\elements\Entry;
use craft\elements\User;
use suprim\geomaps\jobs\GeocodeMissing;
use suprim\geomaps\models\Settings;
use suprim\geomaps\services\MissingCoordinates;
use suprim\geomaps\tests\Fixture;
use suprim\geomaps\tests\TestCase;

class AddressesTest extends TestCase
{
    /** @var Address[] */
    private array $addresses = [];

    protected function tearDown(): void
    {
        foreach ($this->addresses as $address) {
            Craft::$app->getElements()->deleteElement($address, true);
        }
        $this->addresses = [];
        parent::tearDown();
    }

    /**
     * An address in the fixture entry’s Addresses field, or owned by another element.
     */
    private function address(string $line, ?array $point = null, ?\craft\base\ElementInterface $owner = null): Address
    {
        $owner ??= $this->place('Owner of ' . $line, ['full_address' => 'Owner']);
        $address = new Address([
            'ownerId' => $owner->id,
            'fieldId' => $owner instanceof Entry ? Fixture::addressesField()->id : null,
            'countryCode' => 'AU',
            'title' => $line,
            'addressLine1' => $line,
            'latitude' => $point ? (string)$point['lat'] : null,
            'longitude' => $point ? (string)$point['lng'] : null,
        ]);
        if (!Craft::$app->getElements()->saveElement($address)) {
            $this->fail('Couldn’t save the address: ' . implode(' ', $address->getFirstErrors()));
        }
        $this->addresses[] = $address;

        return $address;
    }

    private function fresh(Address $address): Address
    {
        return Address::find()->id($address->id)->status(null)->one();
    }

    public function testSavingAnAddressWithoutCoordinatesStartsTheLookup(): void
    {
        $this->pro();
        $this->settings()->geocodeMissing = true;
        $this->address('Glenelg');

        $this->assertNotEmpty(array_filter(Fixture::$pushed, fn($job) => $job instanceof GeocodeMissing));

        $this->lite();
        Fixture::$pushed = [];
        Craft::$app->getCache()->delete('geomaps:geocode-queued');
        $this->address('Norwood');
        $this->assertSame([], array_filter(Fixture::$pushed, fn($job) => $job instanceof GeocodeMissing), 'Not on Lite.');
    }

    public function testAddressesAreFilledInCraftsOwnColumns(): void
    {
        $this->pro();
        $found = $this->address('Glenelg');
        $missing = $this->address('Nowhere at all');
        $pinned = $this->address('Norwood', Fixture::ADDRESSES['norwood']);

        $service = $this->plugin()->missingCoordinates;
        $items = array_values(array_filter(iterator_to_array($service->find(null, true), false), fn($item) => $item['type'] === Address::class && in_array($item['id'], [$found->id, $missing->id, $pinned->id], true)));
        $this->assertEqualsCanonicalizing([$found->id, $missing->id], array_column($items, 'id'), 'Addresses with coordinates aren’t looked up.');

        $results = [];
        foreach ($items as $item) {
            $results[$item['id']] = $service->fill($item);
        }

        $this->assertSame(MissingCoordinates::SAVED, $results[$found->id]);
        $this->assertSame(MissingCoordinates::NOT_FOUND, $results[$missing->id]);
        $this->assertEquals(Fixture::ADDRESSES['glenelg']['lat'], $this->fresh($found)->latitude);
        $this->assertEquals(Fixture::ADDRESSES['glenelg']['lng'], $this->fresh($found)->longitude);

        $key = (new \ReflectionMethod($service, 'notFoundKey'))->invoke($service, 'Nowhere at all', 'AU');
        Craft::$app->getCache()->delete($key);
    }

    public function testOnlyEntriesAddressesUnlessAllAreAskedFor(): void
    {
        $this->pro();
        $user = User::find()->admin()->one();
        $this->assertNotNull($user, 'The site needs an admin user.');
        $userAddress = $this->address('Glenelg', null, $user);
        $entryAddress = $this->address('Norwood');
        $ids = fn() => array_column(array_filter(iterator_to_array($this->plugin()->missingCoordinates->find(null, true), false), fn($i) => $i['type'] === Address::class), 'id');

        $this->assertContains($entryAddress->id, $ids());
        $this->assertNotContains($userAddress->id, $ids(), 'Users’ addresses stay private by default.');
        $this->assertFalse($this->plugin()->addresses->shouldQueue($userAddress));

        $this->settings()->geocodeAddresses = Settings::ADDRESSES_ALL;
        $this->assertContains($userAddress->id, $ids());

        $this->settings()->geocodeAddresses = false;
        $this->assertSame([], $ids());
    }

    public function testNearestAddressesFirst(): void
    {
        $this->pro();
        $owner = $this->place('Stores', ['full_address' => 'Stores']);
        $this->address('Glenelg', Fixture::ADDRESSES['glenelg'], $owner);
        $this->address('Norwood', Fixture::ADDRESSES['norwood'], $owner);
        $this->address('Melbourne', Fixture::ADDRESSES['melbourne'], $owner);
        $this->address('Not looked up yet', null, $owner);

        $near = fn(array $options) => Address::find()->ownerId($owner->id)->near($options)->all();

        $found = $near(['location' => Fixture::ADDRESSES['adelaide'], 'radius' => 20]);
        $this->assertSame(['Norwood', 'Glenelg'], array_map(fn($a) => $a->addressLine1, $found));
        $this->assertEqualsWithDelta(2.7, $found[0]->distance, 0.3);

        $this->assertSame(['Norwood', 'Glenelg', 'Melbourne'], array_map(fn($a) => $a->addressLine1, $near(['location' => 'Adelaide', 'radius' => null])));
        $this->assertSame(['Melbourne'], array_map(fn($a) => $a->addressLine1, $near(['location' => Fixture::ADDRESSES['melbourne'], 'radius' => 400, 'unit' => 'mi'])));
        $this->assertSame([], $near(['location' => 'Nowhere at all']));
        $this->assertSame(3, (int)Address::find()->ownerId($owner->id)->near(['location' => 'Adelaide', 'radius' => 1000])->count());

        $this->lite();
        $this->assertCount(4, $near(['location' => 'Adelaide', 'radius' => 20]), 'Ignored on Lite.');
    }

    public function testAnAddressCanBeTheSearchLocation(): void
    {
        $this->place('Glenelg store', 'glenelg');
        $this->place('Melbourne store', 'melbourne');
        $customer = $this->address('Norwood', Fixture::ADDRESSES['norwood']);
        $unpinned = $this->address('Adelaide');

        $this->assertSame(['Glenelg store'], $this->titles(['location' => $customer, 'radius' => 20]));
        $this->assertSame(['Glenelg store'], $this->titles(['location' => $unpinned, 'radius' => 20]), 'An address without coordinates is looked up.');

        /** @var \suprim\geomaps\models\GeoValue $value */
        $value = $this->query()->title('Glenelg store')->one()->getFieldValue(Fixture::FIELD);
        $this->assertSame(['Glenelg store'], $this->titles(['location' => $value, 'radius' => 1]), 'Another location field’s value too.');
    }

    public function testDistanceBetweenAnyTwoPlaces(): void
    {
        $geo = new \suprim\geomaps\variables\GeoVariable();
        $store = $this->place('Store', 'norwood')->getFieldValue(Fixture::FIELD);
        $customer = $this->address('Glenelg', Fixture::ADDRESSES['glenelg']);

        [$n, $g] = [Fixture::ADDRESSES['norwood'], Fixture::ADDRESSES['glenelg']];
        $this->assertEqualsWithDelta(\suprim\geomaps\services\MapService::distance($n['lat'], $n['lng'], $g['lat'], $g['lng']), $geo->distance($store, $customer), 0.001);
        $this->assertEqualsWithDelta($geo->distance($store, $customer) * 0.6214, $geo->distance($store, $customer, 'mi'), 0.05);
        $this->assertEqualsWithDelta(654, $geo->distance('Adelaide', Fixture::ADDRESSES['melbourne']), 3);
        $this->assertNull($geo->distance('Nowhere at all', $store));
    }

    public function testMapsShowAddresses(): void
    {
        $customer = $this->address('Glenelg', Fixture::ADDRESSES['glenelg']);
        $store = $this->place('Store', 'norwood')->getFieldValue(Fixture::FIELD);

        (new \suprim\geomaps\models\EmbedMap())->embed(['center' => $customer, 'markers' => [['location' => $customer, 'label' => 'Customer'], ['location' => $store, 'label' => 'Store']]]);
        $js = implode("\n", array_merge(...array_values(Craft::$app->getView()->js)));

        $this->assertStringContainsString('"center":{"lat":-34.9802,"lng":138.5113}', $js);
        $this->assertStringContainsString('"lat":-34.9213,"lng":138.6305,"label":"Store"', $js);
    }
}
