# Store Locator

A complete “find a store near you” page: search by address or the visitor’s current location, list the nearest stores with distances, show them on a map, and paginate.

![A store locator listing the four nearest stores to Adelaide on a map, with distances, directions links and pagination](/examples/store-locator.jpg)

## Setup

- A **Stores** section (handle `stores`) with a Geo Maps field (handle `storeLocation`).
- Optionally, a **Phone** plain text field (handle `phone`).

## The template

`templates/stores/index.twig`:

```twig
{% extends '_layout' %}

{# Search from typed text, or from coordinates sent by “Use my location” #}
{% set request = craft.app.request %}
{% set q = request.getParam('q')|trim %}
{% set lat = request.getParam('lat') %}
{% set lng = request.getParam('lng') %}
{% set radius = request.getParam('radius')|default(25) %}
{% set unit = request.getParam('unit') == 'mi' ? 'mi' : 'km' %}

{% set near = lat is not empty and lng is not empty ? { lat: lat, lng: lng } : q %}

{% block content %}
  <h1>Find a store</h1>

  <form method="get" class="store-search">
    <input type="search" name="q" value="{{ q }}" placeholder="Suburb, postcode or address">
    <select name="radius">
      {% for r in [5, 10, 25, 50, 100] %}
        <option value="{{ r }}" {{ r == radius ? 'selected' }}>{{ r }} {{ unit }}</option>
      {% endfor %}
    </select>
    <input type="hidden" name="lat" value="">
    <input type="hidden" name="lng" value="">
    <button type="submit">Search</button>
    <button type="button" id="use-location">Use my location</button>
  </form>

  {% if near %}
    {% set query = craft.entries()
      .section('stores')
      .storeLocation({ location: near, radius: radius, unit: unit, country: 'AU' })
      .limit(10) %}

    {% paginate query as pageInfo, stores %}

    {% if stores|length %}
      <p>{{ pageInfo.total }} store{{ pageInfo.total != 1 ? 's' }} within {{ radius }} {{ unit }}</p>

      {{ craft.geomaps.embed({
        id: 'stores-map',
        center: { lat: stores[0].storeLocation.lat, lng: stores[0].storeLocation.lng },
        zoom: 11,
        width: 900,
        height: 420,
        markers: stores|map(store => {
          location: { lat: store.storeLocation.lat, lng: store.storeLocation.lng },
          label: store.title,
          color: '#2563eb',
        }),
      }) }}

      <ol class="store-list">
        {% for store in stores %}
          {% set place = store.storeLocation %}
          <li>
            <h2><a href="{{ store.url }}">{{ store.title }}</a></h2>
            <p>{{ place.full_address }}</p>
            <p><strong>{{ place.distance|number_format(1) }} {{ unit }}</strong> away</p>
            {% if store.phone ?? null %}<p><a href="tel:{{ store.phone }}">{{ store.phone }}</a></p>{% endif %}
            <a href="https://www.google.com/maps/dir/?api=1&destination={{ place.lat }},{{ place.lng }}" target="_blank" rel="noopener">Directions</a>
          </li>
        {% endfor %}
      </ol>

      {% if pageInfo.totalPages > 1 %}
        <nav class="pagination">
          {% if pageInfo.prevUrl %}<a href="{{ pageInfo.prevUrl }}">← Nearer</a>{% endif %}
          Page {{ pageInfo.currentPage }} of {{ pageInfo.totalPages }}
          {% if pageInfo.nextUrl %}<a href="{{ pageInfo.nextUrl }}">Further →</a>{% endif %}
        </nav>
      {% endif %}
    {% else %}
      <p>No stores within {{ radius }} {{ unit }} of “{{ q ?: 'your location' }}”. Try a larger distance.</p>
    {% endif %}
  {% endif %}

  {% js %}
    document.getElementById('use-location').addEventListener('click', () => {
      const form = document.querySelector('.store-search');
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          form.lat.value = pos.coords.latitude.toFixed(6);
          form.lng.value = pos.coords.longitude.toFixed(6);
          form.q.value = '';
          form.submit();
        },
        () => alert('We couldn’t get your location. Type a suburb or postcode instead.'),
      );
    });
  {% endjs %}
{% endblock %}
```

## How it works

- **`location`** takes the typed text (geocoded and cached for 30 days) or a `{ lat, lng }` object. Coordinates from the browser skip geocoding entirely, so they’re the fastest.
- **`country: 'AU'`** stops “Richmond” matching Richmond in another country. Remove it for international sites.
- Results are **nearest first** and each one has **`distance`** in the unit you asked for.
- **`{% paginate %}`** works as normal: the total, page count and page URLs all respect the radius.
- The map centres on the nearest store. Every store on the current page gets a marker with its name in a popup.

## Numbered pins

Number the pins to match the list, as in the screenshot. A pin `icon` can be any image URL, including an SVG built in Twig. Save this as `templates/_pin.twig`:

```twig
{%- set svg -%}
<svg xmlns="http://www.w3.org/2000/svg" width="36" height="46" viewBox="0 0 36 46"><path d="M18 45s16-15.6 16-27.5A16 16 0 0 0 2 17.5C2 29.4 18 45 18 45z" fill="{{ colour ?? '#5b67e0' }}" stroke="#fff" stroke-width="2.5"/><text x="18" y="23" text-anchor="middle" font-family="Arial,sans-serif" font-weight="700" font-size="15" fill="#fff">{{ n }}</text></svg>
{%- endset -%}
data:image/svg+xml,{{ svg|trim|url_encode }}
```

Then use it for each marker, numbering from the first result on the page:

```twig
markers: stores|map((store, i) => {
  location: { lat: store.storeLocation.lat, lng: store.storeLocation.lng },
  label: store.title,
  icon: { url: include('_pin', { n: pageInfo.first + i }), width: 36, height: 46 },
  open: i == 0,
}),
```

To fill the space beside the list, give the map a CSS size: `width: '100%', height: '100%'` inside a container with a height.

## Variations

**Sort alphabetically instead** (distance is still set on each store):

```twig
{% set query = query.orderBy('title') %}
```

**Show the nearest store only:**

```twig
{% set nearest = craft.entries()
  .section('stores')
  .storeLocation({ location: near, radius: 500 })
  .one() %}
```

**Combine with other filters.** Location search is an ordinary query parameter, so it works with categories, relations and keyword search:

```twig
{% set query = craft.entries()
  .section('stores')
  .relatedTo(category)
  .storeLocation({ location: near, radius: 25 }) %}
```
