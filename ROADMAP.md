# Geo Maps Roadmap

Geo Maps is free, and everything in the free edition stays free: location fields, drawn zones and trails, distance and “which zone” search, front-end maps, address autocomplete, GraphQL, Feed Me and importing from other map plugins.

**Geo Maps Pro** adds features that save developers time on bigger builds. Nothing that’s free moves to Pro.

Have a request? Open an issue on [GitHub](https://github.com/suprim12/geomaps/issues) or email [support@suprim.com.au](mailto:support@suprim.com.au).

## Done

- **Address autocomplete for front-end forms** (1.3). See the [example](https://plugins.geomaps.suprim.com.au/examples/address-autocomplete).
- **Locator component** (1.4, Pro). `craft.geomaps.locator()`: address search, “Use my location”, results list and map for any element query.
- **Marker clustering** (1.4, Pro) for `embed()` maps.
- **Mapbox** (1.4). Mapbox map styles, address lookups and static images.
- **Missing coordinates** (1.4, Pro). Coordinates for imported locations that only have an address, in the background or with `php craft geomaps/geocode`.
- **Search within an area** (1.4, Pro). Entries inside the visible map area or a drawn shape, from Twig and GraphQL.
- **Search endpoint** (1.4, Pro). Ready-made JSON search for headless sites and apps.
- **Import and export** (1.4, Pro). Locations, zones and trails as CSV, GeoJSON, KML or GPX.
- **Structured data** (1.4, Pro). schema.org local business data for location pages.

## Next

- **More providers.** HERE and MapTiler for address lookups and static images, and an interface for adding your own.

## Later

- **Multiple locations per entry.** One field holding several addresses, such as a business with many branches, with search matching the nearest.
- **Large datasets.** Native database spatial indexes for sites with tens of thousands of locations.
- **A map of your entries in the control panel.** See every location in a section on one map, and jump to an entry from its pin.
- **Route corridor search.** Find entries within a distance of a route or line.

## Considering

- Travel-time search (“within 15 minutes’ drive”) using a routing service.
- Elevation profiles for trails.

## Always free

Ways to build on Geo Maps stay in the free edition: events for geocoding and maps, Twig helpers, field values and query parameters, and clear errors and logging.
