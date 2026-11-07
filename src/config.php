<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */


return [
    '*' => [
        // Google Maps API key (used for map tiles and Places autocomplete)
        'mapToken' => '',

        // Google Geocoding API key (used for address lookup / reverse geocoding)
        'geoToken' => '',

        // Map tiles used when there's no Google key (or Google rejects it), and their credit.
        // Busy public sites should use a tile provider instead of tile.openstreetmap.org.
        // 'tileUrl' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        // For Mapbox styles, put {mapboxToken} in the tiles URL and set the token here:
        // 'mapboxToken' => '$MAPBOX_TOKEN',

        // Address lookups: 'auto' (Google with a server key, otherwise OpenStreetMap), 'nominatim', 'google' or 'mapbox'.
        // 'geocoder' => 'auto',
        // Mapbox only allows saving results from permanent geocoding (needs a card on your Mapbox account).
        // 'mapboxPermanent' => false,
        // 'tileAttribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',

        // Pro: look up coordinates in the background for locations saved with only an address.
        // 'geocodeMissing' => true,
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
