# Troubleshooting

## “Google Maps rejected the API key”

The field shows this notice, and switches to OpenStreetMap, when Google refuses your **Google Maps API Key (browser)**. Your front-end maps switch too, so nothing breaks while you fix it. In the [Google Cloud console](https://console.cloud.google.com/google/maps-apis/credentials):

1. Enable the **Maps JavaScript API** and **Places API** for the key’s project, and the **Maps Static API** if you use `img()`.
2. Under the key’s **Application restrictions → Website restrictions**, add every domain the control panel and site run on, including local ones such as `*.ddev.site/*` or `localhost:*`.
3. Make sure billing is enabled on the project. Google refuses keys on projects without it.

Changes can take a few minutes to apply. Reload the page afterwards.

## Location search returns nothing

Work through these in order:

- **Is the place found?** Searches by address are geocoded first. If the address can’t be found, there are no results. Try a simpler address or pass `country: 'AU'` (your country code) to narrow it down.
- **Is the radius big enough?** The default is 50 in the chosen `unit` (`km` unless you say `mi`).
- **Are the entries indexed?** Coordinates are indexed when entries are saved. After importing content straight into the database, restoring a backup, or changing a field’s type, rebuild the index:

```bash
php craft geomaps/lookup/rebuild
```

- **Do the entries have coordinates?** An entry with only an address (for example from a feed without coordinates) can’t be found by distance. Open it and pick the address from the search, or import coordinates.

Check which entries have no location:

```twig
{{ craft.entries().section('stores').storeLocation(':empty:').count() }}
```

## “Which zone” (`contains`) returns nothing

- Only **areas** count: polygons and multipolygons. Markers and lines are ignored.
- The address must fall inside the area. Holes cut out of a zone don’t count as part of it.
- Zones saved before upgrading to a version with `contains` are indexed by the update migration. If you imported zones directly into the database, run `php craft geomaps/lookup/rebuild`.

## Maps don’t appear on the front end

- **Your layout must output Craft’s head and body hooks.** `embed()` adds its scripts and styles through them. Make sure your layout has `head()` before `</head>`, `beginBody()` after `<body>` and `endBody()` before `</body>`, each output with double curly braces.
- **Check for a Content Security Policy.** If your site sends one, allow the map services (see [below](#content-security-policy)).
- **Give the map a size.** `embed()` sets `width` and `height`; if your CSS overrides them, make sure the map container still has a height.

## The map tiles are grey or say “Access blocked”

The free `tile.openstreetmap.org` tiles are for light use. Busy public sites can be blocked under the [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/). Set **Map Tiles URL** to a tile provider such as Stadia Maps, MapTiler or Thunderforest — see [Configuration](/getting-started/configuration#map-providers).

## `img()` returns nothing

Static map images come from Google. Add a **Google Maps API Key** with the **Maps Static API** enabled, and allow your site’s domain in the key’s restrictions. Without one, `img()` and `imgSrcSet()` return `null`, so always check before outputting:

```twig
{% set src = entry.storeLocation.img({ width: 600, height: 300 }) %}
{% if src %}<img src="{{ src }}" alt="Map">{% endif %}
```

## Address search in the field finds the wrong places

- Set **Limit Search to Countries** in the field’s settings to the countries your entries are in (up to 5).
- Results are biased towards the area shown on the map, so pan the map nearer first.
- Without a Google key, search uses OpenStreetMap data. Google Places often knows more businesses; add a key if you need them.

## Content Security Policy

If your site sends a `Content-Security-Policy` header, allow the services Geo Maps uses:

| Directive | Allow | Needed for |
|---|---|---|
| `img-src` | `https://tile.openstreetmap.org` (or your tile provider) | OpenStreetMap tiles |
| `connect-src` | `https://photon.komoot.io` `https://nominatim.openstreetmap.org` | Address search and reverse lookup in the control panel |
| `script-src`, `img-src`, `connect-src` | `https://maps.googleapis.com` `https://maps.gstatic.com` | Google maps, Places search and static images (only with a Google key) |

Server-side geocoding for location search runs from your server, so it needs no browser rule, but your server must be able to reach `nominatim.openstreetmap.org` (or `maps.googleapis.com` with a Google key).

## Still stuck?

Email [suprimtech@gmail.com](mailto:suprimtech@gmail.com) with your Craft and Geo Maps versions, what you expected, and what happened. Lines from `storage/logs/web.log` mentioning `geomaps` help a lot.
