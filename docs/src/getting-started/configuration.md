# Configuration

Configure **Geo Maps** in the control panel under **Settings → Geo Maps**, or with a `config/geomaps.php` file.

![The Geo Maps plugin settings: optional Google keys, and the OpenStreetMap tiles URL and credit](/images/plugin-settings.jpg)

## Map Providers

Geo Maps works without any API keys. Choose services under **Settings → Plugins → Geo Maps** or in `config/geomaps.php`, and mix them as you like:

| Setting | Choices | Used for |
|---|---|---|
| `mapProvider` in `config/geomaps.php` | `'auto'`, `'google'` or `'tiles'` | `embed()` maps and the locator component |
| `cpMapProvider` in `config/geomaps.php` | `'auto'`, `'google'` or `'tiles'` | The map in the field |
| **Map tiles** | OpenStreetMap, [Mapbox](/examples/mapbox), or any tile URL | Every map that isn’t a Google map |
| **Address lookups → Geocoding service** | Automatic, OpenStreetMap, Google, or [Mapbox](/examples/mapbox#address-search-with-mapbox) | Location searches such as `location: 'Adelaide'`, front-end address suggestions, missing coordinates |

**Automatic** keeps things simple: Google maps when a Google browser key is set, and the map tiles otherwise. To use Google in the control panel but Mapbox on your site, or the other way round, set the map providers in `config/geomaps.php`:

```php
return [
    'mapProvider' => 'tiles',     // front end: map tiles (e.g. Mapbox)
    'cpMapProvider' => 'google',  // control panel: Google Maps
];
```

A single map can use a different one with `embed({ provider: 'google' })` or `provider: 'tiles'`.

Some things follow the map, so each service’s terms are met:

| Feature | On Google maps | On other maps |
|---|---|---|
| Address suggestions in the field | Google Places | OpenStreetMap ([Photon](https://photon.komoot.io)), or Mapbox with permanent geocoding |
| Dragging the pin (finding its address) | Google Geocoding | OpenStreetMap ([Nominatim](https://nominatim.org)), or Mapbox with permanent geocoding |
| Static map images (`.img()`) | Google Static Maps | Mapbox Static Images, with a Mapbox token |

::: warning Google results only on Google maps
Google’s terms say you “must not use Google Maps Content from the Geocoding API in conjunction with a non-Google map”, and the same applies to the Places API. If your maps aren’t Google maps, use OpenStreetMap or Mapbox for address lookups. The settings page shows a warning when Google lookups are combined with other maps. Using Google lookups only for distance search, with results shown in a list rather than on a map, doesn’t involve a map.
:::

::: tip Google Maps
- **Google Maps API Key (browser)** — enable the **Maps JavaScript API**, **Places API** and **Maps Static API**. Restrict it by HTTP referrer, including your local development domain (e.g. `*.ddev.site/*`).
- **Google Geocoding API Key (server)** — enable the **Geocoding API**. Restrict it by your server’s IP address.
:::

::: warning OpenStreetMap tiles on busy sites
The default `tile.openstreetmap.org` tiles are free for light use, such as the control panel. For public-facing maps on busy sites, set **Map Tiles URL** to a tile provider such as [Mapbox](/examples/mapbox), [Stadia Maps](https://stadiamaps.com), [MapTiler](https://www.maptiler.com) or [Thunderforest](https://www.thunderforest.com), and update **Map Credit** to match.
:::

::: tip Mapbox
The settings page has **Quick setup** buttons for Mapbox map styles, and Mapbox can also be chosen as the **Geocoding service** for address lookups. See [Mapbox Maps](/examples/mapbox).
:::

## Config File

Create `config/geomaps.php` to set values per environment. Values can reference environment variables.

```php
<?php

return [
    'mapToken' => '$GOOGLE_MAPS_KEY',
    'geoToken' => '$GOOGLE_GEOCODING_KEY',
    'tileUrl' => 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png?api_key=$STADIA_KEY',
    'tileAttribution' => '&copy; Stadia Maps &copy; OpenMapTiles &copy; OpenStreetMap contributors',
];
```

## Map Services’ Terms

Geo Maps talks to third-party map services on your behalf. Their terms apply to your site:

- **OpenStreetMap** data must be credited. Geo Maps shows the credit on every map; keep it visible if you restyle maps. The free `tile.openstreetmap.org` tiles and the Nominatim geocoder are for light use only — see the [tile](https://operations.osmfoundation.org/policies/tiles/) and [Nominatim](https://operations.osmfoundation.org/policies/nominatim/) usage policies.
- **Google Maps Platform** content (Places search and geocoding results) is meant to be shown on Google maps, and Google limits how long some of it may be stored. If you use Google for address search, use a Google key for your maps too. See the [Google Maps Platform terms](https://cloud.google.com/maps-platform/terms).
