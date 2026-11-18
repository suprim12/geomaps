<?php

namespace suprim\geomaps\tests\Integration;

use Craft;
use craft\elements\Entry;
use craft\helpers\FileHelper;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\services\MissingCoordinates;
use suprim\geomaps\tests\Fixture;
use suprim\geomaps\tests\TestCase;

class ProFeaturesTest extends TestCase
{
    private ?string $tmp = null;

    protected function tearDown(): void
    {
        if ($this->tmp && is_dir($this->tmp)) {
            FileHelper::removeDirectory($this->tmp);
        }
        parent::tearDown();
    }

    public function testMissingCoordinatesAreFilledFromTheAddress(): void
    {
        $this->pro();
        $this->settings()->geocodeMissing = false; // filled here, not by a queued job
        $found = $this->place('Found', ['full_address' => 'Glenelg']);
        $this->place('Not found', ['full_address' => 'Nowhere at all']);
        $this->place('Already pinned', 'norwood');

        $missing = $this->plugin()->missingCoordinates;
        $items = iterator_to_array($missing->find(Fixture::FIELD, true), false);
        $this->assertEqualsCanonicalizing(['Glenelg', 'Nowhere at all'], array_column($items, 'address'));

        $results = [];
        foreach ($items as $item) {
            $results[$item['address']] = $missing->fill($item);
        }

        ksort($results);
        $this->assertSame(['Glenelg' => MissingCoordinates::SAVED, 'Nowhere at all' => MissingCoordinates::NOT_FOUND], $results);
        $value = Entry::find()->id($found->id)->status(null)->one()->getFieldValue(Fixture::FIELD);
        $this->assertSame(Fixture::ADDRESSES['glenelg']['lat'], $value->lat);
        $this->assertSame(['Found'], $this->titles(['location' => Fixture::ADDRESSES['glenelg'], 'radius' => 1]), 'It’s searchable straight away.');
        $this->assertSame([], iterator_to_array($missing->find(Fixture::FIELD), false), 'An address that wasn’t found isn’t tried again on every run.');

        // Forget the test’s address, so the site’s cache keeps nothing from it.
        $key = (new \ReflectionMethod($missing, 'notFoundKey'))->invoke($missing, 'Nowhere at all', $items[array_search('Nowhere at all', array_column($items, 'address'))]['country']);
        Craft::$app->getCache()->delete($key);
    }

    public function testStructuredDataNeedsPro(): void
    {
        $entry = $this->place('Adelaide Store', ['lat' => -34.9285, 'lng' => 138.6007, 'address1' => '1 King William St', 'suburb' => 'Adelaide', 'postcode' => '5000', 'countryCode' => 'AU']);
        /** @var GeoValue $value */
        $value = $entry->getFieldValue(Fixture::FIELD);

        $this->lite();
        $this->assertNull($value->schemaData());
        $this->assertSame('', (string)$value->schema());

        $this->pro();
        $data = $value->schemaData(['type' => 'Store', 'telephone' => '08 8000 0000', 'openingHours' => [['day' => 'monday', 'open' => '9:00', 'close' => '17:00']]]);
        $this->assertSame('Store', $data['@type']);
        $this->assertSame('Adelaide Store', $data['name']);
        $this->assertSame(['@type' => 'PostalAddress', 'streetAddress' => '1 King William St', 'addressLocality' => 'Adelaide', 'postalCode' => '5000', 'addressCountry' => 'AU'], $data['address']);
        $this->assertSame(-34.9285, $data['geo']['latitude']);
        $this->assertSame([['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Monday', 'opens' => '09:00', 'closes' => '17:00']], $data['openingHoursSpecification']);

        $value->full_address = '</script><script>alert(1)</script>';
        $html = (string)$value->schema(['description' => '</script><b>']);
        $this->assertSame(1, substr_count($html, '</script>'), 'Values can’t end the script tag.');
    }

    public function testExportThenImportUpdatesTheSameEntries(): void
    {
        $this->pro();
        $this->place('Glenelg', 'glenelg');
        $this->place('Zone', ['geoJson' => ['type' => 'Polygon', 'coordinates' => [[[138, -35], [139, -35], [139, -34], [138, -34], [138, -35]]]]]);
        $this->tmp = sys_get_temp_dir() . '/geomaps-test-' . bin2hex(random_bytes(4));
        FileHelper::createDirectory($this->tmp);
        $args = ['section' => Fixture::SECTION, 'field' => Fixture::FIELD, 'interactive' => false];

        foreach (['csv', 'geojson', 'kml'] as $format) {
            $file = "$this->tmp/places.$format";
            $this->assertSame(0, Craft::$app->runAction('geomaps/locations/export', $args + ['output' => $file]), $format);
            $this->assertFileExists($file);

            $before = $this->query()->count();
            ob_start();
            $code = Craft::$app->runAction('geomaps/locations/import', [$file] + $args);
            ob_end_clean();
            $this->assertSame(0, $code, "$format import");
            $this->assertSame($before, $this->query()->count(), "$format: re-importing updates, it doesn’t make copies.");
        }

        $this->assertSame(['Glenelg'], $this->titles(['location' => Fixture::ADDRESSES['glenelg'], 'radius' => 1]));
        $this->assertSame(['Zone'], $this->titles(['contains' => ['lat' => -34.5, 'lng' => 138.5]]));
    }
}
