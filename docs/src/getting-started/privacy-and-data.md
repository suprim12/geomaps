# Privacy and Data

What Geo Maps stores, and what it sends to map services, so you can describe it in your site’s privacy policy and decide whether you need consent before showing maps.

::: info
This page explains how the plugin works. It isn’t legal advice; check your obligations under the laws that apply to you, such as the GDPR or the Australian Privacy Act.
:::

## The plugin itself

- **No tracking.** Geo Maps has no analytics or telemetry and never contacts us. Licence checks and update notices are handled by Craft CMS itself.
- **No cookies.** Geo Maps sets no cookies, in the control panel or on your site. Google may set its own when Google maps are shown; see [Visitors to your site](#visitors-to-your-site).

## What’s stored in your database

- **Field values:** coordinates, the address and its parts, the zoom level and any drawn shapes with their properties, in Craft’s content tables like any other field.
- **A search index:** a copy of each value’s coordinates and area outlines in the `supgeo` table, used for distance and `contains` searches. Rows are removed automatically when their entry is deleted, and the table is removed when the plugin is uninstalled.
- **A cache:** looked-up addresses and their coordinates are cached for 30 days in Craft’s cache. From Google, only the coordinates are cached, as Google’s terms require.

If you store people’s home addresses (customers, members, staff), those values are personal data. Treat them like any other personal data on your site.

## What’s sent to map services

Each map service receives the requester’s IP address and browser details, as with any web request, plus the information below.

### In the control panel (your authors)

| When | Sent to | What |
|---|---|---|
| Showing the map | OpenStreetMap tile servers, or your [tile provider](/getting-started/configuration#map-providers), or Google with a key | Which map area is being viewed |
| Typing in the address search | [Photon](https://photon.komoot.io) (komoot), or Google Places with a key | The text typed, the map’s position and the field’s countries |
| Dropping or dragging the pin | [Nominatim](https://nominatim.org) (OpenStreetMap Foundation), or Google with a key | The pin’s coordinates |

### On your server

| When | Sent to | What |
|---|---|---|
| A template searches by an address (`location: 'Glenelg'`, `contains: '12 Smith St'`) | Nominatim, or Google Geocoding with a key | The address text and your site’s URL (Nominatim requires requests to identify the site) |

Searches by coordinates (`{ lat, lng }`) or by another entry’s value aren’t sent anywhere. If your site searches by addresses that visitors type, those addresses go to the geocoding service; mention this in your privacy policy.

### Visitors to your site

| When | Sent to | What |
|---|---|---|
| A page shows `embed()` with OpenStreetMap | OpenStreetMap tile servers, or your tile provider | Which map area is viewed. No cookies. |
| A page shows `embed()` with Google | Google Maps | Which map area is viewed. Google may set cookies and processes data under the [Google Privacy Policy](https://policies.google.com/privacy). |
| A page shows `img()` / `imgSrcSet()` | Google Static Maps | The image’s location and markers |

Custom pin images and everything else on the page come from your own site.

## Asking for consent

OpenStreetMap maps set no cookies, so many sites show them without a consent prompt. Google maps may set cookies and transfer data to Google, which in the EU usually needs consent first. Only output the map once your consent tool says it’s allowed, and show a link or a static image otherwise:

```twig
{% if mapsAllowed %}
  {{ entry.storeLocation.embed({ height: 400 }) }}
{% else %}
  <p><a href="https://www.openstreetmap.org/?mlat={{ entry.storeLocation.lat }}&mlon={{ entry.storeLocation.lng }}#map=16/{{ entry.storeLocation.lat }}/{{ entry.storeLocation.lng }}">View on a map</a></p>
{% endif %}
```

`mapsAllowed` stands for however your consent tool exposes the visitor’s choice, such as a cookie or a JavaScript-loaded fragment.

To avoid Google entirely, leave the Google keys blank: maps, address search and geocoding then use OpenStreetMap services only.

## Wording for your privacy policy

Adapt this to the services your site actually uses:

> **Maps.** Our pages show maps from OpenStreetMap [and Google Maps]. When a map is shown, your browser requests map images from these services, which receive your IP address and the area being viewed. [Google may set cookies; see the Google Privacy Policy.] When you search our site by address, the address is sent to a geocoding service (OpenStreetMap Nominatim [or Google]) to find its location.

## Usage policies of the free services

The free OpenStreetMap services are for light use:

- **Tiles:** busy public sites should use a [tile provider](/getting-started/configuration#map-providers) ([tile usage policy](https://operations.osmfoundation.org/policies/tiles/)).
- **Nominatim:** at most one request per second ([Nominatim usage policy](https://operations.osmfoundation.org/policies/nominatim/)). Geo Maps spaces its requests, caches results for 30 days, and pauses for 10 minutes if Nominatim asks it to slow down. Sites with a lot of address searches should add a Google Geocoding key.
- **Photon:** a free public service run by komoot for fair use.

OpenStreetMap data must be credited. Geo Maps shows the credit on every map; keep it visible if you restyle your maps.
