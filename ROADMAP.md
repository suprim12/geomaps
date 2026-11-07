# Geo Maps Roadmap

Geo Maps is free, and everything in the free edition stays free: location fields, drawn zones and trails, distance and “which zone” search, front-end maps, address autocomplete, GraphQL, Feed Me and importing from other map plugins.

**Geo Maps Pro** adds features that save developers time on bigger builds. Nothing that’s free moves to Pro.

Have a request? Open an issue on [GitHub](https://github.com/suprim12/geomaps/issues) or email [support@suprim.com.au](mailto:support@suprim.com.au).

## Done

- **Address autocomplete for front-end forms** (1.3). See the [example](https://plugins.geomaps.suprim.com.au/examples/address-autocomplete).
- **Store locator component** (1.4, Pro). Address search, “Use my location”, results list and map from one Twig tag.
- **Marker clustering** (1.4, Pro) for `embed()` maps.
- **Missing coordinates** (1.4, Pro). Coordinates for imported locations that only have an address, in the background or with `php craft geomaps/geocode`.

## Next

- **Viewport and polygon search.** Find entries inside the visible map area or any polygon from element queries, for “search as the map moves” without custom SQL.
- **Headless search endpoint.** A ready-made JSON endpoint and matching GraphQL arguments that return the nearest entries with distances, for React, Vue and app front ends.
- **Provider adapters.** Mapbox, HERE and MapTiler for geocoding, map tiles and static images, and an interface for adding your own.

## Later

- **Multiple locations per entry.** One field holding several addresses, such as a business with many branches, with search matching the nearest.
- **Large datasets.** Native database spatial indexes for sites with tens of thousands of locations.
- **Bulk import and export commands.** Load and export locations, zones and trails as GeoJSON, KML, GPX or CSV.
- **Route corridor search.** Find entries within a distance of a route or line.
- **A map of your entries in the control panel.** See every location in a section on one map, and jump to an entry from its pin.

## Considering

- Travel-time search (“within 15 minutes’ drive”) using a routing service.
- Elevation profiles for trails.

## Always free

Ways to build on Geo Maps stay in the free edition: events for geocoding and maps, Twig helpers, field values and query parameters, and clear errors and logging.
