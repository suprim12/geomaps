# Using Geo Maps from PHP

Everything you can do in Twig also works in a module or plugin. These examples assume a Stores section with a Geo Maps field called `storeLocation`, and a Service Areas section whose `areaShape` field holds drawn areas.

## Only accept addresses in your service area

Reject entries whose location falls outside every service area. Add this to your module’s `init()`:

```php
use craft\elements\Entry;
use suprim\geomaps\models\GeoValue;
use yii\base\Event;

Event::on(Entry::class, Entry::EVENT_AFTER_VALIDATE, function(Event $event) {
    /** @var Entry $entry */
    $entry = $event->sender;

    if ($entry->getSection()?->handle !== 'jobs') {
        return;
    }

    $location = $entry->getFieldValue('jobLocation');
    if (!$location instanceof GeoValue || !$location->hasCoordinates()) {
        return;
    }

    $inArea = Entry::find()
        ->section('serviceAreas')
        ->areaShape(['contains' => $location])
        ->exists();

    if (!$inArea) {
        $entry->addError('jobLocation', 'This address is outside our service areas.');
    }
});
```

Authors see the error on the field when they save, and the entry isn’t saved. Passing the field value to `contains` uses its coordinates, so nothing is geocoded.

## Find the nearest entry

```php
use craft\elements\Entry;

$store = Entry::find()
    ->section('stores')
    ->storeLocation(['location' => ['lat' => -34.9214, 'lng' => 138.6306], 'radius' => 20])
    ->one();

if ($store) {
    $km = $store->getFieldValue('storeLocation')->distance; // 0.06
}
```

Every option from [Location Search](/guides/search) works here: `location`, `coordinate`, `radius`, `unit`, `country` and `contains`.

## Geocode an address

```php
use suprim\geomaps\services\GeoService;

$point = GeoService::geocode('175 The Parade, Norwood SA', 'AU');
// ['lat' => -34.9214557, 'lng' => 138.6306147], or null if it can't be found
```

It uses your Google Geocoding key if one is set, otherwise OpenStreetMap, and caches the result for 30 days.

## Check a single area

```php
$zone = $entry->getFieldValue('areaShape');

if ($zone?->contains(['lat' => -34.92, 'lng' => 138.63])) {
    // …
}
```

`contains()` also accepts an address (with an optional country code as the second argument) or another field value.

## Set a value

```php
$entry->setFieldValue('storeLocation', [
    'lat' => -34.9285,
    'lng' => 138.6007,
    'full_address' => '1 King William St, Adelaide SA 5000, Australia',
    'address1' => '1 King William St',
    'suburb' => 'Adelaide',
    'postcode' => '5000',
    'country' => 'Australia',
    'countryCode' => 'AU',
]);
Craft::$app->getElements()->saveElement($entry);
```

The entry is added to the location search index when it’s saved. After writing straight to the database instead, run `php craft geomaps/lookup/rebuild`.
