# Import and Export <Badge type="tip" text="Pro" />

Load locations into a section from a spreadsheet or mapping file, or get them out again, in one command. Use it to bring in a store list, council zones from a GIS team, or walking trails from a GPS app.

```bash
php craft geomaps/locations/import stores.csv --section=stores --field=storeLocation
php craft geomaps/locations/export --section=stores --field=storeLocation --output=stores.geojson
```

| Format | Import | Export |
|---|---|---|
| CSV (`.csv`) | Points, addresses and shapes | ✓ |
| GeoJSON (`.geojson`, `.json`) | Points, lines and areas | ✓ |
| KML (`.kml`), from Google My Maps and Google Earth | Points, lines and areas | ✓ |
| GPX (`.gpx`), from GPS and fitness apps | Tracks and routes become lines, waypoints become points | — |

## Importing

```bash
php craft geomaps/locations/import zones.geojson --section=binZones --field=zoneArea
```

Each row, feature or placemark becomes an entry:

- **New or existing:** entries are matched by slug, then by title when there's no slug, so running the same import again updates them rather than creating copies. Use `--match=title` or `--match=id` to match differently.
- **Points** become the entry's pin. **Lines and areas** are drawn on the map, keeping a GeoJSON `color`, `stroke` or `fill`.
- **Other columns and properties** are saved into fields with the same handle, such as a `phone` column into a Phone field. Others are listed at the end and left out.
- **Addresses without coordinates** are saved as they are, and [missing coordinates](/guides/missing-coordinates) looks them up, in the background or with `php craft geomaps/geocode`.

| Option | Description |
|---|---|
| `--section` | The section handle. Required |
| `--field` | The Geo Maps field handle. Required |
| `--type` | The entry type for new entries. Default: the section's first |
| `--match` | Find existing entries by `slug` (default), `title` or `id` |
| `--update-only` | Only update existing entries, never create new ones |
| `--dry-run` | Show what would be created and updated, without saving anything |
| `--site` | The site handle. Default: the primary site |

### CSV columns

The first row names the columns. Geo Maps recognises these, in any order and upper or lower case:

| Column | Also accepted |
|---|---|
| `title` | `name` |
| `slug`, `id` | |
| `lat`, `lng` | `latitude`, `longitude`, `lon`, `long` |
| `full_address` | `address` |
| `address1` | `street`, `street_address` |
| `suburb` | `city`, `town`, `locality` |
| `postcode` | `zip`, `postal_code` |
| `country`, `countryCode` | `country_code` |
| `geoJson` | A drawn shape as GeoJSON text |

```csv
title,slug,lat,lng,address,suburb,postcode,country_code,phone
Glenelg Store,glenelg,-34.980,138.513,"16 Jetty Rd, Glenelg SA 5045",Glenelg,5045,AU,08 8294 0000
Norwood Store,norwood,,,"175 The Parade, Norwood SA 5067",Norwood,5067,AU,08 8333 0000
```

Rows with errors, such as a latitude above 90, are reported and skipped. The rest are still imported.

## Exporting

```bash
php craft geomaps/locations/export --section=stores --field=storeLocation --output=stores.csv
php craft geomaps/locations/export --section=zones --field=zoneArea --format=kml > zones.kml
```

| Option | Description |
|---|---|
| `--section`, `--field` | Required, as for importing |
| `--format` | `csv`, `geojson` or `kml`. Default: from the `--output` file's extension, otherwise `csv` |
| `--output` | The file to write. Without it, the export is printed |
| `--fields` | Other attributes and fields to include, comma separated, such as `--fields=phone,postDate` |
| `--site` | The site handle. Default: the primary site |

Every entry with a location is exported, including disabled ones. GeoJSON and KML include drawn shapes as their own features, each with the entry's title and details. A CSV export can be imported again, shapes included.
