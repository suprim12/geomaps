# Content by Area

Show different content to visitors in different areas: delivery messages, regional offers, the local branch, or the right phone number. The visitor picks their address once, and the page uses the [zone](/guides/search#find-the-area-containing-a-place) that contains it.

Works on the free edition.

## The content

Draw your areas on entries in a **Regions** section with a Geo Maps field called `regionArea` (turn off **Show Location Pin** in the field’s settings so it only holds shapes), and add the fields you want to vary by region, such as `deliveryMessage` and `phone`.

## The template

```twig
{% set near = craft.app.request.getQueryParam('near') %}
{% set region = (near.lat ?? false)
  ? craft.entries.section('regions').regionArea({ contains: { lat: near.lat, lng: near.lng } }).one()
  : null %}

<form method="get" class="area-picker">
  <label for="area-address">Your address</label>
  {{ craft.geomaps.addressInput({
    name: 'near',
    id: 'area-address',
    value: (near.lat ?? false) ? near : null,
    countries: ['AU'],
    required: true,
  }) }}
  <button type="submit">Show my local info</button>
</form>

{% if region %}
  <section class="local">
    <h2>{{ region.title }}</h2>
    <p>{{ region.deliveryMessage }}</p>
    <p>Call us on <a href="tel:{{ region.phone }}">{{ region.phone }}</a></p>
  </section>
{% elseif near.lat ?? false %}
  <p>We don’t deliver to that address yet.</p>
{% endif %}
```

The search uses the coordinates from the address box, so nothing is sent to a geocoding service from your server.

## Remembering the visitor’s area

To show local content on every page, save the region in a cookie once it’s found, and read it on other pages:

```twig
{% if region %}
  {% do craft.app.response.cookies.add(create({
    class: 'yii\\web\\Cookie',
    name: 'region',
    value: region.id,
    expire: now|date_modify('+1 year')|date('U'),
  })) %}
{% endif %}

{# On any page #}
{% set regionId = craft.app.request.cookies.getValue('region') %}
{% set region = regionId ? craft.entries.section('regions').id(regionId).one() : null %}
```

If you use static caching, vary the cache by this cookie, or load the local content with JavaScript after the page loads.
