# Mapbox Maps

Use [Mapbox](https://www.mapbox.com) map styles (Streets, Light, Dark, Satellite, or your own style from Mapbox Studio) for `embed()` maps, the [store locator](/guides/store-locator) and the map in the control panel. It works on the free edition, with no code.

Mapbox tiles are used when there’s no Google key. Address search and geocoding keep using OpenStreetMap.

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
3. Save. The **Maps** status at the top of the page shows **Mapbox**, and the preview shows the new tiles.

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

This covers every `embed()` map, including the store locator’s.

## Good to know

- **Cost:** Mapbox bills by tile requests, with a free monthly allowance. Check [Mapbox pricing](https://www.mapbox.com/pricing) for your traffic.
- **Privacy:** visitors’ browsers load the tiles from Mapbox, which receives their IP address. Mention Mapbox in your privacy policy; see [Privacy and Data](/getting-started/privacy-and-data).
- **Google keys win:** if a Google browser key is set, maps use Google instead. Remove it to use Mapbox.
- **Check the preview:** after saving, the **Map tiles** preview on the settings page shows the Mapbox tiles. A grey preview saying the tiles didn’t load usually means the token is missing, mistyped, or restricted to other domains.
- **Later, Mapbox address search:** the same token will be used by future Mapbox features, so set it in **Mapbox access token** rather than writing it into the URL.
