<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\models;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use DateTimeInterface;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\GeoService;
use Twig\Markup;

/**
 * GeoValue is the object returned by GeoField::normalizeValue().
 *
 * Twig usage:
 *
 *   {{ entry.location.embed({ id: 'map' }) }}
 *   {{ entry.location.map.img() }}
 *   {{ entry.location.lat }}, {{ entry.location.lng }}
 */
class GeoValue extends Model
{
    // -------------------------------------------------------------------------
    // Raw field data
    // -------------------------------------------------------------------------

    public ?float  $lat = null;
    public ?float  $lng = null;
    public ?int    $zoom = null;
    public ?string $full_address = null;
    public ?string $address1 = null;
    public ?string $suburb = null;
    public ?string $postcode = null;
    public ?string $country = null;
    public ?string $countryCode = null;
    public ?string $geoJson = null;

    /**
     * Distance from the searched location, in the search unit.
     * Only set on results of a proximity search.
     */
    public ?float  $distance = null;

    /**
     * Builds a value from stored/posted data. Returns null when there is nothing to store.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        if (self::isSimpleMapData($data)) {
            $data = self::convertSimpleMapData($data);
        } elseif (self::isGeoJsonData($data)) {
            $data = self::convertGeoJsonData($data);
        }

        $value = new self();
        $value->lat = self::coordinate($data['lat'] ?? null, 'Latitude', 90, $value->_inputErrors);
        $value->lng = self::coordinate($data['lng'] ?? null, 'Longitude', 180, $value->_inputErrors);

        if (($value->lat === null) !== ($value->lng === null) && empty($value->_inputErrors)) {
            $value->_inputErrors[] = 'Latitude and longitude must both be set.';
        }

        $value->zoom = isset($data['zoom']) && is_numeric($data['zoom']) ? (int)$data['zoom'] : null;
        $value->full_address = self::stringOrNull($data['full_address'] ?? null);
        $value->address1 = self::stringOrNull($data['address1'] ?? null);
        $value->suburb = self::stringOrNull($data['suburb'] ?? null);
        $value->postcode = self::stringOrNull($data['postcode'] ?? null);
        $value->country = self::stringOrNull($data['country'] ?? null);
        $value->countryCode = self::stringOrNull($data['countryCode'] ?? null);

        $geoJson = $data['geoJson'] ?? null;
        $value->geoJson = is_array($geoJson) ? json_encode($geoJson) : self::stringOrNull($geoJson);

        return $value->isEmpty() && empty($value->_inputErrors) ? null : $value;
    }

    private static function coordinate(mixed $raw, string $label, int $limit, array &$errors): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            $errors[] = "$label must be a number.";
            return null;
        }

        $raw = (float)$raw;

        if ($raw < -$limit || $raw > $limit) {
            $errors[] = "$label must be between -$limit and $limit.";
            return null;
        }

        return $raw;
    }

    /**
     * Problems found in the raw input that couldn’t be represented in the value.
     *
     * @return string[]
     */
    public function getInputErrors(): array
    {
        return $this->_inputErrors;
    }

    /**
     * Whether the data was saved by Maps (ether/simplemap), which uses `address` and `parts`.
     */
    public static function isSimpleMapData(array $data): bool
    {
        return !array_key_exists('full_address', $data)
            && (array_key_exists('address', $data) || array_key_exists('parts', $data));
    }

    /**
     * Converts a Maps (ether/simplemap) value — current or legacy parts format — to Geo Maps data.
     */
    public static function convertSimpleMapData(array $data): array
    {
        $parts = $data['parts'] ?? [];
        if (is_string($parts)) {
            $parts = Json::decodeIfJson($parts);
        }
        if (!is_array($parts)) {
            $parts = [];
        }

        $part = function(string ...$keys) use ($parts): ?string {
            foreach ($keys as $key) {
                $v = $parts[$key] ?? null;
                if (is_string($v) && trim($v) !== '') {
                    return trim($v);
                }
            }
            return null;
        };

        $street = trim(implode(' ', array_filter([
            $part('number', 'street_number'),
            $part('address', 'route'),
        ])));

        $country = $part('country');
        $countryCode = $part('country_short');
        if (!$countryCode && $country) {
            $countryCode = GeoService::countryCodeFromName($country);
        }

        return [
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'zoom' => $data['zoom'] ?? null,
            'full_address' => $data['address'] ?? null,
            'address1' => $street !== '' ? $street : null,
            'suburb' => $part('city', 'locality', 'postal_town', 'sublocality', 'neighborhood'),
            'postcode' => $part('postcode', 'postal_code'),
            'country' => $country,
            'countryCode' => $countryCode,
            'geoJson' => null,
        ];
    }

    /** Metres per degree of arc on the mean Earth radius (6,371,008.8 m). */
    private const METRES_PER_DEGREE = 111_194.93;

    public const GEOJSON_TYPES = [
        'Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon',
        'GeometryCollection', 'Feature', 'FeatureCollection',
    ];

    /**
     * Whether the data is bare GeoJSON, as saved by Craft Geoman (handplant/craft-geoman).
     */
    public static function isGeoJsonData(array $data): bool
    {
        return isset($data['type'])
            && in_array($data['type'], self::GEOJSON_TYPES, true)
            && !array_key_exists('lat', $data)
            && !array_key_exists('geoJson', $data);
    }

    /**
     * Wraps bare GeoJSON as Geo Maps data, using the centre of its shapes as the location.
     */
    public static function convertGeoJsonData(array $geoJson): array
    {
        $centre = self::geoJsonCentre($geoJson);

        return [
            'lat' => $centre['lat'] ?? null,
            'lng' => $centre['lng'] ?? null,
            'geoJson' => json_encode($geoJson),
        ];
    }

    /**
     * Centre of the bounding box of every coordinate in a GeoJSON object, or null if it has none.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function geoJsonCentre(array $geoJson): ?array
    {
        $minLat = $minLng = INF;
        $maxLat = $maxLng = -INF;

        $walk = function(mixed $node) use (&$walk, &$minLat, &$minLng, &$maxLat, &$maxLng): void {
            if (!is_array($node)) {
                return;
            }
            // A position is [lng, lat, ...].
            if (isset($node[0], $node[1]) && is_numeric($node[0]) && is_numeric($node[1])) {
                $minLng = min($minLng, (float)$node[0]);
                $maxLng = max($maxLng, (float)$node[0]);
                $minLat = min($minLat, (float)$node[1]);
                $maxLat = max($maxLat, (float)$node[1]);
                return;
            }
            foreach (['coordinates', 'geometry', 'geometries', 'features'] as $key) {
                if (isset($node[$key])) {
                    $walk($node[$key]);
                }
            }
            if (array_is_list($node)) {
                foreach ($node as $child) {
                    $walk($child);
                }
            }
        };
        $walk($geoJson);

        if ($minLat === INF) {
            return null;
        }

        return ['lat' => ($minLat + $maxLat) / 2, 'lng' => ($minLng + $maxLng) / 2];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public function isEmpty(): bool
    {
        return !$this->hasCoordinates()
            && $this->full_address === null
            && $this->address1 === null
            && $this->geoJson === null;
    }

    /**
     * Returns the decoded GeoJSON, or null.
     */
    public function getGeoJsonData(): ?array
    {
        if ($this->geoJson === null) {
            return null;
        }

        $data = json_decode($this->geoJson, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Whether this value’s areas (polygons) contain a place: an address, `{ lat, lng }` or another value.
     *
     *   {% if zone.binZone.contains('12 Smith St, Norwood SA') %}
     */
    public function contains(mixed $location, ?string $country = null): bool
    {
        $data = $this->getGeoJsonData();
        if (!$data) {
            return false;
        }

        if ($location instanceof self) {
            $location = ['lat' => $location->lat, 'lng' => $location->lng];
        }

        $point = GeoService::resolveLocation($location, $country);

        return $point !== null && \suprim\geomaps\Plugin::getInstance()->zones->contains($data, (float)$point['lat'], (float)$point['lng']);
    }

    /**
     * Total length of the value's lines (LineString and MultiLineString), e.g. a trail or route.
     * `unit` is `km` (default), `mi` or `m`. Returns 0 when there are no lines.
     *
     *   {{ entry.route.length('km')|number_format(1) }} km
     */
    public function length(string $unit = 'km'): float
    {
        $metres = 0.0;

        foreach (self::geometries($this->getGeoJsonData()) as $geometry) {
            $lines = match ($geometry['type'] ?? null) {
                'LineString' => [$geometry['coordinates'] ?? []],
                'MultiLineString' => $geometry['coordinates'] ?? [],
                default => [],
            };

            foreach ($lines as $line) {
                $metres += self::lineMetres(is_array($line) ? $line : []);
            }
        }

        return match (strtolower($unit)) {
            'm', 'metres', 'meters' => $metres,
            'mi', 'mile', 'miles' => $metres / 1609.344,
            default => $metres / 1000,
        };
    }

    /**
     * Total area of the value's polygons, with holes removed.
     * `unit` is `km2` (default), `ha`, `m2`, `mi2` or `acres`. Returns 0 when there are no polygons.
     *
     *   {{ entry.zone.area('ha')|number_format(1) }} ha
     */
    public function area(string $unit = 'km2'): float
    {
        $data = $this->getGeoJsonData();
        $squareMetres = 0.0;

        foreach ($data ? \suprim\geomaps\Plugin::getInstance()->zones->polygons($data) : [] as $polygon) {
            foreach (is_array($polygon) ? array_values($polygon) : [] as $i => $ring) {
                $ringArea = self::ringSquareMetres(is_array($ring) ? $ring : []);
                $squareMetres += $i === 0 ? $ringArea : -$ringArea;
            }
        }

        $squareMetres = max(0.0, $squareMetres);

        return match (strtolower($unit)) {
            'm2', 'sqm' => $squareMetres,
            'ha', 'hectares' => $squareMetres / 10_000,
            'mi2', 'sqmi' => $squareMetres / 2_589_988.11,
            'acres', 'acre', 'ac' => $squareMetres / 4_046.856_42,
            default => $squareMetres / 1_000_000,
        };
    }

    /**
     * Every geometry in a GeoJSON object (features and geometry collections unwrapped).
     *
     * @return array<int, array>
     */
    private static function geometries(?array $geoJson): array
    {
        return match ($geoJson['type'] ?? null) {
            null => [],
            'FeatureCollection' => array_merge([], ...array_map(
                fn($feature) => is_array($feature) ? self::geometries($feature) : [],
                is_array($geoJson['features'] ?? null) ? $geoJson['features'] : []
            )),
            'Feature' => is_array($geoJson['geometry'] ?? null) ? self::geometries($geoJson['geometry']) : [],
            'GeometryCollection' => array_merge([], ...array_map(
                fn($geometry) => is_array($geometry) ? self::geometries($geometry) : [],
                is_array($geoJson['geometries'] ?? null) ? $geoJson['geometries'] : []
            )),
            default => [$geoJson],
        };
    }

    /** Great-circle length of a line of [lng, lat] positions, in metres. */
    private static function lineMetres(array $positions): float
    {
        $metres = 0.0;
        $previous = null;

        foreach ($positions as $position) {
            if (!isset($position[0], $position[1]) || !is_numeric($position[0]) || !is_numeric($position[1])) {
                continue;
            }
            if ($previous) {
                // Angle between the points in degrees, times metres per degree on the mean Earth radius.
                $degrees = \suprim\geomaps\services\MapService::distance($previous[1], $previous[0], (float)$position[1], (float)$position[0], 1.0);
                $metres += $degrees * self::METRES_PER_DEGREE;
            }
            $previous = [(float)$position[0], (float)$position[1]];
        }

        return $metres;
    }

    /** Area of a ring of [lng, lat] positions on the sphere, in square metres. */
    private static function ringSquareMetres(array $ring): float
    {
        $radius = 6_378_137.0;
        $total = 0.0;
        $positions = array_values(array_filter($ring, fn($p) => isset($p[0], $p[1]) && is_numeric($p[0]) && is_numeric($p[1])));

        for ($i = 0, $n = count($positions); $i < $n - 1; $i++) {
            [$lng1, $lat1] = [(float)$positions[$i][0], (float)$positions[$i][1]];
            [$lng2, $lat2] = [(float)$positions[$i + 1][0], (float)$positions[$i + 1][1]];
            $total += deg2rad($lng2 - $lng1) * (2 + sin(deg2rad($lat1)) + sin(deg2rad($lat2)));
        }

        return abs($total * $radius * $radius / 2);
    }

    // Maps (ether/simplemap) compatibility, so migrated templates keep working
    // -------------------------------------------------------------------------

    /**
     * Alias of `full_address` (Maps by Ether: `entry.map.address`).
     */
    public function getAddress(): ?string
    {
        return $this->full_address;
    }

    /**
     * Address parts in the Maps by Ether shape (`entry.map.parts.city`).
     */
    public function getParts(): array
    {
        return [
            'number' => null,
            'address' => $this->address1,
            'city' => $this->suburb,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'countryCode' => $this->countryCode,
        ];
    }

    /**
     * Static map image URL (Maps by Ether: `entry.map.img({ width: 600 })`).
     */
    public function img(array $options = []): ?string
    {
        return $this->getMap()?->img($options);
    }

    public function imgSrcSet(array $options = []): ?string
    {
        return $this->getMap()?->imgSrcSet($options);
    }

    /**
     * Pro: schema.org structured data for this location as a JSON-LD `<script>` tag, for location pages.
     *
     *   {{ entry.storeLocation.schema({ type: 'Store', telephone: entry.phone }) }}
     *
     * The name and URL come from the entry. See schemaData() for the options.
     */
    public function schema(array $options = []): Markup
    {
        $data = $this->schemaData($options);
        if (!$data) {
            return new Markup('', 'UTF-8');
        }

        // HEX_TAG keeps `</script>` in a value from ending the tag.
        $json = Json::encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT);

        return new Markup('<script type="application/ld+json">' . $json . '</script>', 'UTF-8');
    }

    /**
     * Pro: the structured data as an array, to add to other JSON-LD or pass to an SEO plugin.
     *
     * Options: `type` (default `LocalBusiness`), `name`, `url`, `openingHours` (rows with `day`, `open` and
     * `close`, such as a Table field, or strings like `Mo-Fr 09:00-17:00`), and any other schema.org
     * properties, such as `telephone`, `email`, `image`, `priceRange` or `sameAs`, which are added as they are.
     * Assets and entries become their URL, dates become ISO 8601, and empty values are left out.
     */
    public function schemaData(array $options = []): ?array
    {
        if (!Plugin::getInstance()->isPro()) {
            Craft::warning('Geo Maps: schema() needs Geo Maps Pro, so no structured data was output.', __METHOD__);
            return null;
        }

        $owner = $this->_element;
        $type = $options['type'] ?? 'LocalBusiness';
        $hours = $options['openingHours'] ?? null;
        unset($options['type'], $options['openingHours']);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $options['name'] ?? ($owner ? (string)$owner : null),
            'url' => $options['url'] ?? $owner?->getUrl(),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $this->address1 ?? ($this->suburb || $this->postcode ? null : $this->full_address),
                'addressLocality' => $this->suburb,
                'postalCode' => $this->postcode,
                'addressCountry' => $this->countryCode ?: $this->country,
            ], fn($v) => $v !== null && $v !== ''),
            'geo' => $this->hasCoordinates() ? ['@type' => 'GeoCoordinates', 'latitude' => $this->lat, 'longitude' => $this->lng] : null,
        ];
        unset($options['name'], $options['url']);

        if (count($data['address']) === 1) {
            $data['address'] = null;
        }

        if (is_array($hours) && $hours) {
            if (is_array(reset($hours))) {
                $data['openingHoursSpecification'] = self::openingHoursSpecification($hours);
            } else {
                $data['openingHours'] = array_values(array_filter($hours, 'is_string'));
            }
        } elseif (is_string($hours) && $hours !== '') {
            $data['openingHours'] = $hours;
        }

        foreach ($options as $key => $value) {
            $data[$key] = self::schemaValue($value);
        }

        return array_filter($data, fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Opening hours rows (`day`, `open`, `close`) as schema.org OpeningHoursSpecification.
     */
    private static function openingHoursSpecification(array $rows): array
    {
        $specs = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $day = $row['day'] ?? $row['dayOfWeek'] ?? null;
            if ($day instanceof \Stringable) {
                $day = (string)$day; // a Table field's dropdown column
            }
            $open = self::time($row['open'] ?? $row['opens'] ?? null);
            $close = self::time($row['close'] ?? $row['closes'] ?? null);
            if (!is_string($day) || $day === '' || !$open || !$close) {
                continue;
            }
            $specs[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ucfirst(strtolower($day)), 'opens' => $open, 'closes' => $close];
        }

        return $specs;
    }

    private static function time(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }
        if (is_array($value) && isset($value['time'])) {
            $value = $value['time'];
        }

        return is_string($value) && preg_match('/^\d{1,2}:\d{2}/', $value) ? substr(str_pad($value, 5, '0', STR_PAD_LEFT), 0, 5) : null;
    }

    private static function schemaValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof ElementQueryInterface => self::schemaValue($value->one()),
            $value instanceof Asset => $value->getUrl(),
            $value instanceof ElementInterface => $value->getUrl() ?? (string)$value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            is_array($value) => array_map(fn($v) => self::schemaValue($v), $value),
            $value instanceof \Stringable => (string)$value,
            default => $value,
        };
    }

    /**
     * The element this value belongs to, when it came from a field.
     */
    public function getElement(): ?ElementInterface
    {
        return $this->_element;
    }

    public function setElement(?ElementInterface $element): void
    {
        $this->_element = $element;
    }

    public function __toString(): string
    {
        // Address first; shape-only values print their GeoJSON (as Craft Geoman fields did).
        return (string)($this->full_address ?? $this->geoJson ?? ($this->hasCoordinates() ? "{$this->lat}, {$this->lng}" : ''));
    }

    // -------------------------------------------------------------------------
    // Lazy-initialised map models
    // -------------------------------------------------------------------------

    /** Cached StaticMap instance — built on first access. */
    private ?StaticMap $_staticMap = null;

    /** @var string[] */
    private array $_inputErrors = [];

    private ?ElementInterface $_element = null;

    /** Whether the field shows a location pin (set by the field; used as the embed() default). */
    private bool $_showMarker = true;

    public function setShowMarker(bool $showMarker): void
    {
        $this->_showMarker = $showMarker;
    }

    /**
     * Returns the StaticMap model for this value.
     *
     * Usage:
     *   <img src="{{ entry.location.map.img() }}">
     *   <img srcset="{{ entry.location.map.imgSrcSet() }}">
     */
    public function getMap(): ?StaticMap
    {
        if (!$this->hasCoordinates()) {
            return null;
        }

        if ($this->_staticMap === null) {
            $this->_staticMap = new StaticMap();
            $this->_staticMap->center = ['lat' => $this->lat, 'lng' => $this->lng];
            $this->_staticMap->zoom = $this->zoom ?? 14;
        }

        return $this->_staticMap;
    }

    /**
     * Renders an interactive embed div and registers the required JS/CSS.
     * Called only when the template actually outputs the embed, so assets
     * are never registered on pages that don't use it.
     *
     * Usage:
     *   {{ entry.location.embed({ id: 'map', markers: [{}] }) }}
     *   {{ entry.location.embed()|attr({ class: 'map' }) }}
     *
     * The `center` and `zoom` options are pre-filled from the field value
     * but can be overridden via $options if needed.
     */
    public function embed(array $options = []): \Twig\Markup
    {
        $model = new EmbedMap();

        // Field value provides the defaults (location, zoom, shapes, pin); caller options take precedence.
        $merged = array_merge([
            // Values with only an address (e.g. from a feed) are centred on the geocoded address.
            'center' => $this->hasCoordinates() ? ['lat' => $this->lat, 'lng' => $this->lng] : $this->full_address,
            'zoom' => $this->zoom ?? 14,
            'geoJson' => $this->getGeoJsonData(),
            'marker' => $this->_showMarker,
        ], $options);

        return $model->embed($merged);
    }

    // -------------------------------------------------------------------------
    // Serialisation — used by Feed Me, GraphQL mutations, toArray(), etc.
    // -------------------------------------------------------------------------

    /**
     * Defines which properties are included when Yii serialises this model
     * (toArray(), JSON encoding, REST responses). Excludes the lazy map models
     * so they are never accidentally serialised.
     */
    public function fields(): array
    {
        return [
            'lat',
            'lng',
            'zoom',
            'full_address',
            'address1',
            'suburb',
            'postcode',
            'country',
            'countryCode',
            'geoJson',
        ];
    }
}
