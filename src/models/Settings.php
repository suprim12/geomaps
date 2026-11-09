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

use craft\base\Model;
use craft\helpers\App;

class Settings extends Model
{
    public const SERVICE_OSM = 'openstreetmap';
    public const SERVICE_MAPBOX = 'mapbox';
    public const SERVICE_GOOGLE = 'google';

    /**
     * The map service for maps, address search, lookups and static images: `openstreetmap`, `mapbox` or
     * `google`. Null on sites set up before 1.4, where it's worked out from the keys and tiles URL.
     */
    public ?string $service = null;

    /** Google browser key. */
    public ?string $mapToken = null;
    /** Google server key. */
    public ?string $geoToken = null;
    /** Mapbox access token. */
    public ?string $mapboxToken = null;
    /** Mapbox style as `owner/style-id`, such as `mapbox/streets-v12`, or a `mapbox://styles/…` URL. */
    public string $mapboxStyle = 'mapbox/streets-v12';
    /**
     * Use Mapbox permanent geocoding for addresses that are saved. Mapbox only allows storing results from
     * permanent requests, so with this off, saved addresses come from OpenStreetMap instead. Config file only.
     */
    public bool $mapboxPermanent = true;

    /**
     * Config file only. With the Google service: `tiles` shows the map tiles (Mapbox, with a Mapbox token,
     * or the tiles URL) instead of Google maps on the front end (`mapProvider`) or in the field (`cpMapProvider`).
     */
    public string $mapProvider = 'auto';
    public string $cpMapProvider = 'auto';

    /** Tiles URL for the OpenStreetMap service: the OpenStreetMap tiles, or another tile provider. */
    public string $tileUrl = self::OSM_TILE_URL;
    public string $tileAttribution = self::OSM_ATTRIBUTION;
    /** Pro: find coordinates in the background for saved locations that only have an address. */
    public bool $geocodeMissing = true;

    public const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    public const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    public const MAPBOX_ATTRIBUTION = '© <a href="https://www.mapbox.com/about/maps">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> <strong><a href="https://apps.mapbox.com/feedback/">Improve this map</a></strong>';
    public const MAPBOX_STYLES = [
        'mapbox/streets-v12' => 'Streets',
        'mapbox/light-v11' => 'Light',
        'mapbox/dark-v11' => 'Dark',
        'mapbox/satellite-streets-v12' => 'Satellite',
    ];

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['mapToken', 'geoToken', 'mapboxToken'], 'string'],
            [['mapToken', 'geoToken', 'mapboxToken'], 'default', 'value' => null],
            [['tileUrl', 'tileAttribution', 'mapboxStyle'], 'string'],
            ['tileUrl', 'default', 'value' => self::OSM_TILE_URL],
            ['tileAttribution', 'default', 'value' => self::OSM_ATTRIBUTION],
            ['mapboxStyle', 'default', 'value' => 'mapbox/streets-v12'],
            ['mapboxStyle', fn($attribute) => $this->getService() !== self::SERVICE_MAPBOX || $this->getMapboxStyle() ? null : $this->addError($attribute, Craft::t('geomaps', 'Enter a Mapbox style URL such as {example}.', ['example' => 'mapbox://styles/your-name/style-id']))],
            ['service', 'in', 'range' => [self::SERVICE_OSM, self::SERVICE_MAPBOX, self::SERVICE_GOOGLE]],
            ['mapboxToken', 'required', 'when' => fn() => $this->service === self::SERVICE_MAPBOX, 'message' => Craft::t('geomaps', 'Add a Mapbox access token to use Mapbox.')],
            ['mapToken', 'required', 'when' => fn() => $this->service === self::SERVICE_GOOGLE, 'message' => Craft::t('geomaps', 'Add a browser key to use Google Maps.')],
            ['geocodeMissing', 'boolean'],
            ['mapboxPermanent', 'boolean'],
            [['mapProvider', 'cpMapProvider'], 'in', 'range' => ['auto', 'google', 'tiles']],
        ]);
    }

    /**
     * The chosen map service. Sites set up before there was a choice keep what they had: Google with a
     * browser key, Mapbox with a Mapbox tiles URL, otherwise OpenStreetMap.
     */
    public function getService(): string
    {
        if (in_array($this->service, [self::SERVICE_OSM, self::SERVICE_MAPBOX, self::SERVICE_GOOGLE], true)) {
            return $this->service;
        }

        if ($this->mapToken) {
            return self::SERVICE_GOOGLE;
        }

        if ($this->getMapboxToken() && $this->_mapboxTileUrl()) {
            return self::SERVICE_MAPBOX;
        }

        return self::SERVICE_OSM;
    }

    public function getMapToken(): ?string
    {
        return $this->mapToken
            ? ($this->_parseEnv($this->mapToken) ?: null)
            : null;
    }

    public function getGeoToken(): ?string
    {
        return $this->geoToken
            ? ($this->_parseEnv($this->geoToken) ?: null)
            : null;
    }

    public function getMapboxToken(): ?string
    {
        if ($this->mapboxToken) {
            return $this->_parseEnv($this->mapboxToken) ?: null;
        }

        // Before 1.4, Mapbox tiles URLs had the token in them.
        return $this->service === null ? $this->getOldMapboxToken() : null;
    }

    /**
     * The token in a Mapbox tiles URL saved before 1.4, if it has one.
     */
    public function getOldMapboxToken(): ?string
    {
        $url = $this->_mapboxTileUrl();
        if ($url && preg_match('#[?&]access_token=(pk\.[\w.-]+)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * The Mapbox style as `owner/style-id`, from the style setting or, on sites that set a Mapbox tiles URL
     * before 1.4, from that URL. Null if the setting isn't a Mapbox style.
     */
    public function getMapboxStyle(): ?string
    {
        if ($this->service === null && preg_match('#api\.mapbox\.com/styles/v1/([\w.-]+/[\w.-]+)/tiles#', (string)$this->_mapboxTileUrl(), $m)) {
            return $m[1];
        }

        $style = trim((string)$this->_parseEnv($this->mapboxStyle));
        if (preg_match('#^(?:mapbox://styles/|https://api\.mapbox\.com/styles/v1/)?([\w.-]+/[\w.-]+)/?$#', $style, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Whether maps that aren't Google maps use Mapbox: with the Mapbox service, or with Google and a Mapbox
     * token where the config file chooses `tiles`.
     */
    public function usesMapboxTiles(): bool
    {
        $service = $this->getService();

        return $this->getMapboxToken() && $this->getMapboxStyle()
            && ($service === self::SERVICE_MAPBOX || $service === self::SERVICE_GOOGLE);
    }

    /**
     * The tiles URL for maps that aren't Google maps.
     */
    public function getTileUrl(): string
    {
        if ($this->usesMapboxTiles()) {
            return 'https://api.mapbox.com/styles/v1/' . $this->getMapboxStyle() . '/tiles/256/{z}/{x}/{y}@2x?access_token=' . rawurlencode((string)$this->getMapboxToken());
        }

        if ($this->hasOldMapboxTileUrl()) {
            return self::OSM_TILE_URL;
        }

        return $this->_parseEnv($this->tileUrl) ?: self::OSM_TILE_URL;
    }

    public function getTileAttribution(): string
    {
        if ($this->usesMapboxTiles()) {
            return self::MAPBOX_ATTRIBUTION;
        }

        return $this->hasOldMapboxTileUrl() ? self::OSM_ATTRIBUTION : ($this->tileAttribution ?: self::OSM_ATTRIBUTION);
    }

    /**
     * A Mapbox tiles URL saved before the Mapbox service existed. Mapbox now has its own setting, so with
     * another service these tiles aren't used.
     */
    public function hasOldMapboxTileUrl(): bool
    {
        // Sites that haven't chosen a service yet keep their Mapbox tiles (see getService()).
        return $this->service !== null && $this->_mapboxTileUrl() !== null;
    }

    /**
     * The tiles URL, if it's a Mapbox style URL.
     */
    private function _mapboxTileUrl(): ?string
    {
        $url = (string)$this->_parseEnv($this->tileUrl);

        return str_contains($url, 'api.mapbox.com/styles/') ? $url : null;
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
     * The map shown on the front end (`site`) or in the control panel (`cp`): `google`, or `osm` for a
     * Leaflet map with the tiles (OpenStreetMap, Mapbox or another tile provider).
     */
    public function getMapProvider(string $context = 'site'): string
    {
        if ($this->getService() !== self::SERVICE_GOOGLE || !$this->getMapToken()) {
            return 'osm';
        }

        $choice = $context === 'cp' ? $this->cpMapProvider : $this->mapProvider;

        return $choice === 'tiles' ? 'osm' : 'google';
    }

    /**
     * The service for static map images (`img()`): `google`, `mapbox`, or null when there's neither.
     */
    public function getStaticMapProvider(): ?string
    {
        return match (true) {
            $this->getMapProvider('site') === 'google' => 'google',
            $this->usesMapboxTiles() => 'mapbox',
            default => null,
        };
    }

    /**
     * The service used for address lookups on the server: `google`, `mapbox` or `nominatim`.
     */
    public function getGeocoder(): string
    {
        return match ($this->getService()) {
            // Google's terms only allow Geocoding API results with Google maps, and lookups on the server
            // are used for front-end searches, so Google is only used with Google maps on the front end.
            self::SERVICE_GOOGLE => $this->getGeoToken() && $this->getMapProvider('site') === 'google' ? 'google' : 'nominatim',
            self::SERVICE_MAPBOX => $this->getMapboxToken() ? 'mapbox' : 'nominatim',
            default => 'nominatim',
        };
    }

    /**
     * Whether Mapbox is used where coordinates are saved (the field, entry forms, missing coordinates).
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
