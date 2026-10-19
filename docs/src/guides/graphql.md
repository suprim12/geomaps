# GraphQL

Geo Maps fields are available in Craft’s GraphQL API.

## Reading values

```graphql
{
  entries(section: "stores") {
    title
    ... on store_Entry {
      storeLocation {
        lat
        lng
        full_address
        address1
        suburb
        postcode
        country
        countryCode
        geoJson
      }
    }
  }
}
```

## Location search

The field’s query argument takes the same options as [Location Search](/guides/search), except that `location` must be a string. Use `coordinate` for latitude and longitude.

```graphql
{
  entries(
    section: "stores"
    storeLocation: { coordinate: { lat: -34.9285, lng: 138.6007 }, radius: 25, unit: "km" }
  ) {
    title
    ... on store_Entry {
      storeLocation { full_address distance }
    }
  }
}
```

```graphql
query NearbyStores($near: GeoFieldQuery) {
  entries(section: "stores", storeLocation: $near) { title }
  entryCount(section: "stores", storeLocation: $near)
}
```

with variables:

```json
{ "near": { "location": "Glenelg, South Australia", "radius": 10, "unit": "mi" } }
```

Results are nearest first, and `distance` is set in the requested unit.

## Saving values

Mutations accept the value as a JSON string:

```graphql
mutation {
  save_stores_store_Entry(
    title: "Norwood"
    storeLocation: "{\"lat\": -34.921, \"lng\": 138.631, \"postcode\": \"5067\"}"
  ) {
    id
  }
}
```
