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
        // The map service for maps, address search, lookups and static images:
        // 'openstreetmap' (free, no key), 'mapbox' or 'google'.
        // 'service' => 'openstreetmap',

        // Mapbox: an access token, and a style ('mapbox/streets-v12', 'mapbox/light-v11',
        // 'mapbox/dark-v11', 'mapbox/satellite-streets-v12', or your own 'mapbox://styles/…').
        // 'mapboxToken' => '$MAPBOX_TOKEN',
        // 'mapboxStyle' => 'mapbox/streets-v12',

        // Google: a browser key (maps, Places search, static images) and a server key (lookups).
        // 'mapToken' => '$GOOGLE_MAPS_KEY',
        // 'geoToken' => '$GOOGLE_GEOCODING_KEY',

        // OpenStreetMap: busy public sites should use a tile provider instead of tile.openstreetmap.org.
        // 'tileUrl' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        // 'tileAttribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',

        // Advanced, with the Google service: show the map tiles (Mapbox, with a Mapbox token) instead
        // of Google maps on the front end or in the control panel: 'auto' or 'tiles'.
        // 'mapProvider' => 'auto',      // front end: embed() and the locator component
        // 'cpMapProvider' => 'auto',    // control panel: the map in the field

        // Advanced, with Mapbox: saved addresses use Mapbox permanent geocoding (billed at Mapbox's
        // permanent rate). Set to false to save addresses from OpenStreetMap instead.
        // 'mapboxPermanent' => true,

        // Pro: look up coordinates in the background for locations saved with only an address.
        // 'geocodeMissing' => true,
    ],

    // Environment-specific settings, for example OpenStreetMap locally and Mapbox in production:
    // 'dev' => [
    //     'service' => 'openstreetmap',
    // ],
    // 'production' => [
    //     'service' => 'mapbox',
    // ],
];
