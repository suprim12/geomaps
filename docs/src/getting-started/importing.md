# Importing Existing Map Fields

Already using another map or GeoJSON field plugin? Geo Maps can convert those fields in place. Each field keeps its handle, field layouts and saved values, so your entries and most templates carry over.

## 1. Import

1. Back up your database.
2. Install Geo Maps alongside your current plugin.
3. Go to **Utilities → Geo Maps Import**. It lists every field Geo Maps can convert and how many values each one holds. Click **Import** next to a field to convert just that one, or **Import all**.

Or from the terminal:

```bash
php craft geomaps/import                      # every field
php craft geomaps/import --field=storeMap     # one field
```

Saved values are copied across, including on drafts and revisions, and indexed for location search. The other plugin’s own data is left untouched.

- **Location fields:** addresses, address parts, coordinates and zoom levels.
- **Shape (GeoJSON) fields:** shapes become the field’s GeoJSON, and each entry’s location is set to the centre of its shapes so it can be found with [location search](/guides/search).

If a field you expected isn’t listed, Geo Maps can’t convert it automatically. [Get in touch](mailto:suprimtech@gmail.com) and tell us which plugin it comes from.

## 2. Deploy

The import changes field types, so it updates your [project config](https://craftcms.com/docs/5.x/system/project-config.html). Commit `config/project/` and deploy as usual. On other environments, Geo Maps reads the existing saved values as-is and indexes them in the background, so you don’t need to run the import again there.

## 3. Check your templates

::: v-pre
**Shape fields:** `{{ entry.myField }}` still outputs the GeoJSON. You can also use `entry.myField.getGeoJsonData()` and the [map helpers](/guides/maps).
:::

**Location fields:** common template patterns keep working unchanged:

| Template code | In Geo Maps |
|---|---|
| `entry.map.lat`, `entry.map.lng`, `entry.map.zoom` | Same |
| `entry.map.address` | Same (alias of `full_address`) |
| `entry.map.parts.city`, `.postcode`, `.country` | Same |
| `entry.map.parts.address` | Same (street number and name) |
| `entry.map.distance` | Same |
| `entry.map.img({ width: 600 })` | Same (needs a Google key) |
| `craft.entries.map({ location: 'London', radius: 10, unit: 'mi' })` | Same |
| `craft.maps.*` | Use `craft.geomaps.*` |
| `parts.state`, `parts.county`, `what3words` | Not available |

The import utility also lists anything to check in your templates.

## 4. Uninstall the old plugin

Once you’ve checked everything on production, uninstall it from **Settings → Plugins**.
