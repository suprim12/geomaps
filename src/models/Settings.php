<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\models;

use craft\base\Model;
use craft\helpers\App;

class Settings extends Model
{
    public ?string $mapToken = null;
    public ?string $geoToken = null;
    /** Mapbox access token, filled into `{mapboxToken}` in the tiles URL and used for Mapbox geocoding. */
    public ?string $mapboxToken = null;
    /** Maps on the front end (`embed()`, the store locator): `auto` (Google with a browser key, otherwise the map tiles), `google` or `tiles`. */
    public string $mapProvider = 'auto';
    /** Maps in the control panel (the field): `auto`, `google` or `tiles`. */
    public string $cpMapProvider = 'auto';
    /** Address lookups: `auto` (Google with a server key, otherwise OpenStreetMap), `nominatim`, `google` or `mapbox`. */
    public string $geocoder = 'auto';
    /**
     * Use Mapbox permanent geocoding. Mapbox only allows storing (and caching) results from permanent
     * requests, so Mapbox is used where coordinates are saved only when this is on.
     */
    public bool $mapboxPermanent = false;

    /** Raster tile URL template used when no Google key is set. */
    public string $tileUrl = self::OSM_TILE_URL;
    public string $tileAttribution = self::OSM_ATTRIBUTION;
    /** Pro: find coordinates in the background for saved locations that only have an address. */
    public bool $geocodeMissing = true;

    public const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    public const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['mapToken', 'geoToken', 'mapboxToken'], 'string'],
            [['mapToken', 'geoToken', 'mapboxToken'], 'default', 'value' => null],
            [['tileUrl', 'tileAttribution'], 'string'],
            ['tileUrl', 'default', 'value' => self::OSM_TILE_URL],
            ['tileAttribution', 'default', 'value' => self::OSM_ATTRIBUTION],
            ['geocodeMissing', 'boolean'],
            ['geocoder', 'in', 'range' => ['auto', 'nominatim', 'google', 'mapbox']],
            [['mapProvider', 'cpMapProvider'], 'in', 'range' => ['auto', 'google', 'tiles']],
            ['mapboxPermanent', 'boolean'],
        ]);
    }
    public function getMapToken(): ?string
    {
        return $this->mapToken
            ? $this->_parseEnv($this->mapToken)
            : null;
    }

    public function getGeoToken(): ?string
    {
        return $this->geoToken
            ? $this->_parseEnv($this->geoToken)
            : null;
    }

    /**
     * Map tiles and address search use Google when a map token is set, otherwise OpenStreetMap.
     */
    public function getTileUrl(): string
    {
        $url = $this->_parseEnv($this->tileUrl) ?: self::OSM_TILE_URL;

        return str_replace('{mapboxToken}', rawurlencode((string)$this->getMapboxToken()), $url);
    }

    public function getMapboxToken(): ?string
    {
        return $this->mapboxToken
            ? ($this->_parseEnv($this->mapboxToken) ?: null)
            : null;
    }

    /**
     * The tile provider's name for the settings overview.
     */
    public function getTileProviderName(): string
    {
        $host = parse_url($this->getTileUrl(), PHP_URL_HOST) ?: '';

        return match (true) {
            str_ends_with($host, 'mapbox.com') => 'Mapbox',
            str_ends_with($host, 'openstreetmap.org') => 'OpenStreetMap',
            str_ends_with($host, 'maptiler.com') => 'MapTiler',
            str_ends_with($host, 'stadiamaps.com') => 'Stadia Maps',
            str_ends_with($host, 'thunderforest.com') => 'Thunderforest',
            default => $host ?: 'OpenStreetMap',
        };
    }

    /**
     * The map shown on the front end (`site`) or in the control panel (`cp`): `google`, or `osm` for the
     * map tiles URL (OpenStreetMap, Mapbox or another tile provider). Google needs the browser key.
     */
    public function getMapProvider(string $context = 'site'): string
    {
        $choice = $context === 'cp' ? $this->cpMapProvider : $this->mapProvider;

        if ($choice === 'tiles' || !$this->getMapToken()) {
            return 'osm';
        }

        return 'google';
    }

    /**
     * The service for static map images (`img()`): Google with a Google map and a browser key,
     * otherwise Mapbox when there's a Mapbox token, otherwise Google if there's a key.
     */
    public function getStaticMapProvider(): ?string
    {
        if ($this->getMapProvider('site') === 'google') {
            return 'google';
        }

        if ($this->getMapboxToken()) {
            return 'mapbox';
        }

        return $this->getMapToken() ? 'google' : null;
    }

    /**
     * Google's terms don't allow Geocoding or Places API results with a non-Google map.
     */
    public function hasGoogleGeocodingWithOtherMaps(): bool
    {
        return $this->getGeocoder() === 'google'
            && ($this->getMapProvider('site') !== 'google' || $this->getMapProvider('cp') !== 'google');
    }

    /**
     * The service used for address lookups on the server: `google`, `mapbox` or `nominatim`.
     * A chosen service without its key falls back to the next available one.
     */
    public function getGeocoder(): string
    {
        $google = $this->getGeoToken() ? 'google' : 'nominatim';

        return match ($this->geocoder) {
            'mapbox' => $this->getMapboxToken() ? 'mapbox' : $google,
            'nominatim' => 'nominatim',
            default => $google,
        };
    }

    /**
     * Whether Mapbox may be used where coordinates are saved (the field, entry forms, missing coordinates).
     */
    public function usesMapboxForSaving(): bool
    {
        return $this->getGeocoder() === 'mapbox' && $this->mapboxPermanent;
    }

    private function _parseEnv($value): array|bool|string|null
    {
        if (is_string($value)) {
            return App::parseEnv($value);
        }

        return array_map(function($v) {
            return App::parseEnv($v);
        }, $value);
    }
}
