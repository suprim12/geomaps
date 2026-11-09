# Location Search

Pass an object to the field’s query parameter to find entries near a place. Results are ordered nearest first and each one gets a `distance`.

```twig
{% set stores = craft.entries()
    .section('stores')
    .storeLocation({
        location: 'Glenelg, South Australia',
        radius: 15,
        unit: 'km',
    })
    .all() %}

{% for store in stores %}
    {{ store.title }} — {{ store.storeLocation.distance|number_format(1) }} km
{% endfor %}
```

## Options

| Option | Description | Default |
|---|---|---|
| `location` | Where to search from: an address or place name, a `{ lat, lng }` object, or another Geo Maps value | — |
| `coordinate` | A `{ lat, lng }` object (use instead of `location`) | — |
| `radius` | How far to search | `50` |
| `unit` | `km` or `mi` | `km` |
| `country` | Two-letter code to narrow address lookups, e.g. `AU` | — |
| `contains` | An address, `{ lat, lng }` or Geo Maps value: find entries whose areas contain it (see [below](#find-the-area-containing-a-place)). Use instead of `location` | — |
| `within` | Pro: a map area or a drawn shape: find entries whose location is inside it (see [below](#search-within-an-area)) | — |

## Searching from coordinates

```twig
.storeLocation({ location: { lat: -34.9285, lng: 138.6007 }, radius: 5 })
```

Coordinates don’t need geocoding, so they’re the fastest option — useful with the browser’s geolocation API. Searches work anywhere on the globe, including across the 180° meridian (e.g. Fiji) and near the poles.

## Searching from another entry

```twig
{% set nearby = craft.entries()
    .section('stores')
    .storeLocation({ location: entry.storeLocation, radius: 20 })
    .id(['not', entry.id])
    .all() %}
```

## Sorting

Results are nearest first unless you set another order. To sort explicitly:

```twig
.orderBy('distance desc')   {# farthest first #}
.orderBy('title')           {# alphabetical; distance is still set #}
```

## Find the area containing a place

Use `contains` to find entries whose drawn **areas** (polygons) contain an address or point — for example, which bin-collection zone, delivery area or sales territory a customer is in.

```twig
{% set zone = craft.entries()
    .section('binZones')
    .zoneArea({ contains: '175 The Parade, Norwood SA 5067', country: 'AU' })
    .one() %}

{% if zone %}
    Your collection day is {{ zone.collectionDay }}.
{% else %}
    That address isn’t in a collection zone.
{% endif %}
```

Here `collectionDay` is an ordinary field on the zone entry. To read a value stored on the shape itself, see [Bin Collection Zones](/examples/zone-lookup#storing-data-on-the-shape-instead).

`contains` accepts an address, a `{ lat, lng }` object or another Geo Maps value. Holes in polygons are respected (a park cut out of a zone isn’t part of it), and a point exactly on the border between two zones belongs to only one of them. If areas overlap, every matching entry is returned.

Only polygons and multipolygons count as areas; markers and lines are ignored. To check a single entry instead, use [`contains()`](/guides/templating#methods) on its value.

::: tip
Turn off **Show Location Pin** on fields that only hold areas — see [The Field](/guides/field#field-settings).
:::

## Search within an area <Badge type="tip" text="Pro" />

Find entries whose location is inside a map area, such as the part of a map a visitor is looking at, or inside a drawn shape such as a delivery zone or council area.

```twig
{# The visible part of a map: north, south, east and west edges #}
{% set stores = craft.entries()
    .section('stores')
    .storeLocation({ within: { north: -34.85, south: -35.05, east: 138.70, west: 138.45 } })
    .all() %}

{# Inside the shapes drawn on another entry #}
{% set region = craft.entries().section('regions').slug('eastern-suburbs').one() %}
{% set stores = craft.entries()
    .section('stores')
    .storeLocation({ within: region.regionArea })
    .all() %}
```

`within` takes:

- **A map area:** `{ north, south, east, west }`, `[[south, west], [north, east]]`, or the text `'south,west,north,east'`, which is handy in a URL. An area across the 180° line (west edge east of the east edge) works too.
- **A shape:** a Geo Maps value with drawn areas, or GeoJSON as an array or text. Holes in shapes are respected.

Add `location` to sort the results by distance from a point, nearest first, with `distance` set on each. With an area, `radius` is optional: without it, every entry in the area is returned.

```twig
{% set stores = craft.entries()
    .section('stores')
    .storeLocation({
        within: craft.app.request.getQueryParam('bounds'),
        location: { lat: -34.93, lng: 138.60 },
    })
    .all() %}
```

On the free edition, `within` is ignored and Craft logs a warning.

## With or without a location

```twig
{% set mapped = craft.entries().section('stores').storeLocation(':notempty:').all() %}
{% set missing = craft.entries().section('stores').storeLocation(':empty:').all() %}
```

An entry counts as having a value if it has a pin or at least one drawn area.

::: tip More examples
See [Store Locator](/examples/store-locator) and [Bin Collection Zones](/examples/zone-lookup) for complete templates.
:::

## How addresses are looked up

Address and place names are looked up with the [map service](/getting-started/configuration#map-service) chosen in **Settings → Plugins → Geo Maps**: OpenStreetMap (Nominatim), [Mapbox](/examples/mapbox#address-search-with-mapbox), or Google with the **Server key** (without it, OpenStreetMap is used). If Google or Mapbox refuses a request, OpenStreetMap is used. Results are cached for 30 days (Mapbox results only with permanent geocoding, the default). If an address can’t be found, the search returns no results.

::: tip
Coordinates are indexed when entries are saved. After importing content directly into the database, run `php craft geomaps/lookup/rebuild` — see [Console Commands](/guides/console).
:::
