# Maps

## Interactive maps

Render an interactive map centred on a location:

```twig
{{ entry.storeLocation.embed({ height: 360 }) }}
```

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
| `markers` | List of `{ location, label, color }`. `location` is `{ lat, lng }` or an address | one marker at the centre |
| `id` | The map element’s `id` | random |
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
