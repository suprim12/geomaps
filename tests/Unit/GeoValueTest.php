<?php

namespace suprim\geomaps\tests\Unit;

use PHPUnit\Framework\TestCase;
use suprim\geomaps\models\GeoValue;

class GeoValueTest extends TestCase
{
    public function testReadsItsOwnStoredFormat(): void
    {
        $value = GeoValue::fromArray([
            'lat' => '-34.9285', 'lng' => '138.6007', 'zoom' => '12',
            'full_address' => ' 1 King William St, Adelaide SA ', 'address1' => '1 King William St',
            'suburb' => 'Adelaide', 'postcode' => '5000', 'country' => 'Australia', 'countryCode' => 'AU',
        ]);

        $this->assertSame(-34.9285, $value->lat);
        $this->assertSame(138.6007, $value->lng);
        $this->assertSame(12, $value->zoom);
        $this->assertSame('1 King William St, Adelaide SA', $value->full_address, 'Text is trimmed.');
        $this->assertSame('1 King William St, Adelaide SA', (string)$value);
        $this->assertSame(['lat', 'lng', 'zoom', 'full_address', 'address1', 'suburb', 'postcode', 'country', 'countryCode', 'geoJson'], array_keys($value->toArray()));
    }

    public function testNothingToStoreIsNull(): void
    {
        $this->assertNull(GeoValue::fromArray(null));
        $this->assertNull(GeoValue::fromArray('text'));
        $this->assertNull(GeoValue::fromArray([]));
        $this->assertNull(GeoValue::fromArray(['lat' => '', 'lng' => '', 'full_address' => '  ']));
    }

    public function testBadCoordinatesAreReportedNotSaved(): void
    {
        $value = GeoValue::fromArray(['lat' => '91', 'lng' => 'east']);

        $this->assertNotNull($value, 'A value with errors is kept, so the errors can be shown.');
        $this->assertNull($value->lat);
        $this->assertNull($value->lng);
        $this->assertSame(['Latitude must be between -90 and 90.', 'Longitude must be a number.'], $value->getInputErrors());

        $half = GeoValue::fromArray(['lat' => '-34.9']);
        $this->assertSame(['Latitude and longitude must both be set.'], $half->getInputErrors());
    }

    public function testReadsValuesSavedByTheMapsPlugin(): void
    {
        $value = GeoValue::fromArray([
            'lat' => -34.9285, 'lng' => 138.6007, 'zoom' => 15,
            'address' => '1 King William St, Adelaide SA 5000, Australia',
            'parts' => ['number' => '1', 'address' => 'King William St', 'city' => 'Adelaide', 'postcode' => '5000', 'country' => 'Australia', 'country_short' => 'AU'],
        ]);

        $this->assertSame('1 King William St, Adelaide SA 5000, Australia', $value->full_address);
        $this->assertSame('1 King William St', $value->address1);
        $this->assertSame('Adelaide', $value->suburb);
        $this->assertSame('AU', $value->countryCode);
        $this->assertSame('Adelaide', $value->parts['city'], 'Templates using the Maps plugin’s parts keep working.');
        $this->assertSame($value->full_address, $value->address);
    }

    public function testReadsBareGeoJsonAndCentresOnIt(): void
    {
        $value = GeoValue::fromArray(['type' => 'Polygon', 'coordinates' => [[[138, -35], [139, -35], [139, -34], [138, -34], [138, -35]]]]);

        $this->assertSame(-34.5, $value->lat);
        $this->assertSame(138.5, $value->lng);
        $this->assertSame('Polygon', $value->getGeoJsonData()['type']);
    }

    public function testLengthOfLines(): void
    {
        // One degree of longitude along the equator.
        $value = GeoValue::fromArray(['geoJson' => ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 0]]]]);

        $this->assertEqualsWithDelta(111.19, $value->length(), 0.01);
        $this->assertEqualsWithDelta(111194.9, $value->length('m'), 1);
        $this->assertEqualsWithDelta(69.09, $value->length('mi'), 0.01);
        $this->assertSame(0.0, GeoValue::fromArray(['lat' => 1, 'lng' => 1])->length());
    }

    public function testAreaLeavesOutHoles(): void
    {
        $square = [[0, 0], [0.01, 0], [0.01, 0.01], [0, 0.01], [0, 0]];
        $hole = [[0.0025, 0.0025], [0.0075, 0.0025], [0.0075, 0.0075], [0.0025, 0.0075], [0.0025, 0.0025]];

        $full = GeoValue::fromArray(['geoJson' => ['type' => 'Polygon', 'coordinates' => [$square]]]);
        $holed = GeoValue::fromArray(['geoJson' => ['type' => 'Polygon', 'coordinates' => [$square, $hole]]]);

        $this->assertEqualsWithDelta(123.6, $full->area('ha'), 0.5);
        $this->assertEqualsWithDelta($full->area('ha') * 0.75, $holed->area('ha'), 0.5);
        $this->assertEqualsWithDelta($full->area('km2') * 100, $full->area('ha'), 0.001);
    }
}
