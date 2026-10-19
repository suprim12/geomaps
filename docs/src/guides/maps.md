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

Maps use Google Maps when a **Google Maps API Key** is set, and Leaflet with your [tile provider](/getting-started/configuration) otherwise. If Google rejects the key, the map falls back to Leaflet automatically.

| Option | Description | Default |
|---|---|---|
| `center` | `{ lat, lng }`, `[lat, lng]` or an address | the field value |
| `zoom` | Zoom level | `14` |
| `width`, `height` | Size in pixels | `640` × `400` |
| `markers` | List of `{ location, label, color, icon, html, open }`. `location` is `{ lat, lng }` or an address. See [Custom Pins & Info Windows](/examples/custom-pins) | one marker at the centre |
| `icon` | Pin image for every marker: a URL or `{ url, width, height }` | round coloured pin |
| `html` | Info window HTML for the centre pin (when there are no `markers`) | — |
| `id` | The map element’s `id` | random |
| `geoJson` | Shapes to draw: GeoJSON as an array or string | the field’s shapes |
| `marker` | Show a pin at the centre when no `markers` are given | `true` (the field’s **Show Location Pin** setting) |
| `fitShapes` | Zoom to fit the shapes | `true` |
| `options` | Extra options passed to the map library (Leaflet or Google) | `{}` |

## Static map images

Static images come from the Google Maps Static API, so they need a **Google Maps API Key** with that API enabled. Without one, the methods return `null`.

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
| `width`, `height` | Size in pixels | `640` × `400` |
| `mapType` | `roadmap`, `satellite`, `hybrid` or `terrain` | `roadmap` |
| `mapId` | A Google Cloud map style ID | — |
| `format` | `png`, `jpg`, … | `png` |
| `markers` | List of `{ location, label, color }`. Labels show their first character | one marker at the centre |

`imgSrcSet()` returns `1x` and `2x` URLs for high-density screens.

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
