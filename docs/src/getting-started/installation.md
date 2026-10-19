# Installation

Geo Maps requires Craft CMS 5.10 or later and PHP 8.2 or later.

## From the Plugin Store

In the control panel, go to **Plugin Store**, search for **Geo Maps** and click **Install**.

## With Composer

```bash
composer require suprim/geomaps
php craft plugin/install geomaps
```

## Your first map in five minutes

1. **Create the field.** Go to **Settings → Fields → New field**. Name it “Location” (handle `location`) and choose **Geo Maps** as the field type. The defaults are fine; set **Limit Search to Countries** if your places are in one country.
2. **Add it to an entry type.** Open an entry type under **Settings → Entry Types**, drag the field into its layout and save.
3. **Set a location.** Edit an entry, type an address in the field’s search and pick a suggestion. Save.
4. **Show it.** In the entry’s template:

```twig
{% if entry.location %}
  <p>{{ entry.location.full_address }}</p>
  {{ entry.location.embed({ width: 800, height: 400 }) }}
{% endif %}
```

::: v-pre
Your layout needs Craft’s `{{ head() }}`, `{{ beginBody() }}` and `{{ endBody() }}` hooks for the map to load. Most layouts already have them.
:::

5. **Search by distance.** List entries near a place, nearest first:

```twig
{% for place in craft.entries().location({ location: 'Sydney, Australia', radius: 20 }).all() %}
  {{ place.title }}: {{ place.location.distance|number_format(1) }} km
{% endfor %}
```

## Next steps

1. Optionally add Google API keys under **Settings → Geo Maps** ([Configuration](/getting-started/configuration)). Without them, Geo Maps uses OpenStreetMap.
2. Create a field: **Settings → Fields → New field**, then choose **Geo Maps** as the field type ([The Field](/guides/field)).
3. Switching from another map plugin? See [Importing Existing Fields](/getting-started/importing).
4. Browse the [examples](/examples/store-locator) for complete templates, or [Troubleshooting](/getting-started/troubleshooting) if something doesn’t work.
