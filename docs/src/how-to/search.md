# Location Search   

Being able to search your maps by location is one of the most important features of a map plugin. Here's how it works.

When building your [element query](https://craftcms.com/docs/5.x/development/element-queries.html) (in either Twig or PHP), you can pass an object to the map field to search by location. This object accepts the following properties:

| Option | Description |
|---|---|
| `location` | An address string, Geo Maps field value, or `{ lat: 0, lng: 0 }` object to search by |
| `coordinate` | A `{ lat: 0, lng: 0 }` object (used instead of `location`) |
| `country` | An optional country to restrict the address string to |
| `radius` | The radius around the location to get results from. *Defaults to `50`.* |
| `unit` | Distance unit: `mi` (miles) or `km` (kilometres). *Defaults to `km`.* |

## By Address

To find entries within a certain distance of an address (full or partial, such as a town or city name):

```twig
{% set entries = craft.entries.section('location').fieldHandle({
    location: 'Maidstone, Kent',
    radius: 10,
    unit: 'mi',
}).all() %}
```

This will find all locations within 10 miles of Maidstone, Kent.

## By Coordinates

You can also search using a set of coordinates by passing them to the `location` parameter:

```twig
{% set entries = craft.entries.section('location').fieldHandle({
    location: { lat: 51.272154, lng: 0.514951 },
}).all() %}
```

When other options are omitted they fall back to their defaults. In this case, we're searching for all locations within 50 kilometres of the given coordinates.

## Sorting by Distance

Results are ordered nearest-first by default. To order explicitly, use `distance`:

```twig
{% set entries = craft.entries.section('location').fieldHandle({
    location: 'Maidstone, Kent',
    radius: 100,
    unit: 'mi',
}).orderBy('distance').all() %}
```

Each result's Map field will have a `distance` property containing the distance from the searched location in the specified unit.

## Entries With or Without a Location

```twig
{% set withLocation = craft.entries.fieldHandle(':notempty:').all() %}
{% set withoutLocation = craft.entries.fieldHandle(':empty:').all() %}
```

## Rebuilding the Search Index

Coordinates are indexed when an entry is saved. To rebuild the index (for example after importing data directly into the database), run:

```bash
php craft geo/lookup/rebuild
```
