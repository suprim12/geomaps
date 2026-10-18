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

    public ?float  $lat          = null;
    public ?float  $lng          = null;
    public ?int    $zoom         = null;
    public ?string $full_address = null;
    public ?string $address1     = null;
    public ?string $suburb       = null;
    public ?string $postcode     = null;
    public ?string $country      = null;
    public ?string $countryCode  = null;
    public ?string $geoJson      = null;

    /**
     * Distance from the searched location, in the search unit.
     * Only set on results of a proximity search.
     */
    public ?float  $distance     = null;

    /**
     * Builds a value from stored/posted data. Returns null when there is nothing to store.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $value = new self();
        $value->lat = self::coordinate($data['lat'] ?? null, 'Latitude', 90, $value->_inputErrors);
        $value->lng = self::coordinate($data['lng'] ?? null, 'Longitude', 180, $value->_inputErrors);

        if (($value->lat === null) !== ($value->lng === null) && empty($value->_inputErrors)) {
            $value->_inputErrors[] = 'Latitude and longitude must both be set.';
        }

        $value->zoom         = isset($data['zoom']) && is_numeric($data['zoom']) ? (int)$data['zoom'] : null;
        $value->full_address = self::stringOrNull($data['full_address'] ?? null);
        $value->address1     = self::stringOrNull($data['address1'] ?? null);
        $value->suburb       = self::stringOrNull($data['suburb'] ?? null);
        $value->postcode     = self::stringOrNull($data['postcode'] ?? null);
        $value->country      = self::stringOrNull($data['country'] ?? null);
        $value->countryCode  = self::stringOrNull($data['countryCode'] ?? null);

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

    public function __toString(): string
    {
        return (string)($this->full_address ?? ($this->hasCoordinates() ? "{$this->lat}, {$this->lng}" : ''));
    }

    // -------------------------------------------------------------------------
    // Lazy-initialised map models
    // -------------------------------------------------------------------------

    /** Cached StaticMap instance — built on first access. */
    private ?StaticMap $_staticMap = null;

    /** @var string[] */
    private array $_inputErrors = [];

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
            $this->_staticMap         = new StaticMap();
            $this->_staticMap->center = ['lat' => $this->lat, 'lng' => $this->lng];
            $this->_staticMap->zoom   = $this->zoom ?? 14;
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

        // Field value provides the defaults; caller options take precedence.
        $merged = array_merge([
            'center' => ['lat' => $this->lat, 'lng' => $this->lng],
            'zoom'   => $this->zoom ?? 14,
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