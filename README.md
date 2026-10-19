<p>
<img src="src/icon.svg" width="60px"/>
</p>

# Geo Maps for Craft CMS

Pin an address, draw areas, and find the nearest entries, or the zone that contains an address. Works with OpenStreetMap out of the box, or with Google Maps when you add a key.

## Features

- **Fast location entry.** Address autocomplete, click-to-place and a draggable pin. Street, suburb, postcode, country and coordinates fill in automatically.
- **No API key required.** Uses OpenStreetMap by default. Add a Google key for Google tiles, Places search and static images; if Google rejects the key, maps fall back to OpenStreetMap.
- **Draw shapes.** Markers, lines, rectangles and polygons (with holes), with per-shape properties, a GeoJSON editor, and `.geojson` upload and download.
- **Distance search.** Find entries within a radius of an address, coordinates or another entry, sorted nearest first, with the distance on every result.
- **Containment search.** Find the delivery area, sales territory or bin-collection zone that contains an address.
- **Front-end maps.** Interactive Leaflet or Google maps with markers and shapes, and Google static map images, straight from Twig.
- **Flexible field settings.** Hide the pin, drawing, search, address or map; set zoom limits; limit search to up to 5 countries.
- **GraphQL and Feed Me.** Query, search and save locations in headless projects, and import locations from feeds.
- **One-click import.** Convert Maps (ether/simplemap) and Craft Geoman fields in place. Handles, layouts, content and most templates carry over.

## Requirements

Craft CMS 5.10.0 or later, and PHP 8.2 or later.

## Installation

Install from the Plugin Store (search for “Geo Maps”), or with Composer:

```bash
composer require suprim/geomaps
php craft plugin/install geomaps
```

Then add a **Geo Maps** field to a field layout. No configuration is needed; see [Configuration](https://github.com/suprim12/craft-geomaps/blob/main/docs/src/getting-started/configuration.md) to add Google keys or a different tile provider.

## Examples

The examples below use a `stores` section with a Geo Maps field called `storeLocation`.

### Show a location

```twig
{% set place = entry.storeLocation %}

{% if place %}
  <address>{{ place.address1 }}, {{ place.suburb }} {{ place.postcode }}</address>
  {{ place.embed({ width: 800, height: 360, zoom: 15 }) }}
{% endif %}
```

### Find the nearest stores

```twig
{% set stores = craft.entries()
  .section('stores')
  .storeLocation({ location: 'Glenelg, South Australia', radius: 25, unit: 'km' })
  .all() %}

{% for store in stores %}
  {{ store.title }} ({{ store.storeLocation.distance|number_format(1) }} km)
{% endfor %}
```

`location` also accepts `{ lat: …, lng: … }` or another entry’s location. Results are nearest first.

### Find the zone that contains an address

```twig
{% set zone = craft.entries()
  .section('binZones')
  .zoneArea({ contains: '175 The Parade, Norwood SA 5067', country: 'AU' })
  .one() %}

{{ zone ? "Your bins are collected on #{zone.collectionDay}." : 'Not in a collection zone.' }}
```

### A map with many markers

```twig
{{ craft.geomaps.embed({
  center: 'Adelaide, Australia',
  zoom: 11,
  width: 900,
  height: 450,
  markers: stores|map(s => {
    location: { lat: s.storeLocation.lat, lng: s.storeLocation.lng },
    label: s.title,
  }),
}) }}
```

### Static map image

Requires a Google key with the Maps Static API enabled.

```twig
{% set src = entry.storeLocation.img({ width: 600, height: 300 }) %}
{% if src %}<img src="{{ src }}" width="600" height="300" alt="Map">{% endif %}
```

### GraphQL

```graphql
{
  entries(section: "stores", storeLocation: { location: "Glenelg SA", radius: 25 }) {
    title
    ... on store_Entry {
      storeLocation { full_address lat lng distance }
    }
  }
}
```

Complete examples in the docs:

- [Store locator](https://github.com/suprim12/craft-geomaps/blob/main/docs/src/examples/store-locator.md), with “use my location” and pagination
- [Location page](https://github.com/suprim12/craft-geomaps/blob/main/docs/src/examples/location-page.md), with directions, nearby places and structured data
- [Bin collection zones](https://github.com/suprim12/craft-geomaps/blob/main/docs/src/examples/zone-lookup.md), including a GraphQL version that doesn't reload the page
- [Map of areas](https://github.com/suprim12/craft-geomaps/blob/main/docs/src/examples/area-map.md), with one colour per zone

## Switching from Maps or Geoman

Go to **Utilities → Geo Maps Import** and click **Import** on a field, or **Import all**. From the command line:

```bash
php craft geomaps/import                    # every field
php craft geomaps/import --field=storeMap   # one field
```

Templates written for Maps (`entry.map.address`, `.parts`, `.img()`, `.imgSrcSet()`) keep working.

## License

Geo Maps is licensed under the [Craft License](LICENSE.md). Bundled open-source libraries are listed in [THIRD-PARTY-LICENSES.md](THIRD-PARTY-LICENSES.md).
