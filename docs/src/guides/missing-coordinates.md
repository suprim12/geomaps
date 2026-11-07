# Missing Coordinates <Badge type="tip" text="Pro" />

Locations need coordinates for maps, distance search and zone lookups. Content from a feed, a spreadsheet or an older site often has an address but no latitude and longitude. Geo Maps Pro looks these up and saves them for you.

## Automatically

With Pro active, when an entry (or any other element) is saved with an address but no coordinates, a background job looks the address up and saves the coordinates. This covers [Feed Me](/guides/feed-me) imports, front-end forms and anything else that saves elements.

Turn this off under **Settings → Plugins → Geo Maps** (**Find missing coordinates automatically**), or in `config/geomaps.php`:

```php
return [
    'geocodeMissing' => false,
];
```

## With the console command

To fill in everything at once, for example after an import, run:

```bash
php craft geomaps/geocode
```

| Option | Description |
|---|---|
| `--field=storeLocation` | Only this Geo Maps field |
| `--limit=100` | Stop after this many lookups |
| `--dry-run` | List the addresses that would be looked up, without looking anything up |
| `--retry` | Also retry addresses that couldn’t be found before |

```text
Looking up Entry #185 (site 1): 50 Rundle Mall, Adelaide SA 5000, Australia … saved
Looking up Entry #189 (site 1): Qwxzvb Plnkt Street, Nowheresville 99999 … not found
Saved 1, not found 1.
```

## How addresses are looked up

- **What’s looked up:** the field’s full address, or its street, suburb, postcode and country joined together. The address you entered isn’t changed; only the coordinates are added.
- **Country:** the location’s own country code narrows the search. If it has none and the field limits search to a single country, that country is tried first, then anywhere.
- **Service:** coordinates come from OpenStreetMap (Nominatim), even when you’ve added a Google key, because OpenStreetMap data may be stored in your content while Google’s terms only allow caching its results for 30 days.
- **Speed:** Nominatim allows one request per second, so 1,000 addresses take about 17 minutes. If Nominatim asks Geo Maps to slow down, lookups pause for 10 minutes and the rest are picked up by the next run.
- **Addresses that can’t be found** are remembered for 30 days, so they aren’t looked up again on every save. Fix the address and save the entry to try again, or run the command with `--retry`.

For tens of thousands of addresses, Nominatim’s [usage policy](https://operations.osmfoundation.org/policies/nominatim/) asks for a bulk geocoding service instead; import coordinates with the content where you can.

## Without Pro

On the free edition, addresses are saved as they are and nothing is looked up. The console command explains that it needs Pro.
