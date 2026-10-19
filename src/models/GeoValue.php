<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\models;

use craft\base\Model;
use craft\helpers\Json;
use sup\craftgeo\services\GeoService;

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
            'center' => ['lat' => $this->lat, 'lng' => $this->lng],
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
