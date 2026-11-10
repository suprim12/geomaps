# Search as the Map Moves <Badge type="tip" text="Pro" />

A store finder that searches exactly what's on the map: pan or zoom and the list and pins update, with no page reload. An address box moves the map. The data comes from a [search endpoint](/guides/search-endpoint), so there's no JSON template to write.

![A store finder: an address box above five numbered stores with their distances, beside a map of Adelaide with matching numbered pins](/examples/map-search.jpg)

Compare it with [Live Map Search](/examples/live-finder), which does the same with a hand-written JSON template on Lite.

## 1. The endpoint

In `config/geomaps.php`:

```php
return [
    'searchEndpoints' => [
        'stores' => [
            'field' => 'storeLocation',
            'criteria' => ['section' => 'stores'],
        ],
    ],
];
```

That's the whole back end: `/actions/geomaps/search?endpoint=stores` now returns stores with their distance, address and URL.

## 2. The page

```twig
{% extends '_layout' %}

{% block content %}
<div class="finder">
  <aside>
    <h1>Find a store</h1>
    <form class="search" id="place-form">
      <label class="box">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" id="place" placeholder="Suburb or address" aria-label="Suburb or address">
      </label>
      <button class="btn">Go</button>
    </form>
    <p class="meta" id="meta" aria-live="polite">Move the map to search.</p>
    <ol class="rows" id="results"></ol>
  </aside>
  <section><div id="map" style="height: 100%; border-radius: 14px; overflow: hidden"></div></section>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
{% set geo = craft.app.plugins.getPlugin('geomaps').settings %}
<script>
  const endpoint = '/actions/geomaps/search?endpoint=stores';
  const map = L.map('map').setView([-34.93, 138.6], 11);
  L.tileLayer({{ geo.getTileUrl()|json_encode|raw }}, { attribution: {{ geo.getTileAttribution()|json_encode|raw }} }).addTo(map);

  const pins = L.layerGroup().addTo(map);
  const results = document.getElementById('results');
  const meta = document.getElementById('meta');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
  const pin = (n) => L.divIcon({ className: '', iconSize: [36, 46], iconAnchor: [18, 46],
    html: `<svg width="36" height="46" viewBox="0 0 36 46"><path d="M18 45s16-15.6 16-27.5A16 16 0 0 0 2 17.5C2 29.4 18 45 18 45z" fill="#5b67e0" stroke="#fff" stroke-width="2.5"/><text x="18" y="23" text-anchor="middle" font-family="Inter,Arial" font-weight="700" font-size="15" fill="#fff">${n}</text></svg>` });
  let controller;

  // Stores inside the visible map, nearest its centre first.
  async function search() {
    const b = map.getBounds();
    const c = map.getCenter();
    const params = new URLSearchParams({
      within: [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].map((n) => n.toFixed(5)).join(','),
      lat: c.lat.toFixed(5),
      lng: c.lng.toFixed(5),
    });
    controller?.abort();
    controller = new AbortController();
    const { results: stores, total } = await (await fetch(`${endpoint}&${params}`, { signal: controller.signal })).json();

    pins.clearLayers();
    stores.forEach((s, i) => L.marker([s.location.lat, s.location.lng], { icon: pin(i + 1), title: s.title })
      .bindPopup(`<strong>${esc(s.title)}</strong><br>${esc(s.location.address)}`).addTo(pins));
    results.innerHTML = stores.map((s, i) => `
      <li class="row"><span class="n">${i + 1}</span><h3><a href="${esc(s.url)}">${esc(s.title)}</a></h3>
      <span class="d">${s.distance.toFixed(1)} km</span><p>${esc(s.location.address)}</p></li>`).join('');
    meta.textContent = !total ? 'No stores here. Zoom out or move the map.'
      : total > stores.length ? `Showing the ${stores.length} nearest of ${total} stores on the map` : `${total} store${total === 1 ? '' : 's'} on the map`;
  }

  // An address moves the map; the endpoint looks it up.
  document.getElementById('place-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = document.getElementById('place').value.trim();
    if (!q) return;
    const { origin, message } = await (await fetch(`${endpoint}&limit=1&radius=100&location=${encodeURIComponent(q)}`)).json();
    if (origin) map.setView([origin.lat, origin.lng], 13); else meta.textContent = message;
  });

  map.on('moveend', () => search().catch((e) => e.name === 'AbortError' || console.error(e)));
  search();
</script>
{% endblock %}
```

How it works:

- **The visible map** is sent as `within` (south, west, north, east), with the map's centre as `lat` and `lng`, so stores come back nearest the centre first. Only the 20 nearest are drawn; `total` says how many are on the map.
- **The address box** asks the endpoint for the place with `location`, and moves the map to the `origin` it returns. Moving the map then runs the normal search.
- **Moving the map quickly** cancels the previous request, so results never arrive out of order.
- **The tiles** come from your [map service](/getting-started/configuration) settings, so the map matches your other maps.

## Going further

- **More results:** add `&limit=50`, or page through them with `&offset=20`.
- **Filters:** add `relatedTo` or other params to the endpoint's `criteria`, or set up a second endpoint, such as `stores-with-parking`.
- **Another site or app:** add its address to the endpoint's `cors` option to call it from there.
