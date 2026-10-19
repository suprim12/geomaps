# Release Notes for Geo Maps

## 1.0.0 - 2026-10-18

### Added
- Redesigned control panel field: search bar, uncovered map, labelled location details beside the map (or below it in narrow spaces such as Matrix blocks and slideouts), and a footer with the current coordinates, **Clear** and GeoJSON tools.
- Click the map to drop the pin.
- **Map Height** field setting.
- Works without API keys: OpenStreetMap tiles, address search (Photon) and geocoding (Nominatim) are used when no Google key is set.
- **Tile URL** and **Tile Attribution** settings for using any raster tile provider.
- One-click import from Maps (ether/simplemap) under **Utilities → Geo Maps Import**, or `php craft geo/import/simple-map`.
- Maps-compatible `address`, `parts`, `img()` and `imgSrcSet()` on field values, so migrated templates keep working.
- Proximity search results now include `distance` on the field value (Twig and GraphQL).
- Location search accepts a `coordinate`, a `{ lat, lng }` object, an address string, or another Geo Maps value.
- `:empty:` / `:notempty:` query params.
- `php craft geo/lookup/rebuild` to rebuild the location search index.
- Geocoding results are cached to save API quota.

### Changed
- Drawing tools moved to the top-left; circle tools removed because GeoJSON can’t store circles.
- Google geocoding errors (e.g. a restricted key) now fall back to OpenStreetMap instead of returning no results.
- Requires Craft CMS 5.10 or later.
- Leaflet and Geoman styles are now bundled instead of loaded from unpkg.
- Country options come from Craft’s address repository.
- Postcodes are stored as strings, so leading zeros and alphanumeric postcodes are kept.
- The `craft.maps` Twig variable was removed (it clashed with Maps by Ether); use `craft.geomaps`.

### Fixed
- Location search never returned results because coordinates weren’t written to the search index. Existing content is reindexed on update.
- Coordinate and GeoJSON validation errors weren’t shown.
- Soft-deleted then restored entries dropped out of location search.
- Location search added a global event listener for every element query.
- Static maps crashed on sites without the CKEditor plugin.
- Static map URLs double-encoded address centers and markers.
- `embed()` broke when the map `id` contained a hyphen.
- Saved initial map location in field settings was ignored.
- Google Places/Geocoder errors when the Maps API loads asynchronously or fails to load.
- Location search on PostgreSQL.
- New entries saved the field’s default map centre as their location.
- The field’s preferred country was saved into each entry’s country.
- The country value held the county/state name when picked from Google search.
- Whole-number coordinates were treated as empty.
- The control panel asset bundle published PHP files to the web root.
- Field styles leaked into the rest of the control panel (e.g. submit buttons).
- The map didn’t fill its container after being revealed in tabs, slideouts or field settings.
- **Clear** left the country code and search text behind.
- Duplicate OpenStreetMap search suggestions.
