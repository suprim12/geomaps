# Maps

## Interactive maps

Render an interactive map centred on a location:

```twig
{{ entry.storeLocation.embed({ height: 360 }) }}
```

The field’s shapes are drawn too, and the map zooms to fit them. Fields with **Show Location Pin** turned off (zones, areas) show just their shapes — handy for showing a collection zone or delivery area.

Or build one from scratch with any number of markers:

```twig
{{ craft.geomaps.embed({
    id: 'store-map',
    center: { lat: -34.9285, lng: 138.6007 },
    zoom: 11,
    width: 800,
    height: 450,
    markers: stores|map(store => {
        location: { lat: store.storeLocation.lat, lng: store.storeLocation.lng },
        label: store.title,
        color: '#2563eb',
    }),
}) }}
```

For a map that fills its container, use a CSS width:

```twig
{{ entry.storeLocation.embed({ width: '100%', height: 360 }) }}
```

Shapes are coloured from their `color`, `stroke` and `fill` properties, lines are drawn 4px wide (set a `weight` property to change any shape’s line width), and a shape with a `name` or `title` shows it when clicked — see [Map of Areas](/examples/area-map#styling-shapes).

Maps use Google Maps when a **Google Maps API Key** is set, and Leaflet with your [map tiles](/getting-started/configuration) (OpenStreetMap, [Mapbox](/examples/mapbox) or another provider) otherwise. To choose differently for the front end and the control panel, see [Map Providers](/getting-started/configuration#map-providers). If Google rejects the key, the map falls back to Leaflet automatically.

| Option | Description | Default |
|---|---|---|
| `center` | `{ lat, lng }`, `[lat, lng]` or an address | the field value (its address, if it has no coordinates) |
| `zoom` | Zoom level | the field value’s zoom, or `14` |
| `width`, `height` | Size in pixels (`800`), or a CSS length such as `'100%'` or `'60vh'` | `640` × `400` |
| `markers` | List of `{ location, label, color, icon, html, open }`. `location` is `{ lat, lng }` or an address. See [Custom Pins & Info Windows](/examples/custom-pins) | one marker at the centre |
| `icon` | Pin image for every marker: a URL or `{ url, width, height }` | round coloured pin |
| `html` | Info window HTML for the centre pin (when there are no `markers`) | — |
| `id` | The map element’s `id` | random |
| `geoJson` | Shapes to draw: GeoJSON as an array or string | the field’s shapes |
| `marker` | Show a pin at the centre when no `markers` are given | `true` (the field’s **Show Location Pin** setting) |
| `fitShapes` | Zoom to fit the shapes | `true` |
| `fitMarkers` | Zoom to fit the markers | `false` |
| `provider` | `'google'` or `'tiles'` to choose the map for this map | Google with a browser key, otherwise the map tiles |
| `options` | Extra options passed to the map library (Leaflet or Google) | `{}` |
| `cluster` | Group nearby markers into numbered bubbles: `true` or `{ color, radius, maxZoom }`. **Pro.** See [Marker clustering](#marker-clustering) | `false` |

### Choosing Google or map tiles for one map

Maps use Google when a Google browser key is set, and the [map tiles](/getting-started/configuration#map-providers) otherwise. Use `provider` to choose for a single map:

```twig
{# Always Google Maps (needs the browser key; falls back to the map tiles without one) #}
{{ entry.storeLocation.embed({ provider: 'google', height: 360 }) }}

{# Always the map tiles: OpenStreetMap, Mapbox or your tile provider, even with a Google key #}
{{ craft.geomaps.embed({
    provider: 'tiles',
    center: 'Adelaide, South Australia',
    zoom: 12,
    height: 400,
}) }}
```

To change the default for every map on your site, or in the control panel, set `mapProvider` or `cpMapProvider` in [`config/geomaps.php`](/getting-started/configuration#map-providers).

Static images work the same way with `img({ provider: 'google' })` or `img({ provider: 'mapbox' })`; see [Static map images](#static-map-images).

### Marker clustering <Badge type="tip" text="Pro" /> {#marker-clustering}

Maps with dozens or hundreds of markers get crowded. Turn on `cluster` and nearby markers group into a numbered bubble, which splits apart as visitors zoom in or click it:

![A map of Adelaide with hundreds of markers grouped into purple numbered bubbles](/images/marker-clustering.jpg)

```twig
{{ craft.geomaps.embed({
    center: 'Adelaide, South Australia',
    zoom: 10,
    width: '100%',
    height: 500,
    markers: stores|map(store => {
        location: { lat: store.storeLocation.lat, lng: store.storeLocation.lng },
        label: store.title,
    }),
    cluster: true,
}) }}
```

Pass options instead of `true` to change how it looks and behaves:

| Option | Description | Default |
|---|---|---|
| `color` | Bubble colour, as a hex colour such as `'#0f766e'` | `'#4f46e5'` |
| `radius` | How close markers must be to group, in pixels (10–200) | `60` |
| `maxZoom` | Stop grouping from this zoom level, showing every marker | groups at every zoom |

```twig
cluster: { color: '#0f766e', radius: 40, maxZoom: 15 },
```

Clustering works with both OpenStreetMap and Google maps, and the clustering script only loads on maps that use it. Markers with `open: true` are revealed and their info window opened. To restyle the bubbles on OpenStreetMap maps, target `.geomaps-cluster span` in your CSS.

Clustering is part of [Geo Maps Pro](/getting-started/pro). On the free edition, the `cluster` option is ignored and every marker is shown.

### Working with the map in JavaScript

Each map element gets a small `geomaps` object once it’s drawn, and fires a `geomaps:ready` event. Use it to open a marker’s info window from your own page, such as a list of stores beside the map:

```js
document.addEventListener('geomaps:ready', (e) => {
  const map = e.detail;      // also available as element.geomaps
  map.open(0);               // open the first marker (in the order of `markers`)
  map.map;                   // the Leaflet or Google map, for anything else
});
```

### Styling the map

Embedded maps are ordinary Leaflet (or Google) maps, so you can style them with CSS. To make OpenStreetMap tiles calmer, so your pins and shapes stand out:

```css
[data-geo-embed] { border-radius: 14px; overflow: hidden; }
[data-geo-embed] .leaflet-tile-pane { filter: saturate(0.35) contrast(0.92) brightness(1.06); }
```

For a different map style altogether, set **Map Tiles URL** to another [tile provider](/getting-started/configuration#map-providers).

## Static map images

Static images come from Google (the Maps Static API) when your front-end maps are Google maps, and otherwise from Mapbox (the Static Images API) when a **Mapbox access token** is set. Mapbox images use your Mapbox style and include the Mapbox logo and credit. Without either key, the methods return `null`.

Choose the service for one image with `provider: 'google'` or `provider: 'mapbox'`.

```twig
{% set src = entry.storeLocation.img({ width: 600, height: 300, zoom: 13 }) %}
{% if src %}
    <img src="{{ src }}"
         srcset="{{ entry.storeLocation.imgSrcSet({ width: 600, height: 300, zoom: 13 }) }}"
         width="600" height="300" alt="Map of {{ entry.title }}">
{% endif %}
```

Without a field value, use `craft.geomaps.img()` / `craft.geomaps.imgSrcSet()` with a `center` or `markers`.

| Option | Description | Default |
|---|---|---|
| `center` | `{ lat, lng }`, `[lat, lng]` or an address | the field value, or the first marker |
| `zoom` | Zoom level | `14` |
| `width`, `height` | Size in pixels. Google allows up to 640 and Mapbox up to 1280; larger sizes are scaled down, keeping their shape | `640` × `400` |
| `provider` | `'google'` or `'mapbox'` | Google with Google maps, otherwise Mapbox |
| `mapType` | Google only: `roadmap`, `satellite`, `hybrid` or `terrain` | `roadmap` |
| `mapId` | Google only: a Google Cloud map style ID | — |
| `format` | Google only: `png`, `jpg`, … | `png` |
| `markers` | List of `{ location, label, color }`. Labels show their first character | one marker at the centre |

`imgSrcSet()` returns `1x` and `2x` URLs for high-density screens. Options apply to that one image, so you can call `img()` several times with different sizes.

## Drawing shapes on your own maps

`getGeoJsonData()` returns the field’s shapes ready to hand to a map library. With Leaflet (load it yourself, or render an `embed()` on the same page, which loads it):

```twig
<div id="area-map" style="height: 400px"></div>
<script>
  const map = L.map('area-map');
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map);
  const shapes = L.geoJSON({{ entry.storeLocation.getGeoJsonData()|json_encode|raw }}).addTo(map);
  map.fitBounds(shapes.getBounds());
</script>
```
