# Configuration

Configure **Geo Maps** in the control panel under **Settings → Geo Maps**, or with a `config/geo.php` file.

## Map Providers

Geo Maps works without any API keys. Add a Google key when you want Google’s map tiles, Places search or static map images.

| Feature | Without a Google key | With a Google key |
|---|---|---|
| Map tiles (control panel and `embed()`) | OpenStreetMap, or any tile URL you set | Google Maps |
| Address search in the field | OpenStreetMap ([Photon](https://photon.komoot.io)) | Google Places |
| Reverse geocoding (dragging the marker) | OpenStreetMap ([Nominatim](https://nominatim.org)) | Google Geocoding |
| Location search by address (`location: 'Adelaide'`) | OpenStreetMap (Nominatim, cached) | Google Geocoding (cached) |
| Static map images (`.img()`) | Not available | Google Static Maps |

::: tip Google Maps
Enable the **Maps JavaScript API** and **Places API** for the **Google Maps API Key**, and the **Geocoding API** and **Maps Static API** for the **Google Geocoding API Key**. Restrict browser keys by HTTP referrer, including your local development domain (e.g. `*.ddev.site/*`).
:::

::: warning OpenStreetMap tiles on busy sites
The default `tile.openstreetmap.org` tiles are free for light use, such as the control panel. For public-facing maps on busy sites, set **Tile URL** to a tile provider such as [Stadia Maps](https://stadiamaps.com), [MapTiler](https://www.maptiler.com) or [Thunderforest](https://www.thunderforest.com), and update **Tile Attribution** to match.
:::

## Config File

Create `config/geo.php` to set values per environment. Values can reference environment variables.

```php
<?php

return [
    'mapToken' => '$GOOGLE_MAPS_KEY',
    'geoToken' => '$GOOGLE_GEOCODING_KEY',
    'tileUrl' => 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png?api_key=$STADIA_KEY',
    'tileAttribution' => '&copy; Stadia Maps &copy; OpenMapTiles &copy; OpenStreetMap contributors',
];
```
