<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim <support@suprim.com.au> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\models;

use craft\base\Model;
use craft\helpers\App;

class Settings extends Model
{
    public ?string $mapToken = null;
    public ?string $geoToken = null;

    /** Raster tile URL template used when no Google key is set. */
    public string $tileUrl = self::OSM_TILE_URL;
    public string $tileAttribution = self::OSM_ATTRIBUTION;

    public const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    public const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['mapToken', 'geoToken'], 'string'],
            [['mapToken', 'geoToken'], 'default', 'value' => null],
            [['tileUrl', 'tileAttribution'], 'string'],
            ['tileUrl', 'default', 'value' => self::OSM_TILE_URL],
            ['tileAttribution', 'default', 'value' => self::OSM_ATTRIBUTION],
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
        return $this->_parseEnv($this->tileUrl) ?: self::OSM_TILE_URL;
    }

    public function getMapProvider(): string
    {
        return $this->getMapToken() ? 'google' : 'osm';
    }

    /**
     * Server-side geocoding uses Google when a geocoding token is set, otherwise Nominatim.
     */
    public function getGeocoder(): string
    {
        return $this->getGeoToken() ? 'google' : 'nominatim';
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
