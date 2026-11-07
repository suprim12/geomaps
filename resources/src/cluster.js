/**
 * Marker clustering for front-end `embed()` maps (Geo Maps Pro), loaded after embed.js
 * only on maps that ask for it.
 *
 * Leaflet.markercluster attaches itself to the global `L` that embed.js sets up.
 * Google maps use @googlemaps/markerclusterer.
 */
import "leaflet.markercluster/dist/leaflet.markercluster-src.js";
import "leaflet.markercluster/dist/MarkerCluster.css";
import "./cluster.css";
import { MarkerClusterer } from "@googlemaps/markerclusterer";

const sizeFor = (count) => (count < 10 ? 34 : count < 100 ? 40 : 48);

/** Leaflet: a round badge with the count, in the map's cluster colour. */
function leafletIcon(color) {
  return (cluster) => {
    const count = cluster.getChildCount();
    const size = sizeFor(count);
    return L.divIcon({
      html: `<span style="--geomaps-cluster:${color}">${count}</span>`,
      className: "geomaps-cluster",
      iconSize: [size, size],
    });
  };
}

/** Google: the same badge drawn as an SVG marker. */
function googleRenderer(color) {
  return {
    render({ count, position }) {
      const size = sizeFor(count);
      const r = size / 2;
      const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
        <circle cx="${r}" cy="${r}" r="${r - 1}" fill="${color}" fill-opacity="0.25"/>
        <circle cx="${r}" cy="${r}" r="${r - 5}" fill="${color}"/>
        <text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="system-ui, sans-serif" font-size="13" font-weight="600" fill="#fff">${count}</text>
      </svg>`;
      return new google.maps.Marker({
        position,
        icon: {
          url: "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(svg),
          scaledSize: new google.maps.Size(size, size),
          anchor: new google.maps.Point(r, r),
        },
        title: `${count} locations`,
        zIndex: Number(google.maps.Marker.MAX_ZINDEX) + count,
      });
    },
  };
}

window.GeoMapsCluster = {
  /** Returns a Leaflet layer group that clusters the markers added to it. */
  leaflet(options = {}) {
    const settings = {
      showCoverageOnHover: false,
      spiderfyOnMaxZoom: true,
      maxClusterRadius: options.radius ?? 60,
      iconCreateFunction: leafletIcon(options.color || "#4f46e5"),
    };
    // Only set when given: an explicit `undefined` turns clustering off entirely.
    if (Number.isFinite(options.maxZoom)) settings.disableClusteringAtZoom = options.maxZoom;
    return L.markerClusterGroup(settings);
  },

  /** Clusters existing Google markers on a map. */
  google(map, markers, options = {}) {
    return new MarkerClusterer({ map, markers, renderer: googleRenderer(options.color || "#4f46e5") });
  },
};
