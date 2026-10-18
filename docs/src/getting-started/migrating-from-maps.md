# Migrating from Maps (Ether)

Geo Maps can take over your existing [Maps](https://plugins.craftcms.com/simplemap) (`ether/simplemap`) fields in one click. Each field keeps its handle, field layouts and saved locations.

## 1. Import

1. Back up your database.
2. Install Geo Maps alongside Maps.
3. Go to **Utilities → Geo Maps Import** and click **Import**.

Or from the terminal:

```bash
php craft geo/import/simple-map
```

Every Maps field is converted to a Geo Maps field. Saved addresses, coordinates and zoom levels are copied across, including on drafts and revisions, and indexed for location search. The Maps plugin’s own data is left untouched.

## 2. Deploy

The import changes field types, so it updates your [project config](https://craftcms.com/docs/5.x/system/project-config.html). Commit `config/project/` and deploy as usual. On other environments, Geo Maps reads values saved by Maps as-is and indexes them in the background, so you don’t need to run the import again there.

## 3. Check your templates

Most templates keep working unchanged:

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

## 4. Uninstall Maps

Once you’ve checked everything on production, uninstall Maps from **Settings → Plugins**.
