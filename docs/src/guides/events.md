# Events

Hook into Geo Maps from a module or plugin. Events are in Lite and Pro.

| Event | Class | When |
|---|---|---|
| `EVENT_BEFORE_GEOCODE` | `suprim\geomaps\services\GeoService` | Before an address is looked up |
| `EVENT_AFTER_GEOCODE` | `suprim\geomaps\services\GeoService` | After an address was looked up |
| `EVENT_BEFORE_RENDER` | `suprim\geomaps\models\EmbedMap` | Before an interactive map is output |
| `EVENT_DEFINE_RESULT` | `suprim\geomaps\controllers\SearchController` | For each result of a [search endpoint](/guides/search-endpoint) |
| `EVENT_DEFINE_RESULT` | `suprim\geomaps\models\Locator` | For each result of the [locator](/guides/locator) |

## Looking up addresses

`EVENT_BEFORE_GEOCODE` runs before Geo Maps asks the map service for an address’s coordinates: for location searches, map centres, missing coordinates and delivery conditions. Answer it yourself, such as from your own geocoder or a list of known places, by setting `result` and `handled`. The map service isn’t called.

```php
use suprim\geomaps\events\GeocodeEvent;
use suprim\geomaps\services\GeoService;
use yii\base\Event;

Event::on(GeoService::class, GeoService::EVENT_BEFORE_GEOCODE, function(GeocodeEvent $event) {
    // $event->address, $event->country (two-letter code or null)
    // $event->purpose: 'search', or 'save' when the coordinates are saved with content
    if ($event->address === 'Head office') {
        $event->result = ['lat' => -34.9285, 'lng' => 138.6007];
        $event->handled = true;
    }
});
```

The first handler that sets `handled` answers the lookup, and later handlers aren’t called. Set `result` to `null` with `handled` to say the address wasn’t found.

When `purpose` is `save`, the coordinates are stored in your content. Only answer with data you’re allowed to store.

`EVENT_AFTER_GEOCODE` gets the same event after the lookup. Change `result`, or set it to `null` to reject it:

```php
Event::on(GeoService::class, GeoService::EVENT_AFTER_GEOCODE, function(GeocodeEvent $event) {
    // Only accept places in Australia.
    if ($event->result && ($event->result['lat'] > -10 || $event->result['lng'] < 112)) {
        $event->result = null;
    }
});
```

## Changing maps

`EVENT_BEFORE_RENDER` gets the options an `embed()` map was given, as `options`, before it’s output. Change them to change every map on the site, such as adding a pin or setting a default zoom.

```php
use suprim\geomaps\events\MapEvent;
use suprim\geomaps\models\EmbedMap;
use yii\base\Event;

Event::on(EmbedMap::class, EmbedMap::EVENT_BEFORE_RENDER, function(MapEvent $event) {
    $event->options['markers'][] = [
        'location' => ['lat' => -34.9285, 'lng' => 138.6007],
        'label' => 'Head office',
        'color' => '#111827',
    ];
    $event->options['cluster'] ??= true;
});
```

`options` only holds what the template passed. Options it left out still use their defaults.

## Changing search results

`EVENT_DEFINE_RESULT` runs for each result, with the element, its location (with `distance` when the search had a location) and the result as `result`.

For a search endpoint, `result` is the JSON for the result:

```php
use suprim\geomaps\controllers\SearchController;
use suprim\geomaps\events\SearchResultEvent;
use yii\base\Event;

Event::on(SearchController::class, SearchController::EVENT_DEFINE_RESULT, function(SearchResultEvent $event) {
    $event->result['openNow'] = MyModule::isOpen($event->element);
    unset($event->result['url']);
});
```

For the locator, `result` holds the variables its result and pin templates get: `entry`, `location`, `distance`, `unit`, `number`, `address`, `url` and `directionsUrl`. Change them, or add your own for a `resultTemplate`:

```php
use suprim\geomaps\events\SearchResultEvent;
use suprim\geomaps\models\Locator;
use yii\base\Event;

Event::on(Locator::class, Locator::EVENT_DEFINE_RESULT, function(SearchResultEvent $event) {
    $event->result['directionsUrl'] = 'https://maps.apple.com/?daddr=' . $event->location->lat . ',' . $event->location->lng;
});
```
