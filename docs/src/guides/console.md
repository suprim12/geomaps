# Console Commands

## Rebuild the search index

```bash
php craft geo/lookup/rebuild
```

Location search uses an index of coordinates that’s updated whenever an entry is saved. Rebuild it after importing content directly into the database or restoring a backup from another environment.

## Import from Maps (Ether) or Craft Geoman

```bash
php craft geo/import                    # every importable field
php craft geo/import --field=storeMap   # a single field
```

Converts fields to Geo Maps fields — the same as **Utilities → Geo Maps Import**. See [Migrating](/getting-started/migrating-from-maps).
