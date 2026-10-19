# Migrating from Maps or Craft Geoman

Geo Maps can take over existing [Maps](https://plugins.craftcms.com/simplemap) (`ether/simplemap`) and [Craft Geoman](https://plugins.craftcms.com/craft-geoman) (`handplant/craft-geoman`) fields. Each field keeps its handle, field layouts and saved values.

## 1. Import

1. Back up your database.
2. Install Geo Maps alongside the old plugin.
3. Go to **Utilities → Geo Maps Import**. Click **Import** next to a field to convert just that one, or **Import all**.

Or from the terminal:

```bash
php craft geo/import                      # every field
php craft geo/import --field=storeMap     # one field
```

Saved values are copied across, including on drafts and revisions, and indexed for location search. The old plugin’s own data is left untouched.

- **Maps:** addresses, address parts, coordinates and zoom levels.
- **Craft Geoman:** shapes become the field’s GeoJSON, and each entry’s location is set to the centre of its shapes so it can be found with [location search](/guides/search).

## 2. Deploy

The import changes field types, so it updates your [project config](https://craftcms.com/docs/5.x/system/project-config.html). Commit `config/project/` and deploy as usual. On other environments, Geo Maps reads values saved by Maps as-is and indexes them in the background, so you don’t need to run the import again there.

## 3. Check your templates

::: v-pre
**From Craft Geoman:** `{{ entry.myField }}` still outputs the GeoJSON. You can also use `entry.myField.getGeoJsonData()` and the [map helpers](/guides/maps).
:::

**From Maps**, most templates keep working unchanged:

| Maps | Geo Maps |
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

## 4. Uninstall the old plugin

Once you’ve checked everything on production, uninstall it from **Settings → Plugins**.
