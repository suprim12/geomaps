# Geo Maps Pro

Geo Maps is free, and everything in the free edition stays free. **Geo Maps Pro** adds extra features for bigger sites.

## Pro features

| Feature | What it does |
|---|---|
| [Marker clustering](/guides/maps#marker-clustering) | Groups nearby pins on `embed()` maps into numbered bubbles, so maps with hundreds of locations stay readable. |
| [Locator component](/guides/locator) | `craft.geomaps.locator()` outputs an address search, “Use my location”, a distance filter, a numbered results list and a map for any element query. |
| [Missing coordinates](/guides/missing-coordinates) | Looks up coordinates for locations that only have an address, such as imported ones, automatically or with `php craft geomaps/geocode`. |

More Pro features are on the way; see the [roadmap](https://github.com/suprim12/geomaps/blob/main/ROADMAP.md).

## Trying Pro

You can try Pro free on local and staging sites:

1. Go to **Settings → Plugins** in the control panel.
2. Find **Geo Maps** and switch its edition to **Pro**.
3. Use the Pro features in your templates.

Craft asks for a licence once the site goes live in production. Buy Pro from the Plugin Store in your control panel, or at [plugins.craftcms.com/geomaps](https://plugins.craftcms.com/geomaps).

## Checking the edition in templates

```twig
{% if craft.app.plugins.getPlugin('geomaps').isPro() %}
  …
{% endif %}
```

## Without Pro

Pro options are ignored on the free edition rather than causing errors, so templates keep working if you switch back. For example, a map with `cluster: true` shows every marker without grouping. Craft logs a warning so you can see why.

The **Geo Maps Pro** section at the bottom of the plugin’s settings page shows whether Pro is active.
