/**
 * Leaflet for front-end `embed()` maps when no Google Maps key is configured.
 */
import * as L from "leaflet";
import "leaflet/dist/leaflet.css";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";

L.Icon.Default.imagePath = "";
L.Icon.Default.mergeOptions({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
});

window.L = window.L || L;

// Google calls window.gm_authFailure when it rejects the API key; embeds listen for this and switch to OpenStreetMap.
if (!window.__supgeoAuthHooked) {
  window.__supgeoAuthHooked = true;
  const previous = window.gm_authFailure;
  window.gm_authFailure = () => {
    window.__supgeoGoogleFailed = true;
    window.dispatchEvent(new CustomEvent("supgeo:google-failed"));
    if (typeof previous === "function") previous();
  };
}
