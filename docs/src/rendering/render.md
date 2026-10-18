# Static Map Images 

**Geo Maps** makes it easy to render static map images in Twig. There are two approaches: via a Map field, or using the global `craft.geomaps` variable.

### 1. Using a Map Field
```twig
{# Using a Map field #}
{% set location = entry.mapFieldHandle %}

<img 
loading="lazy" 
src="{{location.map.img({
    mapType:'roadmap',
    mapId:"121",
    zoom:12,
    markers:[{
    location:{
    lat:"-34.943222", lng:"138.589525"}
    },
    {
    location:{
    lat:"-34.928499", lng:"138.600746"}
    }],
    colorScheme: 'DARK'
})}}" 
srcset="{{location.map.imgSrcSet}}" alt="{{location.full_address}}"
/>
```


### 2. Using the Global craft.geomaps Service

```twig
<img
    src="{{craft.geomaps.img({
        markers:[
        { location:{ lat:"-34.943222", lng:"138.589525"} }, { location:{ lat:"-34.928499", lng:"138.600746"} }
        ],
        zoom: 12,
        size: '600x400',
    }) }}"
    srcset="{{craft.geomaps.imgSrcSet({
        markers:[
        { location:{ lat:"-34.943222", lng:"138.589525"} }, { location:{ lat:"-34.928499", lng:"138.600746"} }
        ],
        zoom: 12,
        size: '600x400',
    }) }}"
    alt="{{location.full_address}}"
/>
```

## Options

Core Parameters

| Option | Description |
|---|---|
| `center` | Map center (address string or {lat, lng} object)|
| `zoom` | Zoom level (typically 1–21) |
| `mapType` | roadmap, satellite, hybrid, terrain |
| `size` | Image dimensions (widthxheight) |
| `scale` | 1 or 2 for high-DPI / retina displays |
| `mapId` | Google Maps custom style ID |


## Markers

```twig
markers: [
    {
        location: { lat: "-34.943222", lng: "138.589525" },   // or "Address String"
        color: "red",
        label: "A",
        icon: "https://example.com/custom-marker.png",
        size: "mid"
    }
]
```

---

![An image](/static-map.png)

---


# Embed Dynamic Maps 


**Geo Maps** makes it easy to render dynamic map images in Twig. There are two approaches: via a Map field, or using the global `craft.geomaps` variable.


### 1. Using a Map Field

```twig
{# Using a Map field #}
{% set location = entry.mapFieldHandle %}

{{location.embed({id:'googlemap'})}}
```

### 2. Using the Global craft.geomaps Service

```twig
{{ craft.geomaps.embed({
    center: 'Adelaide SA, Australia',
    width: 800,
    height: 400,
    zoom: 12,
})}} 
```

## Options

The available options include those from the Static Map as well as two additional options:

- `id` (string) - A unique identifier for the map instance, used for targeting with JavaScript.



## Example usage

To render a geoJson

```twig
{% set location = entry.supgeomap %}

<div id="geojson" style="display: none;">
    {{location.geoJson}}
</div>

<script>
var map = L.map(document.getElementById('map')).setView([47.9034, 8.10577], 10);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Data ©<a href="http://osm.org/copyright">OpenStreetMap</a>',
    maxZoom: 18
}).addTo(map);

var layer = L.featureGroup().addTo(map);

var geojson = document.getElementById('geojson');
if (geojson.innerHTML) {
    var geojsonLayer = L.geoJson(JSON.parse(geojson.innerHTML), {
        pointToLayer: (feature, latlng) => {
            if (feature.properties.radius) {
                return new L.Circle(latlng, feature.properties.radius)
            } else if (feature.properties.shape == "CircleMarker") {
                return new L.CircleMarker(latlng)
            } else {
                return new L.Marker(latlng)
            }
            return
        },
    })
    geojsonLayer.eachLayer(function (l) {
        layer.addLayer(l)
    })
    map.fitBounds(layer.getBounds())
}
</script>

```