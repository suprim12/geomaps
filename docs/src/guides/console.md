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
