# Configuration

Configure **Geo Maps** in the control panel under **Settings → Plugins → Geo Maps**, or with a `config/geomaps.php` file.

![The Geo Maps settings: status tiles and the Map service choice (OpenStreetMap, Mapbox or Google Maps) with the chosen service’s fields](/images/plugin-settings.jpg)

## Map Service

Choose one **Map service**: **OpenStreetMap** (no key), **Mapbox** (an access token) or **Google Maps** (two keys). Only the chosen service’s fields are shown. Everything follows it: the map in the field, maps on your site (`embed()` and the [locator component](/guides/locator)), address suggestions, location searches in templates, and static map images.

| | OpenStreetMap | Mapbox | Google Maps |
|---|---|---|---|
| Maps | Leaflet with OpenStreetMap tiles, or another tile provider | Leaflet with your Mapbox style | Google Maps |
| Address suggestions in the field | [Photon](https://photon.komoot.io) | Mapbox | Google Places |
| Finding the address when the pin is dragged | [Nominatim](https://nominatim.org) | Mapbox | Google Geocoding (browser key) |
| Suggestions in [`addressInput()`](/examples/address-autocomplete) and the locator | Photon | Mapbox | Photon |
| Location searches, such as `location: 'Glenelg SA'` | Nominatim | Mapbox | Google Geocoding (server key) |
| [Missing coordinates](/guides/missing-coordinates) (Pro) | Nominatim | Mapbox | Nominatim |
| Static images (`img()`) | — | Mapbox Static Images | Google Maps Static API |

If Mapbox or Google refuses a lookup, or Mapbox finds nothing, Geo Maps uses OpenStreetMap instead. If Google rejects the browser key, maps switch to Leaflet, so nothing breaks while you fix it.

### OpenStreetMap

Nothing to set up. This is the default.

::: warning OpenStreetMap tiles on busy sites
The free `tile.openstreetmap.org` tiles are for light use, such as the control panel. For public-facing maps on busy sites, choose [Mapbox](#mapbox), or open **Use another tile provider** and set **Map tiles URL** to a provider such as [Stadia Maps](https://stadiamaps.com), [MapTiler](https://www.maptiler.com) or [Thunderforest](https://www.thunderforest.com), and **Map credit** to the credit it requires. `{z}`, `{x}` and `{y}` are filled in for each square of the map, and `${VAR}` reads an environment variable inside the URL:

```php
'tileUrl' => 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png?api_key=${STADIA_KEY}',
'tileAttribution' => '&copy; Stadia Maps &copy; OpenMapTiles &copy; OpenStreetMap contributors',
```
:::

### Mapbox

- **Access token** — a public token (starting `pk.`) from your [Mapbox account](https://account.mapbox.com/access-tokens/), restricted to your site’s domains.
- **Style** — click **Streets**, **Light**, **Dark** or **Satellite**, or paste a style URL from Mapbox Studio (`mapbox://styles/…`). The preview shows the style.

Geo Maps builds the tiles URL and adds the Mapbox credit itself. Addresses that are saved use Mapbox permanent geocoding, which needs a card on your Mapbox account. See [Mapbox Maps](/examples/mapbox).

### Google Maps

- **Browser key** — enable the **Maps JavaScript API**, **Places API**, **Maps Static API** and **Geocoding API** (the Geocoding API finds the address when the pin is dragged in the field). It’s visible in the page, so restrict it by HTTP referrer, including your local development domain (e.g. `*.ddev.site/*`).
- **Server key** — enable the **Geocoding API**. Craft uses it for location searches; it’s never sent to browsers, so restrict it by your server’s IP address. Without it, location searches use OpenStreetMap.

::: warning Google results only on Google maps
Google’s terms say you “must not use Google Maps Content from the Geocoding API in conjunction with a non-Google map”, and the same applies to the Places API. With the Google Maps service, Google results are shown on Google maps. If you show other maps on the front end (see [Advanced: mixing services](#advanced-mixing-services)), location searches use OpenStreetMap instead.
:::

### Sites set up before 1.4

Until you choose a service and save, Geo Maps uses Google Maps when a browser key is set, Mapbox when the tiles URL was a Mapbox style URL and a Mapbox token is set, and otherwise OpenStreetMap, keeping any custom tiles URL.

## Config File

Create `config/geomaps.php` to set values per environment. Settings in the file can’t be changed on the settings page. Values can reference environment variables. Uncomment the lines you need:

```php
<?php

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
```

`mapProvider`, `cpMapProvider`, `mapboxPermanent` and the Pro setting `geocodeMissing` are only set in the config file.

## Advanced: Mixing Services

With the **Google Maps** service, you can show other map tiles instead of Google maps on the front end or in the control panel:

```php
return [
    'service' => 'google',
    'mapboxToken' => '$MAPBOX_TOKEN',
    'mapProvider' => 'tiles',     // front end: Mapbox instead of Google maps
    'cpMapProvider' => 'auto',    // control panel: Google Maps
];
```

`'tiles'` shows Mapbox (in the `mapboxStyle` style) when a Mapbox token is set, and otherwise the tiles URL (OpenStreetMap by default). Things that go with the map follow it, so each service’s terms are met:

- `mapProvider` set to `'tiles'`: location searches use OpenStreetMap, and static images use Mapbox (with a Mapbox token, otherwise `img()` returns `null`).
- `cpMapProvider` set to `'tiles'`: suggestions in the field come from Photon, and the dragged pin’s address from Nominatim.

These settings have no effect with the OpenStreetMap or Mapbox service.

For a single map or image, choose with `provider`:

- `embed({ provider: 'google' })` shows a Google map whenever a Google browser key is set, and `embed({ provider: 'tiles' })` shows the map tiles: your Mapbox style when a Mapbox token is set with the Mapbox or Google Maps service, otherwise OpenStreetMap or your tiles URL. See [Maps](/guides/maps#choosing-google-or-map-tiles-for-one-map).
- `img({ provider: 'google' })` or `img({ provider: 'mapbox' })` uses that service’s key or token. See [Static map images](/guides/maps#static-map-images).

## Map Services’ Terms

Geo Maps talks to third-party map services on your behalf. Their terms apply to your site:

- **OpenStreetMap** data must be credited. Geo Maps shows the credit on every map; keep it visible if you restyle maps. The free `tile.openstreetmap.org` tiles and the Nominatim geocoder are for light use only — see the [tile](https://operations.osmfoundation.org/policies/tiles/) and [Nominatim](https://operations.osmfoundation.org/policies/nominatim/) usage policies.
- **Mapbox** requires its logo and credit on maps that use its tiles, and only allows storing results from permanent geocoding. See [Mapbox Maps](/examples/mapbox).
- **Google Maps Platform** content (Places search and geocoding results) is meant to be shown on Google maps, and Google limits how long some of it may be stored. See the [Google Maps Platform terms](https://cloud.google.com/maps-platform/terms).
