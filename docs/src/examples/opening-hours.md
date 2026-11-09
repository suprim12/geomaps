# Opening Hours and “Open Now” <Badge type="tip" text="Pro for the locator part" />

Show whether a location is open right now (“Open now · closes 5:00 pm”, “Opens 9:00 am”, “Closed today”) on entry pages and in [locator component](/guides/locator) results.

The opening hours themselves are ordinary Craft content, so this works with any field you like. This example uses a **Table** field.

![Locator results near Adelaide, each showing “Open now · closes 11:59 pm” under the store's address](/examples/opening-hours.jpg)

## The field

Add a Table field called **Opening Hours** (`openingHours`) to your stores, with three columns:

| Column heading | Handle | Type |
|---|---|---|
| Day | `day` | Dropdown: Monday … Sunday |
| Opens | `open` | Time |
| Closes | `close` | Time |

Leave a day out, or leave its times empty, when the store is closed.

## The “open now” partial

```twig
{# templates/_partials/open-status.twig
   `hours`: rows of { day, open, close } from the Opening Hours field. #}
{% set now = now|date('U') %}
{% set today = now|date('l') %}
{% set row = hours|filter(r => r.day == today)|first %}

{% if row and row.open and row.close %}
  {% set opens = (now|date('Y-m-d') ~ ' ' ~ row.open|date('H:i'))|date('U') %}
  {% set closes = (now|date('Y-m-d') ~ ' ' ~ row.close|date('H:i'))|date('U') %}
  {% if now >= opens and now < closes %}
    <span class="open-status is-open">Open now · closes {{ closes|date('g:i a') }}</span>
  {% elseif now < opens %}
    <span class="open-status">Opens {{ opens|date('g:i a') }}</span>
  {% else %}
    <span class="open-status">Closed now</span>
  {% endif %}
{% else %}
  <span class="open-status">Closed today</span>
{% endif %}
```

Times are compared in your site’s time zone (**Settings → General**). For stores in several time zones, add a time zone field to each store and pass it to the `date` filters.

## On an entry page

Works on the free edition:

```twig
<h1>{{ entry.title }}</h1>
{{ include('_partials/open-status', { hours: entry.openingHours }) }}
<address>{{ entry.storeLocation.address1 }}, {{ entry.storeLocation.suburb }}</address>
```

## In locator results <Badge type="tip" text="Pro" />

Give [`locator()`](/guides/locator) your own result template, and include the partial in it:

```twig
{{ craft.geomaps.locator({
  section: 'stores',
  field: 'storeLocation',
  resultTemplate: '_partials/locator-result',
}) }}
```

```twig
{# templates/_partials/locator-result.twig #}
<h3 class="geomaps-locator__name">{{ entry.title }}</h3>
{% if distance is not null %}
  <span class="geomaps-locator__distance">{{ distance|number_format(1) }} {{ unit }}</span>
{% endif %}
<p class="geomaps-locator__address">{{ location.address1 }}, {{ location.suburb }}</p>
{{ include('_partials/open-status', { hours: entry.openingHours }) }}
```

```css
.open-status { font-size: 13px; color: #6b7280; }
.open-status.is-open { color: #15803d; font-weight: 600; }
```

Pages with “Open now” change through the day, so if you use static caching, cache them for a short time only.
