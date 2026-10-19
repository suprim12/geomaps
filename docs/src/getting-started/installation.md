# Installation

Geo Maps requires Craft CMS 5.10 or later and PHP 8.2 or later.

## From the Plugin Store

In the control panel, go to **Plugin Store**, search for **Geo Maps** and click **Install**.

## With Composer

```bash
composer require suprim/geomaps
php craft plugin/install geomaps
```

## Next steps

1. Optionally add Google API keys under **Settings → Geo Maps** ([Configuration](/getting-started/configuration)). Without them, Geo Maps uses OpenStreetMap.
2. Create a field: **Settings → Fields → New field**, then choose **Geo Maps** as the field type ([The Field](/guides/field)).
3. Moving from Maps (Ether)? See [Migrating from Maps](/getting-started/migrating-from-maps).
