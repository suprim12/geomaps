# Search Endpoint <Badge type="tip" text="Pro" />

Ready-made JSON search for headless sites and apps: React, Vue, Next.js, mobile apps, or plain JavaScript on your own pages. Each endpoint returns the nearest entries with their distance, location and the fields you choose.

```text
GET /actions/geomaps/search?endpoint=stores&location=Glenelg%20SA&radius=25
```

```json
{
  "results": [
    {
      "id": 128,
      "title": "Glenelg Store",
      "url": "https://example.com/stores/glenelg",
      "distance": 0.13,
      "unit": "km",
      "location": {
        "lat": -34.98, "lng": 138.513,
        "address": "16 Jetty Rd, Glenelg 5045, Australia",
        "address1": "16 Jetty Rd", "suburb": "Glenelg", "postcode": "5045",
        "country": "Australia", "countryCode": "AU"
      },
      "phone": "08 8294 0000"
    }
  ],
  "total": 5,
  "limit": 20,
  "offset": 0,
  "unit": "km",
  "origin": { "lat": -34.9804, "lng": 138.5143 }
}
```

## Setting up an endpoint

Endpoints are set up in `config/geomaps.php`, so visitors can only search what you choose to share:

```php
return [
    'searchEndpoints' => [
        'stores' => [
            'field' => 'storeLocation',              // the Geo Maps field to search
            'criteria' => ['section' => 'stores'],   // which entries (any element query params)
            'fields' => ['phone', 'openingHours'],   // values to include in each result
        ],
    ],
];
```

| Option | Description | Default |
|---|---|---|
| `field` | The handle of the Geo Maps field to search. Required. | — |
| `criteria` | Element query params that pick the elements, such as `['section' => 'stores', 'relatedTo' => 12]`. Only live elements on the current site are returned. | — |
| `elementType` | The element type to search, such as `craft\commerce\elements\Product::class` | Entries |
| `fields` | Attributes and fields to add to each result. Related elements become `{ id, title, url }`, dates become ISO 8601. | — |
| `transform` | A function that builds each result yourself: `fn($element, $location) => [...]`. Replaces the default result. | — |
| `limit` | Results per page. Visitors can ask for up to 100. | `20` |
| `radius` | The radius when a visitor doesn't give one | `50` |
| `maxRadius` | The largest radius visitors can ask for | `500` |
| `unit` | `km` or `mi`, when a visitor doesn't give one | `km` |
| `country` | Two-letter code to narrow address lookups | — |
| `allowAddress` | Set to `false` to only accept `lat` and `lng`, so the endpoint never looks up addresses on your server | `true` |
| `cors` | Sites allowed to call the endpoint from a browser, such as `['https://app.example.com']`, or `['*']` for any | — |

## Searching

| Parameter | Description |
|---|---|
| `endpoint` | The endpoint's name from the config file |
| `location` | An address or place name, looked up with your [map service](/getting-started/configuration) |
| `lat`, `lng` | Coordinates to search from (instead of `location`), such as from the browser's location |
| `radius` | How far to search |
| `unit` | `km` or `mi` |
| `within` | A map area as `south,west,north,east`, for “search as the map moves” (see [Search within an area](/guides/search#search-within-an-area)). With `lat` and `lng`, results are sorted by distance. |
| `limit`, `offset` | Paging |
| `country` | Two-letter code to narrow the address lookup |

Without `location`, `lat`/`lng` or `within`, every element with a location is returned, a page at a time.

```js
const params = new URLSearchParams({ endpoint: 'stores', lat: -34.93, lng: 138.6, radius: 25 });
const res = await fetch(`https://example.com/actions/geomaps/search?${params}`);
const { results, total } = await res.json();
```

### Searching the visible map area

```js
map.on('moveend', async () => {
  const b = map.getBounds();
  const within = [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].join(',');
  const res = await fetch(`/actions/geomaps/search?endpoint=stores&within=${within}`);
  showStores((await res.json()).results);
});
```

## Your own results

Use `transform` to return exactly what your front end needs:

```php
'stores' => [
    'field' => 'storeLocation',
    'criteria' => ['section' => 'stores'],
    'transform' => fn($store, $location) => [
        'name' => $store->title,
        'km' => $location?->distance,
        'lat' => $location?->lat,
        'lng' => $location?->lng,
        'open' => $store->openNow,
    ],
],
```

## A nicer URL

Add a route in `config/routes.php`:

```php
return [
    'api/stores' => ['route' => 'geomaps/search', 'defaults' => ['endpoint' => 'stores']],
];
```

Or one route for every endpoint:

```php
return [
    'api/search/<endpoint:{handle}>' => 'geomaps/search',
];
```

## Errors

| Status | When |
|---|---|
| `400` | `within` isn't `south,west,north,east`, or the endpoint only accepts `lat` and `lng` |
| `403` | Geo Maps Pro isn't active |
| `404` | There's no endpoint with that name |

An address that can't be found returns `200` with no results, `origin: null` and a `message`.

## Good to know

- **Security:** visitors only choose the place, distance, area and page. They can't change which elements or fields are returned.
- **Caching:** address lookups are cached for 30 days (Mapbox only with permanent geocoding), so repeated searches for the same place don't call the map service again.
- **GraphQL:** prefer GraphQL? The same searches work with the field's [GraphQL arguments](/guides/graphql#location-search), including [`within`](/guides/graphql#searching-within-an-area).
