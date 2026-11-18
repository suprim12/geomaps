<?php

namespace suprim\geomaps\tests\Unit;

use PHPUnit\Framework\TestCase;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\LocationFiles;
use suprim\geomaps\services\MapService;

class GeometryTest extends TestCase
{
    public function testDistanceBetweenCities(): void
    {
        // Adelaide to Melbourne is about 654 km in a straight line.
        $this->assertEqualsWithDelta(654, MapService::distance(-34.9285, 138.6007, -37.8136, 144.9631), 3);
        $this->assertEqualsWithDelta(406, MapService::distance(-34.9285, 138.6007, -37.8136, 144.9631, MapService::MI_PER_DEGREE), 3);
        $this->assertEqualsWithDelta(0.0, MapService::distance(-34.9, 138.6, -34.9, 138.6), 0.001);
    }

    public function testReadsMapBoundsInEachForm(): void
    {
        $box = ['south' => -35.0, 'west' => 138.0, 'north' => -34.0, 'east' => 139.0];

        $this->assertSame($box, MapService::parseBounds('-35,138,-34,139'));
        $this->assertSame($box, MapService::parseBounds([[-35, 138], [-34, 139]]));
        $this->assertSame($box, MapService::parseBounds(['north' => -34, 'south' => -35, 'east' => 139, 'west' => 138]));
        $this->assertNull(MapService::parseBounds('-35,138,-34'), 'Three numbers.');
        $this->assertNull(MapService::parseBounds('-34,138,-35,139'), 'South north of north.');
        $this->assertNull(MapService::parseBounds('-35,138,-34,190'), 'Out of range.');
        $this->assertNull(MapService::parseBounds(['north' => 'x']));
    }

    public function testZonesWithHoles(): void
    {
        $zones = Plugin::getInstance()->zones;
        $zone = ['type' => 'Polygon', 'coordinates' => [
            [[138, -35], [139, -35], [139, -34], [138, -34], [138, -35]],
            [[138.4, -34.6], [138.6, -34.6], [138.6, -34.4], [138.4, -34.4], [138.4, -34.6]],
        ]];

        $this->assertTrue($zones->contains($zone, -34.2, 138.2));
        $this->assertFalse($zones->contains($zone, -34.5, 138.5), 'Inside the hole.');
        $this->assertFalse($zones->contains($zone, -33.0, 138.5), 'Outside.');

        $collection = ['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'properties' => [], 'geometry' => $zone]]];
        $this->assertTrue($zones->contains($collection, -34.2, 138.2), 'Shapes drawn in the field are a feature collection.');
        $this->assertSame(['minLat' => -35.0, 'maxLat' => -34.0, 'minLng' => 138.0, 'maxLng' => 139.0], $zones->bounds($zones->polygons($zone)));
    }

    public function testImportFilesInEachFormat(): void
    {
        $csv = "title,lat,lng,address,phone\nCity,-34.9285,138.6007,\"1 King William St, Adelaide\",123\n";
        $rows = LocationFiles::read($csv, 'csv');
        $this->assertSame('City', $rows[0]['title']);
        $this->assertSame('1 King William St, Adelaide', $rows[0]['value']['full_address']);
        $this->assertEquals(-34.9285, $rows[0]['value']['lat']);
        $this->assertSame(['phone' => '123'], $rows[0]['fields'], 'Other columns are kept for other fields.');

        $geoJson = json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['name' => 'Pin'], 'geometry' => ['type' => 'Point', 'coordinates' => [138.6, -34.9]]],
            ['type' => 'Feature', 'properties' => ['name' => 'Trail'], 'geometry' => ['type' => 'LineString', 'coordinates' => [[138.6, -34.9], [138.7, -34.8]]]],
        ]]);
        $rows = LocationFiles::read($geoJson, 'geojson');
        $this->assertSame(['lng' => 138.6, 'lat' => -34.9], $rows[0]['value']);
        $this->assertSame('LineString', $rows[1]['value']['geoJson']['geometry']['type']);

        $kml = '<?xml version="1.0"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document><Placemark><name>Pin</name><Point><coordinates>138.6,-34.9,0</coordinates></Point></Placemark></Document></kml>';
        $rows = LocationFiles::read($kml, 'kml');
        $this->assertSame('Pin', $rows[0]['title']);
        $this->assertEquals(-34.9, $rows[0]['value']['lat']);

        $gpx = '<?xml version="1.0"?><gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1"><trk><name>Walk</name><trkseg><trkpt lat="-34.9" lon="138.6"/><trkpt lat="-34.8" lon="138.7"/></trkseg></trk></gpx>';
        $rows = LocationFiles::read($gpx, 'gpx');
        $this->assertSame('Walk', $rows[0]['title']);
        $this->assertSame('LineString', $rows[0]['value']['geoJson']['geometry']['type']);
    }
}
