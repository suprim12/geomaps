# Mapbox Maps

Use [Mapbox](https://www.mapbox.com) map styles (Streets, Light, Dark, Satellite, or your own style from Mapbox Studio) for `embed()` maps, the [locator component](/guides/locator) and the map in the control panel. It works on the free edition, with no code.

Mapbox tiles are used when there’s no Google browser key, or for maps set to `'tiles'` in [`config/geomaps.php`](/getting-started/configuration#map-providers), so you can use Google in the control panel and Mapbox on your site, or the other way round. Mapbox can also look up addresses (see [Address search with Mapbox](#address-search-with-mapbox)) and make [static map images](/guides/maps#static-map-images).

![Left: an interactive map of Adelaide stores on Mapbox Streets tiles. Right: a static Mapbox image of central Adelaide with a red pin](/examples/mapbox.jpg)

```twig
{# Interactive map on Mapbox tiles, even when a Google key is set #}
{{ craft.geomaps.embed({
  provider: 'tiles',
  markers: stores|map(s => { location: { lat: s.storeLocation.lat, lng: s.storeLocation.lng }, label: s.title }),
  fitMarkers: true,
  height: 380,
}) }}

{# Static image from Mapbox #}
<img alt="Map of Adelaide" src="{{ craft.geomaps.img({
  provider: 'mapbox',
  center: { lat: -34.9285, lng: 138.6007 },
  zoom: 13,
  width: 640,
  height: 380,
}) }}">
```

## 1. Create an access token

1. Sign in at [account.mapbox.com](https://account.mapbox.com) and open **Tokens**.
2. Create a **public** token (it starts with `pk.`) with the default public scopes.
3. Under **URL restrictions**, add your site’s domains, such as `https://example.com` and your local domain like `https://example.ddev.site`. The token is visible in the page source, so this stops other sites from using it.

Add it to your site’s `.env` file:

```bash
MAPBOX_TOKEN="pk.eyJ1Ijo…"
```

## 2. Choose a style

Go to **Settings → Plugins → Geo Maps** and, under **Map tiles**:

1. Click a **Quick setup** button: **Mapbox Streets**, **Light**, **Dark** or **Satellite**. It fills in the tiles URL and the credit Mapbox requires.
2. Enter `$MAPBOX_TOKEN` in **Mapbox access token**, so the token stays in `.env` rather than your project config.
3. Save. The preview shows the new tiles.

The tiles URL has a `{mapboxToken}` placeholder that Geo Maps fills in with the token:

```text
https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/256/{z}/{x}/{y}@2x?access_token={mapboxToken}
```

`256` keeps tiles the size Leaflet expects, and `@2x` makes them sharp on high-resolution screens.

### Your own Mapbox Studio style

Design a style in [Mapbox Studio](https://studio.mapbox.com), click **Share**, and copy the **Style URL** (`mapbox://styles/your-name/style-id`). Put your username and style ID into the tiles URL:

```text
https://api.mapbox.com/styles/v1/your-name/style-id/tiles/256/{z}/{x}/{y}@2x?access_token={mapboxToken}
```

### In the config file

To set it per environment, use `config/geomaps.php` instead:

```php
return [
    'mapboxToken' => '$MAPBOX_TOKEN',
    'tileUrl' => 'https://api.mapbox.com/styles/v1/mapbox/light-v11/tiles/256/{z}/{x}/{y}@2x?access_token={mapboxToken}',
    'tileAttribution' => '© <a href="https://www.mapbox.com/about/maps">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> <strong><a href="https://apps.mapbox.com/feedback/">Improve this map</a></strong>',
];
```

## 3. Show the Mapbox logo

Mapbox’s [attribution rules](https://docs.mapbox.com/help/getting-started/attribution/) require the Mapbox logo and the text credit on maps that use its tiles. **Quick setup** adds the text credit. For the logo, download it from the [Mapbox brand guidelines](https://www.mapbox.com/about/press/brand-guidelines), add it to your site (for example `web/images/mapbox-logo.svg`), and place it on your maps with CSS:

```css
[data-geo-embed] {
  position: relative;
}

[data-geo-embed]::after {
  content: '';
  position: absolute;
  left: 10px;
  bottom: 10px;
  z-index: 1000;
  width: 88px;
  height: 20px;
  background: url('/images/mapbox-logo.svg') no-repeat left bottom / contain;
  pointer-events: none;
}
```

This covers every `embed()` map, including the locator component’s.

## Address search with Mapbox

Mapbox can also turn addresses into coordinates, instead of OpenStreetMap or Google. Under **Address lookups**, set **Geocoding service** to **Mapbox**. It uses the same access token.

Mapbox’s terms only allow saving results from its **permanent** geocoding, which needs a card on your Mapbox account and is billed at a higher rate. Geo Maps follows this:

| | Permanent geocoding off | Permanent geocoding on |
|---|---|---|
| Location searches, such as `location: 'Glenelg SA'` | Mapbox (results aren’t cached) | Mapbox (cached for 30 days) |
| Suggestions in search boxes: [`addressInput()`](/examples/address-autocomplete) with `name`, and the [locator component](/guides/locator) | Mapbox | Mapbox |
| Suggestions that are saved: the field in the control panel, and `addressInput()` with `field` | OpenStreetMap or Google | Mapbox |
| [Missing coordinates](/guides/missing-coordinates) | OpenStreetMap | Mapbox, without the one-per-second limit |

If Mapbox refuses a request, for example because the token is restricted to other domains, or finds nothing, OpenStreetMap is used instead, and Geo Maps logs a warning.

## Good to know

- **Cost:** Mapbox bills by tile requests, with a free monthly allowance. Check [Mapbox pricing](https://www.mapbox.com/pricing) for your traffic.
- **Privacy:** visitors’ browsers load the tiles from Mapbox, which receives their IP address. Mention Mapbox in your privacy policy; see [Privacy and Data](/getting-started/privacy-and-data).
- **With a Google key too:** set `mapProvider` (front end) or `cpMapProvider` (control panel) to `'tiles'` in `config/geomaps.php` where you want Mapbox. Without it, Google is used when a browser key is set.
- **Check the preview:** after saving, the **Map tiles** preview on the settings page shows the Mapbox tiles. A grey preview saying the tiles didn’t load usually means the token is missing, mistyped, or restricted to other domains.
- **One token:** map tiles, address search and static images all use the token in **Mapbox access token**, so set it there rather than writing it into the URL.
