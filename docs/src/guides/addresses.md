# Craft Addresses

Craft has its own addresses: in Addresses fields, on users, and on Craft Commerce orders. Geo Maps works with them too, so you can find the nearest store to a customer, show addresses on a map, or search near an order’s shipping address.

## Anywhere a place is accepted

Pass a Craft address wherever Geo Maps takes a place: a location search, a map’s centre or pins, or `distance()`. Its coordinates are used when it has them; otherwise the address is looked up. This works in Lite and Pro.

```twig
{# Stores within 20 km of the customer’s shipping address, nearest first #}
{% set stores = craft.entries
  .section('stores')
  .storeLocation({ location: cart.shippingAddress, radius: 20 })
  .all() %}

{# How far away is it? #}
{{ craft.geomaps.distance(entry.storeLocation, currentUser.addresses.one())|number_format(1) }} km

{# A map with a pin for each of the user’s addresses #}
{{ craft.geomaps.embed({
  markers: currentUser.addresses.all()|map(a => { location: a, label: a.title }),
  fitMarkers: true,
}) }}
```

`location` also takes another entry’s location field, so `{ location: store.storeLocation, radius: 10 }` finds what’s near a store.

## Coordinates for addresses <Badge type="tip" text="Pro" />

Craft keeps a latitude and longitude with every address but never fills them in. With Geo Maps Pro, addresses saved without coordinates get them in the background, along with [missing coordinates](/guides/missing-coordinates), and `php craft geomaps/geocode` fills in existing ones.

Coordinates are saved in Craft’s own `latitude` and `longitude`, so they’re available to everything that reads addresses, not only Geo Maps. Saving them doesn’t make a new revision of the entry.

By default, only addresses in Addresses fields on entries get coordinates, such as your stores’ or venues’ addresses. Users’ and Commerce orders’ addresses are personal data, so they’re only looked up if you choose to:

```php
// config/geomaps.php
return [
    // 'entries' (default), 'all' (also users’ and orders’ addresses), or false
    'geocodeAddresses' => 'entries',
];
```

Addresses are looked up with OpenStreetMap, or Mapbox with permanent geocoding, because only their results may be stored. With `all`, customers’ addresses are sent to that service; mention it in your [privacy policy](/getting-started/privacy-and-data).

## Nearest addresses <Badge type="tip" text="Pro" />

`near()` on an address query finds addresses near a place, nearest first, with `distance` on each:

```twig
{% set nearby = craft.addresses
  .near({ location: 'Glenelg SA', radius: 30 })
  .all() %}

{% for address in nearby %}
  {{ address.title }}: {{ address.distance|number_format(1) }} km
{% endfor %}
```

| Option | Description |
|---|---|
| `location` | An address, `{ lat, lng }`, a Craft address or a location field’s value |
| `radius` | Distance to search within (default `50`). `null` sorts every address with coordinates by distance |
| `unit` | `km` (default) or `mi` |
| `country` | Two-letter country code to look the `location` up in |

Combine it with any other address criteria, such as `.ownerId(store.id)` or `.countryCode('AU')`. Only addresses with coordinates are found. An `orderBy()` of your own replaces the nearest-first order.

From PHP:

```php
use craft\elements\Address;

$addresses = Address::find()->near(['location' => $order->getShippingAddress(), 'radius' => 25])->all();
```
