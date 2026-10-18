# Release Notes for Geo Maps

## 1.0.0 - 2026-10-18

### Added
- Proximity search results now include `distance` on the field value (Twig and GraphQL).
- Location search accepts a `coordinate`, a `{ lat, lng }` object, an address string, or another Geo Maps value.
- `:empty:` / `:notempty:` query params.
- `php craft geo/lookup/rebuild` to rebuild the location search index.
- Geocoding results are cached to save API quota.

### Changed
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
