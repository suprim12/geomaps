# Third-Party Licenses

Geo Maps is licensed under the [Craft License](LICENSE.md). Its control panel and front-end scripts bundle the following open-source packages, each under its own permissive license:

| Package | License |
|---|---|
| Leaflet | BSD-2-Clause |
| Leaflet-Geoman (free) | MIT |
| Leaflet.fullscreen | MIT |
| Leaflet.GridLayer.GoogleMutant | Beerware |
| Leaflet.markercluster | MIT |
| @googlemaps/markerclusterer (and its bundled kdbush, supercluster, fast-equals) | Apache-2.0, ISC, MIT |
| Alpine.js and @alpinejs/intersect | MIT |
| CodeMirror 6 and Lezer | MIT |
| screenfull, style-mod, crelt, w3c-keyname, find-cluster-break | MIT |

The full license texts, with versions, are generated on every build:

- [`src/web/assets/licenses/supgeo.md`](src/web/assets/licenses/supgeo.md) — control panel field
- [`src/web/assets/licenses/embed.md`](src/web/assets/licenses/embed.md) — front-end maps
- [`src/web/assets/licenses/cluster.md`](src/web/assets/licenses/cluster.md) — marker clustering (Pro)
- [`src/web/assets/licenses/address.md`](src/web/assets/licenses/address.md) — front-end address autocomplete

Map tiles and geocoding come from third-party services (OpenStreetMap, Google Maps Platform) under their own terms; see the documentation’s Configuration page.
