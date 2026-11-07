# Console Commands

## Rebuild the search index

```bash
php craft geomaps/lookup/rebuild
```

Location search uses an index of coordinates that’s updated whenever an entry is saved. Rebuild it after importing content directly into the database or restoring a backup from another environment.

## Import existing map fields

```bash
php craft geomaps/import                    # every importable field
php craft geomaps/import --field=storeMap   # a single field
```

Converts fields to Geo Maps fields — the same as **Utilities → Geo Maps Import**. See [Importing Existing Fields](/getting-started/importing).

## Find missing coordinates <Badge type="tip" text="Pro" />

```bash
php craft geomaps/geocode                  # every Geo Maps field
php craft geomaps/geocode --dry-run        # list what would be looked up
```

Looks up coordinates for locations that have an address but no latitude and longitude. See [Missing Coordinates](/guides/missing-coordinates).

