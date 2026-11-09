# Geo Maps Pro

Geo Maps is free, and everything in the free edition stays free. **Geo Maps Pro** adds extra features for bigger sites.

## Pro features

| Feature | What it does |
|---|---|
| [Marker clustering](/guides/maps#marker-clustering) | Groups nearby pins on `embed()` maps into numbered bubbles, so maps with hundreds of locations stay readable. |
| [Locator component](/guides/locator) | `craft.geomaps.locator()` outputs an address search, “Use my location”, a distance filter, a numbered results list and a map for any element query. |
| [Missing coordinates](/guides/missing-coordinates) | Looks up coordinates for locations that only have an address, such as imported ones, automatically or with `php craft geomaps/geocode`. |
| [Search within an area](/guides/search#search-within-an-area) | Finds entries inside a map area or a drawn shape, for “search as the map moves” and region pages, from Twig and GraphQL. |
| [Search endpoint](/guides/search-endpoint) | JSON search URLs for headless and app front ends, set up in the config file, with distances, paging and CORS. |
| [Import and export](/guides/import-export) | Loads locations, zones and trails from CSV, GeoJSON, KML or GPX into a section, and exports them again, with `php craft geomaps/locations/import` and `export`. |
| [Structured data](/examples/location-page#structured-data-for-search-engines) | `entry.storeLocation.schema()` outputs schema.org local business data, with the address, map position and opening hours, for search engines. |
| [Delivery zones for Commerce](/examples/delivery-zones) | Adds “Delivery address is in zone” and “Delivery distance” conditions to Craft Commerce shipping rules, shipping methods and discounts, to price delivery by drawn zone or distance from the store. |

See the [roadmap](https://github.com/suprim12/geomaps/blob/main/ROADMAP.md) for what’s planned.

## Trying Pro

You can try Pro free on local and staging sites:

1. Go to **Settings → Plugins** in the control panel.
2. Find **Geo Maps** and switch its edition to **Pro**.
3. Use the Pro features in your templates.

Craft asks for a licence once the site goes live in production. Buy Pro from the Plugin Store in your control panel, or at [plugins.craftcms.com/geomaps](https://plugins.craftcms.com/geomaps).

## Checking the edition in templates

```twig
{% if craft.app.plugins.getPlugin('geomaps').isPro() %}
  …
{% endif %}
```

## Without Pro

Pro options are ignored on the free edition rather than causing errors, so templates keep working if you switch back: a map with `cluster: true` shows every marker without grouping, `locator()` outputs nothing (an HTML comment in dev mode), `php craft geomaps/geocode` exits with a message saying it needs Pro, `within` is ignored, search endpoints return a 403 error, `schema()` outputs nothing, the import and export commands exit with a message, and Commerce delivery conditions match no orders. For maps and the locator, Craft logs a warning so you can see why.

The **Edition** tile at the top of the plugin’s settings page (**Settings → Plugins → Geo Maps**) shows whether Pro is active, with a **Try Pro** link on the free edition.
