# Release Notes for Geo Maps

## 1.2.1 - 2026-10-22

### Changed
- Links to the source code, issues, documentation and changelog point to the repository’s new address, github.com/suprim12/geomaps.

## 1.2.0 - 2026-10-22

### Added
- A redesigned shape panel in the field: click a shape to rename it, see its area or length as you draw, pick its colour from swatches, and edit its properties, or zoom to, copy or delete just that shape.
- `length()` and `area()` on field values, for trails, routes, zones and parks (`entry.route.length('km')`, `entry.zone.area('ha')`).
- Shapes in the field are drawn in their own `color`, `stroke` and `fill`, matching front-end maps.
- Lines on `embed()` maps are 4px wide by default, and any shape’s line width can be set with a `weight` property.
- The config file template includes the **Map Tiles URL** and **Map Credit** settings.
- Documentation: Trail Maps example, the shape panel, and a Privacy and Data page describing what the plugin stores and sends to map services.

### Changed
- OpenStreetMap geocoding (Nominatim) requests are spaced at least a second apart, as its usage policy requires, and pause for 10 minutes if Nominatim asks the site to slow down.
- Google geocoding results are cached as coordinates only, as Google’s terms require.

### Fixed
- Shape-only fields showed “No location set” while shapes were drawn; the footer now shows the centre of the shapes, which is saved as the location.
- Deselected shapes in the field turned purple instead of keeping their colour.
- Static map images wider or taller than 640 pixels failed; they’re now scaled down to Google’s limit.
- Static map marker labels with accents or punctuation, and named marker colours such as `red`, produced invalid images.
- Feed Me treated any two-letter country, such as `UK`, as a country code and lost the country name.
- A “Map styles must be an array” warning in the browser console with Google maps.

### Removed
- Unused internal classes (two distance calculators, a GraphQL resolver and type generator, and a leftover zone helper).

## 1.1.1 - 2026-10-20

### Added
- `embed()` `width` and `height` accept CSS lengths such as `'100%'` or `'60vh'`, as well as pixels.
- Shapes on Google `embed()` maps use their `color`, `stroke` and `fill` properties and show their `name` when clicked, as on OpenStreetMap maps.

### Changed
- The field saves the map’s zoom level with each location, and **Initial Location** in field settings saves its zoom.
- Feed Me fills in the country name from a two-letter code, and the field type is listed as “Geo Maps”.
- The support email is now support@suprim.com.au.

### Fixed
- Pressing Enter in the field’s address search saved the entry.
- Saved GeoJSON that wasn’t valid stopped the field from loading, so it couldn’t be fixed.
- After deleting every shape, edits in the GeoJSON editor no longer updated the map.
- Shape-only fields kept their old location after the last shape was deleted.
- The field ignored its default zoom once an entry had a value.
- The shape property editor opened with the wrong value type.
- Distance search missed entries across the 180° meridian and near the poles.
- `img()` and `imgSrcSet()` options carried over to later calls on the same value.
- `embed()` on a value with only an address centred the map at 0, 0.
- Invalid Feed Me coordinates were saved as 0 instead of being reported.
- Imported **Min Zoom** / **Max Zoom** settings were saved as decimals.
- Rebuilding the location search index was slow on sites with many field layouts.
- **Download** saved the text “null” when there were no shapes; files are now named `.geojson`.

## 1.1.0 - 2026-10-20

### Added
- Custom pin images and info windows for `embed()` maps: new `icon`, `html` and `open` marker options, plus a top-level `icon` for every marker. Works with OpenStreetMap and Google maps.

### Changed
- The plugin icon is 98% smaller (33 KB instead of 2.2 MB), so the control panel loads it faster.

### Security
- Map settings written into the page are now escaped so content such as entry titles can’t break out of the inline script.

## 1.0.0 - 2026-10-20

### Added
- Redesigned control panel field: search bar, uncovered map, labelled location details beside the map (or below it in narrow spaces such as Matrix blocks and slideouts), and a footer with the current coordinates, **Clear** and GeoJSON tools.
- Click the map to drop the pin.
- **Map Height** field setting.
- `contains` location search: find entries whose areas contain an address or point (e.g. which bin-collection zone or delivery area a customer is in), plus `contains()` on field values and `contains` / `containsCoordinate` in GraphQL. Holes are respected.
- **Show Location Pin** field setting for shape-only fields (zones, areas). The location becomes the centre of the shapes.
- `embed()` draws the field’s shapes and fits the map to them; new `geoJson`, `marker` and `fitShapes` options.
- **Enable Drawing & GeoJSON** field setting, for fields that only need a single location.
- **Limit Search to Countries** field setting: restrict address search to up to five countries (replaces the single country setting; existing values carry over).
- If Google rejects the Maps API key, the field and front-end maps switch to OpenStreetMap and explain why, instead of showing Google’s error.
- The GeoJSON panel has a title, explanation, close button and an example when empty.
- Works without API keys: OpenStreetMap tiles, address search (Photon) and geocoding (Nominatim) are used when no Google key is set.
- **Map Tiles URL** and **Map Credit** settings for using any raster tile provider.
- Import existing fields from other map and GeoJSON plugins under **Utilities → Geo Maps Import** — one field at a time or all at once — or with `php craft geomaps/import [--field=handle]`.
- `address`, `parts`, `img()` and `imgSrcSet()` on field values, so imported templates keep working.
- Proximity search results now include `distance` on the field value (Twig and GraphQL).
- Location search accepts a `coordinate`, a `{ lat, lng }` object, an address string, or another Geo Maps value.
- `:empty:` / `:notempty:` query params (a pin or a drawn area counts as a value).
- `php craft geomaps/lookup/rebuild` to rebuild the location search index.
- Geocoding results are cached to save API quota.

### Changed
- The GeoJSON editor loads only when its panel is opened, roughly halving the control panel script loaded on entry pages (283 KB → 149 KB gzipped).
- Field settings no longer store leftover value properties in project config.
- Rewritten documentation covering the field, templating, location search, maps, GraphQL, Feed Me and console commands.
- Third-party licences for bundled libraries now ship with the plugin (`THIRD-PARTY-LICENSES.md`).
- Drawing tools moved to the top-left; circle tools removed because GeoJSON can’t store circles.
- Google geocoding errors (e.g. a restricted key) now fall back to OpenStreetMap instead of returning no results.
- Requires Craft CMS 5.10 or later.
- Leaflet and drawing-tool styles are now bundled instead of loaded from unpkg.
- Country options come from Craft’s address repository.
- Postcodes are stored as strings, so leading zeros and alphanumeric postcodes are kept.
- The `craft.maps` Twig variable was removed (it clashed with another plugin); use `craft.geomaps`.

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
- GeoJSON saved as a bare geometry (without `properties`) broke the map.
- Marker labels in front-end Leaflet maps weren’t escaped.
- **Min Zoom** and **Max Zoom** field settings had no effect.
- The field-settings map showed Google’s error panel when the key was rejected.
