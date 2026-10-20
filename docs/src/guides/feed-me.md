# Feed Me

Geo Maps works with [Feed Me](https://plugins.craftcms.com/feed-me). When mapping a feed, a Geo Maps field shows a row for each part of the location:

Latitude, Longitude, Zoom, Full Address, Address Line 1, Suburb, Postcode, Country and GeoJSON.

Map any of them to columns in your feed. A few things are handled for you:

- **Country** can be a name (`Australia`) or a two-letter code (`AU`); the other is filled in where possible.
- **Postcodes** are kept as text, so leading zeros survive.
- **GeoJSON** must be valid GeoJSON text.
- **Latitude** and **Longitude** that aren’t numbers are reported as validation errors on the entry, rather than saved as `0`.
- Imported coordinates are indexed for [Location Search](/guides/search) as entries are saved.

If a feed only has addresses and no coordinates, import them into **Full Address**. Entries will show the address, but won’t appear in location search until they have coordinates — open them and pick the address from search, or geocode the feed beforehand.
