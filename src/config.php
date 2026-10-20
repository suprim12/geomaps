<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim <support@suprim.com.au> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 suprim
 */


return [
    '*' => [
        // Google Maps API key (used for map tiles and Places autocomplete)
        'mapToken' => '',

        // Google Geocoding API key (used for address lookup / reverse geocoding)
        'geoToken' => '',
    ],

    // Environment-specific overrides
    'dev' => [
        'mapToken' => '',
        'geoToken' => '',
    ],

    'staging' => [
        'mapToken' => '',
        'geoToken' => '',
    ],

    'production' => [
        'mapToken' => '',
        'geoToken' => '',
    ],
];
