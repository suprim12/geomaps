# Templating

A Geo Maps field returns a location value (or `null` when nothing is set).

```twig
{% set place = entry.storeLocation %}

{% if place %}
    <p>{{ place.full_address }}</p>
    <p>{{ place.lat }}, {{ place.lng }}</p>
{% endif %}
```

## Properties

| Property | Description |
|---|---|
| `lat`, `lng` | Coordinates (floats) |
| `zoom` | Zoom level chosen in the control panel |
| `full_address` | The complete formatted address |
| `address1` | Street address, e.g. `1 King William St` |
| `suburb` | Suburb, town or city |
| `postcode` | Postcode as text, so leading zeros (`0800`) and letters (`SW1A 1AA`) are kept |
| `country` | Country name |
| `countryCode` | Two-letter country code, e.g. `AU` |
| `geoJson` | Drawn shapes as a GeoJSON string |
| `distance` | Distance from the searched location — only set on [location search](/guides/search) results |

Outputting the value directly prints the full address (or the coordinates if there’s no address):

```twig
{{ entry.storeLocation }}
```

## Methods

| Method | Returns |
|---|---|
| `hasCoordinates()` | Whether both `lat` and `lng` are set |
| `getGeoJsonData()` | The GeoJSON decoded to an array, or `null` |
| `contains(place)` | Whether the value’s areas (polygons) contain an address, `{ lat, lng }` or another value |
| `embed(options)` | An interactive map centred on this location — see [Maps](/guides/maps) |
| `img(options)` / `imgSrcSet(options)` | A static map image URL — see [Maps](/guides/maps) |

### Working with shapes

```twig
{% set shapes = entry.storeLocation.getGeoJsonData() %}
{% for feature in shapes.features ?? [] %}
    {{ feature.properties.name ?? 'Unnamed' }} ({{ feature.geometry.type }})
{% endfor %}
```

## Checking for a location

```twig
{% if entry.storeLocation and entry.storeLocation.hasCoordinates() %}
    …
{% endif %}
```

To query entries that do or don’t have a location, see [Location Search](/guides/search#with-or-without-a-location).
