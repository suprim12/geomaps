# Console Commands

## Rebuild the search index

```bash
php craft geo/lookup/rebuild
```

Location search uses an index of coordinates that’s updated whenever an entry is saved. Rebuild it after importing content directly into the database or restoring a backup from another environment.

## Import from Maps (Ether)

```bash
php craft geo/import/simple-map
```

Converts every Maps field to a Geo Maps field. The same as **Utilities → Geo Maps Import** — see [Migrating from Maps](/getting-started/migrating-from-maps).
