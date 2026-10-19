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

## Searching from coordinates

```twig
.storeLocation({ location: { lat: -34.9285, lng: 138.6007 }, radius: 5 })
```

Coordinates don’t need geocoding, so they’re the fastest option — useful with the browser’s geolocation API.

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

## With or without a location

```twig
{% set mapped = craft.entries().section('stores').storeLocation(':notempty:').all() %}
{% set missing = craft.entries().section('stores').storeLocation(':empty:').all() %}
```

## How addresses are looked up

Address and place names are geocoded with Google when a **Google Geocoding API Key** is set, otherwise with OpenStreetMap (Nominatim). Results are cached for 30 days. If an address can’t be found, the search returns no results.

::: tip
Coordinates are indexed when entries are saved. After importing content directly into the database, run `php craft geo/lookup/rebuild` — see [Console Commands](/guides/console).
:::
