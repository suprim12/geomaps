<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\services;

use craft\helpers\Json;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Reads and writes locations as CSV, GeoJSON, KML and GPX, for `geomaps/locations/import` and `export` (Pro).
 *
 * A record is `['title' => …, 'slug' => …, 'id' => …, 'value' => [Geo Maps value], 'fields' => [other values]]`.
 */
class LocationFiles
{
    public const FORMATS = ['csv', 'geojson', 'kml', 'gpx'];

    /** CSV columns for the location, and other names people use for them. */
    private const CSV_COLUMNS = [
        'lat' => ['lat', 'latitude'],
        'lng' => ['lng', 'lon', 'long', 'longitude'],
        'full_address' => ['full_address', 'address', 'fulladdress'],
        'address1' => ['address1', 'street', 'street_address'],
        'suburb' => ['suburb', 'city', 'town', 'locality'],
        'postcode' => ['postcode', 'zip', 'postal_code', 'postalcode'],
        'country' => ['country'],
        'countryCode' => ['countrycode', 'country_code'],
        'geoJson' => ['geojson'],
    ];

    public static function formatFromPath(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'csv' => 'csv',
            'geojson', 'json' => 'geojson',
            'kml' => 'kml',
            'gpx' => 'gpx',
            default => null,
        };
    }

    // Reading
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array{title: ?string, slug: ?string, id: ?int, value: ?array, fields: array<string, mixed>}>
     */
    public static function read(string $contents, string $format): array
    {
        return match ($format) {
            'csv' => self::readCsv($contents),
            'geojson' => self::readGeoJson($contents),
            'kml' => self::readKml($contents),
            'gpx' => self::readGpx($contents),
            default => throw new InvalidArgumentException("Unknown format: $format"),
        };
    }

    private static function readCsv(string $contents): array
    {
        $contents = (string)preg_replace('/^\xEF\xBB\xBF/', '', $contents); // Excel's byte order mark
        // fgetcsv handles quoted cells with line breaks in them, such as GeoJSON.
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $lines = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $lines[] = $row;
        }
        fclose($stream);
        $header = $lines ? array_shift($lines) : [];
        if (!$header || $header === [null]) {
            return [];
        }

        // Which location value each column holds, by its normalised name.
        $map = [];
        foreach ($header as $i => $name) {
            $key = strtolower(trim((string)$name));
            foreach (self::CSV_COLUMNS as $prop => $names) {
                if (in_array($key, $names, true)) {
                    $map[$i] = $prop;
                    continue 2;
                }
            }
        }

        $records = [];
        foreach ($lines as $cells) {
            if ($cells === [null] || implode('', array_map('strval', $cells)) === '') {
                continue;
            }
            $value = [];
            $other = [];
            foreach ($header as $i => $name) {
                $cell = isset($cells[$i]) ? trim((string)$cells[$i]) : '';
                if (isset($map[$i])) {
                    if ($cell !== '') {
                        $value[$map[$i]] = $cell;
                    }
                } else {
                    $other[trim((string)$name)] = $cell;
                }
            }
            if (isset($value['geoJson'])) {
                $value['geoJson'] = Json::decodeIfJson($value['geoJson']);
            }
            $records[] = self::record($other, $value);
        }

        return $records;
    }

    private static function readGeoJson(string $contents): array
    {
        $data = Json::decodeIfJson($contents);
        if (!is_array($data)) {
            throw new InvalidArgumentException('The file isn’t valid GeoJSON.');
        }

        $features = match ($data['type'] ?? null) {
            'FeatureCollection' => is_array($data['features'] ?? null) ? $data['features'] : [],
            'Feature' => [$data],
            default => [['type' => 'Feature', 'properties' => [], 'geometry' => $data]],
        };

        $records = [];
        foreach ($features as $feature) {
            if (!is_array($feature) || !is_array($feature['geometry'] ?? null)) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $records[] = self::record($props, self::valueFromGeometry($feature['geometry'], $props));
        }

        return $records;
    }

    private static function readKml(string $contents): array
    {
        $xml = self::xml($contents, 'KML');
        $records = [];

        foreach ($xml->xpath('//*[local-name()="Placemark"]') ?: [] as $placemark) {
            $name = self::xpathText($placemark, '*[local-name()="name"]');
            $props = ['title' => $name, 'description' => self::xpathText($placemark, '*[local-name()="description"]')];
            foreach ($placemark->xpath('.//*[local-name()="Data"]') ?: [] as $data) {
                $props[(string)$data['name']] = self::xpathText($data, '*[local-name()="value"]');
            }

            $geometries = [];
            foreach ($placemark->xpath('.//*[local-name()="Point"]') ?: [] as $point) {
                $positions = self::kmlCoordinates(self::xpathText($point, '*[local-name()="coordinates"]'));
                if ($positions) {
                    $geometries[] = ['type' => 'Point', 'coordinates' => $positions[0]];
                }
            }
            foreach ($placemark->xpath('.//*[local-name()="LineString"]') ?: [] as $line) {
                $positions = self::kmlCoordinates(self::xpathText($line, '*[local-name()="coordinates"]'));
                if (count($positions) > 1) {
                    $geometries[] = ['type' => 'LineString', 'coordinates' => $positions];
                }
            }
            foreach ($placemark->xpath('.//*[local-name()="Polygon"]') ?: [] as $polygon) {
                $rings = [];
                foreach (['outerBoundaryIs', 'innerBoundaryIs'] as $boundary) {
                    foreach ($polygon->xpath('*[local-name()="' . $boundary . '"]//*[local-name()="coordinates"]') ?: [] as $coords) {
                        $ring = self::kmlCoordinates((string)$coords);
                        if (count($ring) >= 4) {
                            $rings[] = $ring;
                        }
                    }
                }
                if ($rings) {
                    $geometries[] = ['type' => 'Polygon', 'coordinates' => $rings];
                }
            }

            if (!$geometries) {
                continue;
            }
            $geometry = count($geometries) === 1 ? $geometries[0] : ['type' => 'GeometryCollection', 'geometries' => $geometries];
            $records[] = self::record(array_filter($props, fn($v) => $v !== null && $v !== ''), self::valueFromGeometry($geometry, $props));
        }

        return $records;
    }

    private static function readGpx(string $contents): array
    {
        $xml = self::xml($contents, 'GPX');
        $records = [];
        $point = fn(SimpleXMLElement $p) => [round((float)$p['lon'], 7), round((float)$p['lat'], 7)];

        // Tracks and routes become lines; waypoints become points.
        foreach (['trk' => './/*[local-name()="trkpt"]', 'rte' => './/*[local-name()="rtept"]'] as $tag => $pointPath) {
            foreach ($xml->xpath('//*[local-name()="' . $tag . '"]') ?: [] as $track) {
                $positions = array_map($point, $track->xpath($pointPath) ?: []);
                if (count($positions) < 2) {
                    continue;
                }
                $props = ['title' => self::xpathText($track, '*[local-name()="name"]'), 'description' => self::xpathText($track, '*[local-name()="desc"]')];
                $records[] = self::record(array_filter($props), self::valueFromGeometry(['type' => 'LineString', 'coordinates' => $positions], $props));
            }
        }
        foreach ($xml->xpath('//*[local-name()="wpt"]') ?: [] as $wpt) {
            $props = ['title' => self::xpathText($wpt, '*[local-name()="name"]'), 'description' => self::xpathText($wpt, '*[local-name()="desc"]')];
            $records[] = self::record(array_filter($props), ['lat' => (float)$wpt['lat'], 'lng' => (float)$wpt['lon']]);
        }

        return $records;
    }

    /**
     * A Geo Maps value from a GeoJSON geometry: points become the pin, other shapes are drawn.
     */
    private static function valueFromGeometry(array $geometry, array $props): array
    {
        if (($geometry['type'] ?? null) === 'Point' && is_array($geometry['coordinates'] ?? null)) {
            $value = ['lng' => $geometry['coordinates'][0] ?? null, 'lat' => $geometry['coordinates'][1] ?? null];
        } else {
            $shapeProps = array_intersect_key($props, array_flip(['name', 'title', 'color', 'stroke', 'fill']));
            if (isset($shapeProps['title']) && !isset($shapeProps['name'])) {
                $shapeProps['name'] = $shapeProps['title'];
            }
            unset($shapeProps['title']);
            $value = ['geoJson' => ['type' => 'Feature', 'properties' => $shapeProps ?: new \stdClass(), 'geometry' => $geometry]];
        }

        // Address details stored as properties.
        foreach (self::CSV_COLUMNS as $prop => $names) {
            if ($prop === 'lat' || $prop === 'lng' || $prop === 'geoJson') {
                continue;
            }
            foreach ($names as $name) {
                foreach ($props as $key => $v) {
                    if (strtolower((string)$key) === $name && is_scalar($v) && $v !== '') {
                        $value[$prop] = (string)$v;
                        continue 3;
                    }
                }
            }
        }

        return $value;
    }

    /**
     * A record from a row or feature's other values: title, slug and id are picked out, the rest kept.
     */
    private static function record(array $props, ?array $value): array
    {
        $title = $slug = null;
        $id = null;
        $fields = [];
        foreach ($props as $key => $v) {
            $lower = strtolower((string)$key);
            if (in_array($lower, ['title', 'name'], true)) {
                if ($title === null && is_scalar($v) && $v !== '') {
                    $title = (string)$v;
                }
            } elseif ($lower === 'slug') {
                $slug = is_scalar($v) && $v !== '' ? (string)$v : null;
            } elseif ($lower === 'id') {
                $id = is_numeric($v) ? (int)$v : null;
            } elseif (in_array($lower, ['color', 'stroke', 'fill'], true)) {
                // Used for the drawn shape's colour.
            } elseif (!in_array($lower, array_merge(...array_values(self::CSV_COLUMNS)), true)) {
                $fields[(string)$key] = $v;
            }
        }

        return ['title' => $title, 'slug' => $slug, 'id' => $id, 'value' => $value ?: null, 'fields' => $fields];
    }

    private static function xml(string $contents, string $label): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET: never fetch anything over the network while parsing.
        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if (!$xml instanceof SimpleXMLElement) {
            throw new InvalidArgumentException("The file isn’t valid $label.");
        }

        return $xml;
    }

    private static function xpathText(SimpleXMLElement $node, string $path): ?string
    {
        $found = $node->xpath($path);

        return $found ? (trim((string)$found[0]) ?: null) : null;
    }

    /**
     * KML coordinates (`lng,lat[,alt]` separated by spaces) as GeoJSON positions.
     */
    private static function kmlCoordinates(?string $text): array
    {
        $positions = [];
        foreach (preg_split('/\s+/', trim((string)$text)) ?: [] as $tuple) {
            $parts = explode(',', $tuple);
            if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $positions[] = [round((float)$parts[0], 7), round((float)$parts[1], 7)];
            }
        }

        return $positions;
    }

    // Writing
    // -------------------------------------------------------------------------

    /**
     * @param array<int, array{id: int, title: string, slug: ?string, url: ?string, value: \suprim\geomaps\models\GeoValue, fields: array<string, mixed>}> $rows
     */
    public static function write(array $rows, string $format): string
    {
        return match ($format) {
            'csv' => self::writeCsv($rows),
            'geojson' => Json::encode(self::featureCollection($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'kml' => self::writeKml($rows),
            default => throw new InvalidArgumentException("Can’t export to $format. Use csv, geojson or kml."),
        };
    }

    private static function writeCsv(array $rows): string
    {
        $extra = $rows ? array_keys($rows[0]['fields']) : [];
        $header = array_merge(['id', 'title', 'slug', 'url', 'lat', 'lng', 'full_address', 'address1', 'suburb', 'postcode', 'country', 'countryCode', 'geoJson'], $extra);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, $header, ',', '"', '');
        foreach ($rows as $row) {
            $v = $row['value'];
            fputcsv($out, array_merge(
                [$row['id'], $row['title'], $row['slug'], $row['url'], $v->lat, $v->lng, $v->full_address, $v->address1, $v->suburb, $v->postcode, $v->country, $v->countryCode, ($shape = $v->getGeoJsonData()) ? Json::encode($shape, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null],
                array_map(fn($x) => is_scalar($x) || $x === null ? $x : Json::encode($x), array_values($row['fields']))
            ), ',', '"', '');
        }
        rewind($out);
        $csv = (string)stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private static function featureCollection(array $rows): array
    {
        $features = [];
        foreach ($rows as $row) {
            $v = $row['value'];
            $props = array_filter([
                'id' => $row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'url' => $row['url'],
                'full_address' => $v->full_address,
                'address1' => $v->address1,
                'suburb' => $v->suburb,
                'postcode' => $v->postcode,
                'country' => $v->country,
                'countryCode' => $v->countryCode,
            ], fn($x) => $x !== null && $x !== '') + $row['fields'];

            if ($v->hasCoordinates()) {
                $features[] = ['type' => 'Feature', 'properties' => $props, 'geometry' => ['type' => 'Point', 'coordinates' => [$v->lng, $v->lat]]];
            }
            // Drawn shapes, each with the entry's details and its own shape properties.
            foreach (self::shapes($v->getGeoJsonData()) as $shape) {
                $features[] = ['type' => 'Feature', 'properties' => (is_array($shape['properties'] ?? null) ? $shape['properties'] : []) + $props, 'geometry' => $shape['geometry']];
            }
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * Every feature with a non-point geometry in a GeoJSON value.
     */
    private static function shapes(?array $geoJson): array
    {
        if (!$geoJson) {
            return [];
        }

        return match ($geoJson['type'] ?? null) {
            'FeatureCollection' => array_merge([], ...array_map(fn($f) => is_array($f) ? self::shapes($f) : [], (array)($geoJson['features'] ?? []))),
            'Feature' => is_array($geoJson['geometry'] ?? null) && ($geoJson['geometry']['type'] ?? null) !== 'Point' ? [$geoJson] : [],
            'Point', null => [],
            default => [['type' => 'Feature', 'properties' => [], 'geometry' => $geoJson]],
        };
    }

    private static function writeKml(array $rows): string
    {
        $e = fn(?string $s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $kml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<kml xmlns="http://www.opengis.net/kml/2.2"><Document>' . "\n";

        foreach (self::featureCollection($rows)['features'] as $feature) {
            $p = $feature['properties'];
            $geometry = self::kmlGeometry($feature['geometry']);
            if ($geometry === '') {
                continue;
            }
            $data = '';
            foreach ($p as $key => $value) {
                if (is_scalar($value)) {
                    $data .= '<Data name="' . $e((string)$key) . '"><value>' . $e((string)$value) . '</value></Data>';
                }
            }
            $kml .= '<Placemark><name>' . $e($p['name'] ?? $p['title'] ?? '') . '</name>'
                . (isset($p['full_address']) ? '<address>' . $e($p['full_address']) . '</address>' : '')
                . '<ExtendedData>' . $data . '</ExtendedData>' . $geometry . "</Placemark>\n";
        }

        return $kml . '</Document></kml>' . "\n";
    }

    private static function kmlGeometry(array $geometry): string
    {
        $coords = fn(array $positions) => implode(' ', array_map(fn($p) => ($p[0] ?? 0) . ',' . ($p[1] ?? 0), $positions));
        $c = $geometry['coordinates'] ?? [];

        return match ($geometry['type'] ?? null) {
            'Point' => '<Point><coordinates>' . ($c[0] ?? 0) . ',' . ($c[1] ?? 0) . '</coordinates></Point>',
            'LineString' => '<LineString><coordinates>' . $coords($c) . '</coordinates></LineString>',
            'Polygon' => self::kmlPolygon($c, $coords),
            'MultiLineString' => '<MultiGeometry>' . implode('', array_map(fn($l) => '<LineString><coordinates>' . $coords($l) . '</coordinates></LineString>', $c)) . '</MultiGeometry>',
            'MultiPolygon' => '<MultiGeometry>' . implode('', array_map(fn($poly) => self::kmlPolygon($poly, $coords), $c)) . '</MultiGeometry>',
            'GeometryCollection' => '<MultiGeometry>' . implode('', array_map(fn($g) => is_array($g) ? self::kmlGeometry($g) : '', (array)($geometry['geometries'] ?? []))) . '</MultiGeometry>',
            default => '',
        };
    }

    private static function kmlPolygon(array $rings, callable $coords): string
    {
        $kml = '<Polygon>';
        foreach (array_values($rings) as $i => $ring) {
            $tag = $i === 0 ? 'outerBoundaryIs' : 'innerBoundaryIs';
            $kml .= "<$tag><LinearRing><coordinates>" . $coords($ring) . "</coordinates></LinearRing></$tag>";
        }

        return $kml . '</Polygon>';
    }
}
