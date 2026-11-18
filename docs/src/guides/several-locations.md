# Several Locations <Badge type="tip" text="Pro" />

One field can hold more than one location, such as a business with several branches, a franchise or an event at several venues. Distance search then finds the entry by its nearest location, and tells you which one it was.

## Turning it on

In the field’s settings, turn on **Allow Several Locations**. The field gets a list of its locations beside the map: pick one to edit it with the search, map and address fields as usual, **Add location** for another, and × to remove one. The other locations show as grey pins on the map; click one to edit it. Each location can have a **Name**, such as the branch name.

## In templates

The value works as before for the first location, so templates written for one location keep working. `locations` lists them all:

```twig
{% for branch in entry.storeLocation.locations %}
  <h3>{{ branch.label ?? entry.title }}</h3>
  <p>{{ branch.address }}</p>
{% endfor %}
```

`embed()` shows a pin for each location and zooms to show them all, and `img()` adds a pin for each.

## Searching

[Location search](/guides/search) finds each entry once, by its nearest location, and sorts by that distance. `nearest` is the location that matched:

```twig
{% set stores = craft.entries
  .section('stores')
  .storeLocation({ location: 'Glenelg SA', radius: 20 })
  .all() %}

{% for store in stores %}
  {% set branch = store.storeLocation.nearest %}
  {{ store.title }}, {{ branch.label }}: {{ branch.address }} ({{ store.storeLocation.distance|number_format(1) }} km)
{% endfor %}
```

`within` finds entries with any location inside the area, and [the locator](/guides/locator) and [search endpoints](/guides/search-endpoint) show the nearest branch’s address, pin and directions.

## Elsewhere

- **GraphQL:** `locations`, `nearest` and `label`. See [GraphQL](/guides/graphql).
- **Import and export:** CSV has `location_label` and `locations` columns, and GeoJSON a `locations` property, so exporting and importing keeps every location. See [Import and Export](/guides/import-export).
- **Missing coordinates:** each location with only an address gets coordinates. See [Missing Coordinates](/guides/missing-coordinates).
- **Feed Me** sets the first location.
- **Structured data** (`schema()`) describes the first location.

## Stored value

The first location is saved in the field’s usual keys, and the others in a `locations` list of the same shape, without shapes or zoom:

```json
{
  "lat": -37.8136, "lng": 144.9631, "full_address": "Head office, Melbourne", "label": "Head office",
  "locations": [
    { "lat": -34.9802, "lng": 138.5113, "full_address": "16 Jetty Rd, Glenelg SA", "label": "Glenelg" }
  ]
}
```

Values with one location are saved exactly as before. Turning the setting off keeps the saved locations; they stay searchable and editable again when it’s turned back on.
