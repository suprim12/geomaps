/**
 *  https://suprim.com.au
 * author suprim <support@suprim.com.au> <https://suprim.com.au>
 * copyright Copyright (c) 2026 suprim
 * 
 */
import "leaflet/dist/leaflet.css";
import "@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css";
import "./supgeo.css";

import Alpine from "alpinejs";
import intersect from "@alpinejs/intersect";
import * as L from "leaflet";
import "leaflet.gridlayer.googlemutant";
import "@geoman-io/leaflet-geoman-free";
// The GeoJSON code editor is loaded the first time the panel opens.
let cm = null;
const loadEditor = async () => (cm ??= await import("./geojson-editor.js"));
import "leaflet.fullscreen";
import "leaflet.fullscreen/Control.FullScreen.css";

import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";

// Leaflet guesses the marker image path from its CSS, which fails once the CSS is bundled.
L.Icon.Default.imagePath = "";
L.Icon.Default.mergeOptions({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
});

/**
 * Resolves a Google Maps library, or null when the API isn't available
 * (no key, blocked key, ad blocker, offline).
 */
async function loadGoogleLibrary(name) {
  if (typeof google === "undefined" || !google.maps) return null;
  try {
    if (typeof google.maps.importLibrary === "function") {
      return await google.maps.importLibrary(name);
    }
    return name === "places" ? google.maps.places || null : google.maps;
  } catch (e) {
    console.warn("Geo Maps: couldn't load Google Maps library '" + name + "'", e);
    return null;
  }
}

// Google calls window.gm_authFailure when it rejects the API key (wrong referrer, billing, disabled API).
// Let every map on the page fall back to OpenStreetMap instead of showing Google's error panel.
if (!window.__supgeoAuthHooked) {
  window.__supgeoAuthHooked = true;
  const previous = window.gm_authFailure;
  window.gm_authFailure = () => {
    window.__supgeoGoogleFailed = true;
    window.dispatchEvent(new CustomEvent("supgeo:google-failed"));
    if (typeof previous === "function") previous();
  };
}

Alpine.plugin(intersect);

document.addEventListener("alpine:init", () => {
  Alpine.data("appgeo", () => ({
    notokenAdded: false,
    map: null,
    marker: null,
    autocomplete: null,
    markerLayer: null,
    geojsonLayer: null,
    editor: null,
    defaultLat: null,
    defaultLng: null,
    defaultZoom: null,
    showEditor: false,
    provider: "google",
    googleFailed: false,
    showMarker: true,
    _googleLayer: null,
    _tileAttribution: null,
    restrictCountries: [],
    language: null,
    isInput: false,
    hideAdress: false,
    hideSearch: false,
    uploadProgress: 0,
    uploadStatus: null,
    uploadError: null,
    lat: null,
    lng: null,
    zoom: null,
    full_address: null,
    address1: null,
    postcode: null,
    suburb: null,
    country: null,
    countryCode: null,
    geojsonText: null,
    suggestions: [],
    searchQuery: "",
    suggestionsOpen: false,
    loadingSuggestions: false,
    _autocompleteService: null,
    _placesService: null,
    _sessionToken: null,
    _suggestDebounce: null,
    activeSuggestion: -1,
    selectedLayer: null,
    featureEditorOpen: false,
    featureEditorTab: "properties",
    featureProps: [],
    featurePropNewKey: "",
    featurePropNewVal: "",
    featureGeomType: "",
    featureVertices: [],
    featureCopied: false,
    featureMeasure: "",
    shapeColors: ["#2563eb", "#16a34a", "#d97706", "#dc2626", "#9333ea", "#0891b2", "#475569"],
    _mapIsSource: false,
    featureEditingPropIndex: null,
    featureEditingPropType: "text", // 'text' | 'json' | 'number' | 'boolean' | 'color'
    featureEditingPropValue: "",
    featureEditingPropRaw: "", // raw string in the editor
    featureEditingPropError: null,
    featurePropEditorOpen: false,
    // ########################################## Init Map
    initMap(data, isInput = false, settingData = {}) {
      const { lat, lng, zoom } = data;
      const isCoord = (v) => v !== null && v !== undefined && v !== "" && !isNaN(v);

      // The saved value (lat/lng) wins; otherwise centre on the field's default location.
      const hasLat = isCoord(lat);
      const hasLng = isCoord(lng);
      this.defaultLat = parseFloat(hasLat ? lat : data.centerLat ?? settingData?.lat ?? 0);
      this.defaultLng = parseFloat(hasLng ? lng : data.centerLng ?? settingData?.lng ?? 0);
      this.defaultZoom = zoom ?? 9;
      this.isInput = isInput ?? false;

      this.hideAdress = data?.hideAddress ?? false;
      this.hideSearch = data?.hideSearch ?? false;

      this.showMarker = data.showMarker !== false;
      this.provider = data.provider === "osm" ? "osm" : "google";
      this.restrictCountries = (Array.isArray(data.restrictCountries) ? data.restrictCountries : [])
        .map((c) => String(c).toUpperCase());
      this.language = data.language || null;
      const tiles = L.tileLayer(
        data.tileUrl || "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
        { maxZoom: 19 },
      );

      // Field settings: limit how far authors can zoom.
      const minZoom = Number.isFinite(Number(data.minZoom)) && data.minZoom !== null ? Number(data.minZoom) : undefined;
      const maxZoom = Number.isFinite(Number(data.maxZoom)) && data.maxZoom !== null ? Number(data.maxZoom) : undefined;
      if (minZoom !== undefined) this.defaultZoom = Math.max(this.defaultZoom, minZoom);
      if (maxZoom !== undefined) this.defaultZoom = Math.min(this.defaultZoom, maxZoom);

      const map = L.map(this.$refs["appgeo-map"], {
        minZoom,
        maxZoom,
        tap: false,
        zoomControl: false,
        // pmIgnore: false,
        fullscreenControl: false,
        attributionControl: false,
      })
        .setView([this.defaultLat, this.defaultLng], this.defaultZoom)
        .addLayer(tiles);

      this.map = map;

      // Saved with the value (and as the field's initial zoom in field settings).
      this.zoom = map.getZoom();
      map.on("zoomend", () => (this.zoom = map.getZoom()));

      // Re-measure when the container changes size (field settings, tabs, slideouts, collapsed Matrix blocks).
      if (typeof ResizeObserver !== "undefined") {
        new ResizeObserver(() => this.map?.invalidateSize()).observe(this.$refs["appgeo-map"]);
      }

      L.control
        .zoom({
          position: "topright",
        })
        .addTo(map);

      L.control
        .fullscreen({
          position: "topright",
          title: "Show Fullscreen",
          titleCancel: "Exit Fullscreen",
        })
        .addTo(map);

      // Google tiles replace the OpenStreetMap base layer when a Google key is configured.
      this._tileAttribution =
        data.tileAttribution ||
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors';

      if (this.provider === "google") {
        window.addEventListener("supgeo:google-failed", () => this.useOsmFallback());
        if (window.__supgeoGoogleFailed) {
          this.useOsmFallback();
        } else {
          this.map.whenReady(async () => {
            if (!(await loadGoogleLibrary("maps"))) return this.useOsmFallback();
            if (this.provider !== "google") return;
            this._googleLayer = L.gridLayer.googleMutant({ type: "roadmap", styles: [] }).addTo(map);
          });
        }
      } else {
        this.addTileAttribution();
      }

      // Full screen control
      this.map.on("enterFullscreen", () =>
        this.$refs["geowrapper"].classList.add("--fullscreen"),
      );
      this.map.on("exitFullscreen", () =>
        this.$refs["geowrapper"].classList.remove("--fullscreen"),
      );

      // Default makers
      this.markerLayer = L.layerGroup().addTo(this.map);
      this.createMarker(this.defaultLat, this.defaultLng);
      if (!this.hideSearch) {
        // Initalize autocomplete
        this.initAutocomplete(isInput);
      }

      // Initalize geojson (the editor itself is created when the panel is first opened)
      this.geojsonText = data.geoJson;

      // Inital Data set
      this.lat = hasLat ? parseFloat(lat)?.toFixed(6) : null;
      this.lng = hasLng ? parseFloat(lng)?.toFixed(6) : null;

      this.full_address = data.full_address || null;
      this.address1 = data.address1 || null;
      this.postcode = data.postcode || null;
      this.suburb = data.suburb || null;
      this.country = data.country || null;
      this.countryCode = data.countryCode || null;

      // Initialize Geo JSON data
      if (data.geoJson) {
        // Shape-only fields (and entries without a location) open zoomed to their shapes.
        // Invalid GeoJSON (e.g. after a failed save) stays in the editor so it can be fixed.
        try {
          this.drawGeoJSONOnMap(JSON.parse(data.geoJson), !this.showMarker || !hasLat);
        } catch (e) {
          console.warn("Geo Maps: couldn't draw the saved GeoJSON", e);
        }
      }
      // Drawing tools are only for the field itself, not the field-settings location picker.
      if (this.isInput && data.enableGeoJson !== false) {
        this.initGeoman();
      } else {
        this.map.on("click", (e) => {
          this.createMarker(e.latlng.lat, e.latlng.lng);
          this.setLocation(e.latlng.lat, e.latlng.lng);
        });
      }
    },
    validateGeoJSON(doc) {
      if (this._mapIsSource) return;
      if (!this.editor) return;

      if (!doc?.trim()) {
        this.resetMap();
        this.editor.dispatch(cm.setDiagnostics(this.editor.state, []));
        return;
      }
      try {
        const geojson = JSON.parse(doc);
        // GeoJSON validation
        if (!geojson.type) {
          throw new Error("GeoJSON must have a 'type' property");
        }
        if (
          geojson.type === "FeatureCollection" &&
          !Array.isArray(geojson.features)
        ) {
          throw new Error("'features' must be an array in FeatureCollection");
        }

        // Close feature Editor - to show updates on sync
        if (this.featureEditorOpen) {
          this.closeFeatureEditor();
        }
        this.drawGeoJSONOnMap(geojson);
        // Clear any previous errors if valid
        this.editor.dispatch(cm.setDiagnostics(this.editor.state, []));
      } catch (err) {
        const message = err.message || "Invalid JSON";

        const diagnostics = [
          {
            from: 0,
            to: Math.min(200, doc.length),
            severity: "error",
            message: message,
          },
        ];

        // Correct way to show diagnostics
        this.editor.dispatch(cm.setDiagnostics(this.editor.state, diagnostics));

        console.warn("GeoJSON Error:", message);
      }
    },
    // ########################################## Init Autocomplete
    initAutocompleteOld(isInput) {
      const searchContainer = this.$refs["apgeo-search"];
      const input = document.createElement("input");
      input.type = "text";
      input.placeholder = "Search for a place...";
      input.className = "supgeo-search-input text fullwidth";
      input.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          e.stopPropagation();
        }
      });
      input.addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          e.stopPropagation();
        }
      });
      searchContainer.appendChild(input);
      this.autocomplete = new google.maps.places.Autocomplete(input, {
        fields: [
          "formatted_address",
          "geometry",
          "name",
          "place_id",
          "address_components",
        ],
        // componentRestrictions: { country: ['au'] },
      });
      this.autocomplete.addListener("place_changed", (e) => {
        const place = this.autocomplete.getPlace();
        if (!place.geometry || !place.geometry.location) {
          console.warn("No details available for input: '" + place.name + "'");
          return;
        }
        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();

        if (lat && lng) {
          this.fillAddressFields(place, lat, lng);
          this.focusOn(lat, lng);
          if (this.marker) this.marker.remove();
          this.createMarker(lat, lng);
        }
      });
    },
    async initAutocomplete(isInput) {
      if (this.provider !== "google") return;
      // The Maps API loads with `loading=async`, so classes must be imported before use.
      if (!(await loadGoogleLibrary("places"))) return;
      this._autocompleteService = new google.maps.places.AutocompleteService();
      this._placesService = new google.maps.places.PlacesService(
        this.$refs["supgeo-places-attr"],
      );
      this._sessionToken = new google.maps.places.AutocompleteSessionToken();
    },
    onSearchKeydown(e) {
      // Enter picks a suggestion; it must never submit (save) the entry form.
      if (e.key === "Enter") e.preventDefault();
      if (!this.suggestionsOpen) return;

      if (e.key === "ArrowDown") {
        e.preventDefault();
        this.activeSuggestion = Math.min(
          this.activeSuggestion + 1,
          this.suggestions.length - 1,
        );
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        this.activeSuggestion = Math.max(this.activeSuggestion - 1, 0);
      } else if (e.key === "Enter") {
        if (
          this.activeSuggestion >= 0 &&
          this.suggestions[this.activeSuggestion]
        ) {
          this.selectSuggestion(this.suggestions[this.activeSuggestion]);
        }
      } else if (e.key === "Escape") {
        this.suggestionsOpen = false;
        this.activeSuggestion = -1;
      }
    },
    onSearchInput() {
      const q = this.searchQuery.trim();
      this.activeSuggestion = -1;

      if (!q) {
        this.suggestions = [];
        this.suggestionsOpen = false;
        clearTimeout(this._suggestDebounce);
        return;
      }

      clearTimeout(this._suggestDebounce);
      this._suggestDebounce = setTimeout(() => this._fetchSuggestions(q), 220);
    },
    _fetchSuggestions(query) {
      if (this.provider === "osm") return this._fetchOsmSuggestions(query);
      if (!this._autocompleteService) return;

      this.loadingSuggestions = true;

      this._autocompleteService.getPlacePredictions(
        {
          input: query,
          sessionToken: this._sessionToken,
          ...(this.restrictCountries.length
            ? { componentRestrictions: { country: this.restrictCountries.map((c) => c.toLowerCase()) } }
            : {}),
        },
        (predictions, status) => {
          this.loadingSuggestions = false;

          if (
            status !== google.maps.places.PlacesServiceStatus.OK ||
            !predictions
          ) {
            this.suggestions = [];
            this.suggestionsOpen = false;
            return;
          }

          this.suggestions = predictions.map((p) => ({
            placeId: p.place_id,
            mainText: p.structured_formatting.main_text,
            secondaryText: p.structured_formatting.secondary_text || "",
            fullText: p.description,
          }));
          this.suggestionsOpen = this.suggestions.length > 0;
        },
      );
    },
    // Photon (komoot) is an OpenStreetMap geocoder built for search-as-you-type.
    async _fetchOsmSuggestions(query) {
      this.loadingSuggestions = true;
      const params = new URLSearchParams({ q: query, limit: "8" });
      // Filter on Photon's side so nearby results from other countries don't crowd out the allowed ones.
      this.restrictCountries.forEach((c) => params.append("countrycode", c.toLowerCase()));
      const lang = (this.language || "").slice(0, 2);
      if (["en", "de", "fr", "it"].includes(lang)) params.set("lang", lang);
      if (this.map) {
        const c = this.map.getCenter();
        params.set("lat", c.lat.toFixed(4));
        params.set("lon", c.lng.toFixed(4));
      }

      try {
        const res = await fetch("https://photon.komoot.io/api/?" + params);
        const json = await res.json();
        if (this.searchQuery.trim() !== query) return; // stale response
        let features = json.features || [];
        if (this.restrictCountries.length) {
          features = features.filter((f) =>
            this.restrictCountries.includes((f.properties.countrycode || "").toUpperCase()),
          );
        }
        const seen = new Set();
        this.suggestions = features.map((f, i) => {
          const p = f.properties;
          const street = [p.housenumber, p.street].filter(Boolean).join(" ");
          const main = p.name || street || p.city || p.country || query;
          const secondary = [p.name ? street : null, p.city || p.district, p.state, p.country]
            .filter(Boolean)
            .join(", ");
          return {
            placeId: "osm-" + (p.osm_type || "") + (p.osm_id || i),
            mainText: main,
            secondaryText: secondary,
            fullText: [main, secondary].filter(Boolean).join(", "),
            osm: f,
          };
        }).filter((s) => {
          // Photon often returns several OSM objects for the same place.
          const key = s.fullText.toLowerCase();
          if (seen.has(key)) return false;
          seen.add(key);
          return true;
        }).slice(0, 6);
        this.suggestionsOpen = this.suggestions.length > 0;
      } catch (e) {
        console.warn("Geo Maps: OpenStreetMap search failed", e);
        this.suggestions = [];
        this.suggestionsOpen = false;
      } finally {
        this.loadingSuggestions = false;
      }
    },
    fillOsmAddressFields(a, fullAddress, lat, lng) {
      const street = [a.housenumber || a.house_number, a.street || a.road]
        .filter(Boolean)
        .join(" ");
      this.full_address = fullAddress || null;
      this.lat = lat?.toFixed(6) ?? null;
      this.lng = lng?.toFixed(6) ?? null;
      this.address1 = street || a.name || null;
      this.suburb =
        a.suburb || a.city || a.town || a.village || a.district || a.locality || null;
      this.postcode = a.postcode || null;
      this.country = a.country || null;
      this.countryCode = (a.countrycode || a.country_code || "").toUpperCase() || null;
    },
    selectSuggestion(suggestion) {
      this.searchQuery = suggestion.fullText;
      this.suggestionsOpen = false;
      this.suggestions = [];

      if (suggestion.osm) {
        const [lng, lat] = suggestion.osm.geometry.coordinates;
        this.fillOsmAddressFields(suggestion.osm.properties, suggestion.fullText, lat, lng);
        this.focusOn(lat, lng);
        this.createMarker(lat, lng);
        return;
      }

      this._placesService.getDetails(
        {
          placeId: suggestion.placeId,
          fields: [
            "formatted_address",
            "geometry",
            "name",
            "place_id",
            "address_components",
          ],
          sessionToken: this._sessionToken,
        },
        (place, status) => {
          // Rotate session token after a completed autocomplete → detail pair
          this._sessionToken =
            new google.maps.places.AutocompleteSessionToken();

          if (
            status !== google.maps.places.PlacesServiceStatus.OK ||
            !place?.geometry
          ) {
            console.warn("Places detail failed:", status);
            return;
          }

          const lat = place.geometry.location.lat();
          const lng = place.geometry.location.lng();

          this.fillAddressFields(place, lat, lng);

          this.focusOn(lat, lng);
          if (this.marker) this.marker.remove();
          this.createMarker(lat, lng);
        },
      );
    },
    // Centre on a point, keeping it clear of the search/address panel that overlays the map's left side.
    focusOn(lat, lng, zoom = 16) {
      this.map.flyTo(L.latLng(lat, lng), Math.max(zoom, this.map.getZoom()), { duration: 1.2 });
    },
    closeSuggestions() {
      // Small delay so a click on a suggestion fires before the list disappears
      setTimeout(() => {
        this.suggestionsOpen = false;
      }, 150);
    },
    // ########################################## Init GeoJson Editor
    async initGeoJSONEditor(isInput) {
      const container = this.$refs["geojson-editor"];
      if (!container || this.editor) return;
      const {
        EditorView, basicSetup, json, highlightActiveLine, highlightSpecialChars,
        bracketMatching, closeBrackets, placeholder,
      } = await loadEditor();
      const self = this;
      let timeout;

      this.editor = new EditorView({
        parent: container,
        extensions: [
          basicSetup,
          json(),
          highlightActiveLine(),
          highlightSpecialChars(),
          bracketMatching(),
          closeBrackets(),
          EditorView.lineWrapping,
          placeholder(
            'No shapes yet. Draw on the map with the tools on the left, upload a .geojson file, or paste GeoJSON here, e.g.\n{ "type": "FeatureCollection", "features": [] }',
          ),
          EditorView.theme({
            // "&": { height: "100%" },
            ".cm-scroller": { overflow: "auto" },
          }),
          EditorView.updateListener.of((update) => {
            let currentDoc = update.state.doc.toString();
            if (update.docChanged) {
              const doc = update.state.doc.toString();
              if (doc == "") {
                self.geojsonText = "";
                this.resetMap();
                return;
              }
              if (currentDoc == self.geojsonText) {
                return;
              }
              self.geojsonText = doc;
              clearTimeout(self.validateTimeout);
              self.validateTimeout = setTimeout(() => {
                self.validateGeoJSON(doc);
              }, 250);
            }
          }),
        ],
        doc: this.geojsonText,
      });
    },
    updateMapFromEditor() {
      if (!this.geojsonText?.trim()) return;
      try {
        const geojson = JSON.parse(this.geojsonText);
        if (!geojson.type || !Array.isArray(geojson.features)) {
          console.warn("Not a valid FeatureCollection");
          return;
        }
        this.drawGeoJSONOnMap(geojson);
      } catch (err) {
        console.warn("Invalid GeoJSON:", err.message);
      }
    },
    updateEditorFromMap() {
      if (!this.map) return;
      this._mapIsSource = true;
      // Let edits typed in the editor redraw the map again once this update has gone through.
      this.$nextTick(() => {
        this._mapIsSource = false;
      });
      const geoLayers = this.map.pm.getGeomanLayers();
      if (!geoLayers || geoLayers.length === 0) {
        this.geojsonText = null;
        // A shape-only field's location is the centre of its shapes, so it goes when the last shape does.
        if (!this.showMarker) this.lat = this.lng = null;
        if (this.editor) {
          const currentDoc = this.editor.state.doc.toString();
          if (currentDoc !== "") {
            this.editor.dispatch({
              changes: {
                from: 0,
                to: currentDoc.length,
                insert: "",
              },
            });
          }
        }
        return;
      }
      const geojson = {
        type: "FeatureCollection",
        features: [],
      };
      for (let i = 0; i < geoLayers.length; i++) {
        const json = geoLayers[i].toGeoJSON();
        if (geoLayers[i] instanceof L.Circle) {
          json.properties.shape = "Circle";
          json.properties.radius = geoLayers[i].getRadius();
        }
        if (geoLayers[i] instanceof L.CircleMarker) {
          json.properties.shape = "CircleMarker";
        }
        geojson.features.push(json);
      }
      this.geojsonText = JSON.stringify(geojson, null, 2);
      if (this.editor) {
        const currentDoc = this.editor.state.doc.toString();
        if (currentDoc !== this.geojsonText || currentDoc == "") {
          this.editor.dispatch({
            changes: {
              from: 0,
              to: currentDoc.length,
              insert: this.geojsonText,
            },
          });
        }
      }
    },
    resetMap() {
      const layers = this.map.pm.getGeomanDrawLayers(true);
      if (layers) {
        layers.eachLayer(function (layer) {
          layer.remove();
        });
      }
      if (this.geoJsonLayer) {
        this.geoJsonLayer.eachLayer(function (layer) {
          layer.remove();
        });
      }
    },
    drawGeoJSONOnMap(geojson, fitbounds = false) {
      this.resetMap();
      this.geoJsonLayer = L.geoJSON(geojson, {
        pmIgnore: false,
        style: (feature) => this._shapeStyle(feature?.properties),
        pointToLayer: (feature, latlng) => {
          if (feature.properties?.radius) {
            return new L.Circle(latlng, feature.properties?.radius);
          } else if (feature.properties?.shape == "CircleMarker") {
            return new L.CircleMarker(latlng);
          } else {
            return new L.Marker(latlng);
          }
          return;
        },
        onEachFeature: (feature, layer) => {
          layer.options.pmIgnore = false;
          layer.pm?.enable?.();
          // re-attach event listener
          this.setLayerEvents(layer);
        },
      }).addTo(this.map);
      this.map.eachLayer((layer) => {
        if (layer.pm && layer !== this.marker) {
          layer.pm.enable();
          layer.pm.disable(); // enable then disable = registers without activating edit mode
        }
      });
      if (fitbounds) {
        if (this.geoJsonLayer.getLayers().length) {
          this.map.fitBounds(this.geoJsonLayer.getBounds());
        }
      }
    },
    fillAddressFields(place, lat, lng) {
      const components = place.address_components || [];
      let address1 = "";
      let suburb = "";
      let postcode = "";
      let country = "";
      let countryCode = "";

      components.forEach((component) => {
        const types = component.types;

        // Address Line 1
        if (types.includes("street_number")) {
          address1 = component.long_name;
        } else if (types.includes("route")) {
          address1 = (address1 ? address1 + " " : "") + component.long_name;
        }
        // Suburb / City
        if (
          types.includes("locality") ||
          types.includes("sublocality_level_1")
        ) {
          suburb = component.long_name;
        }
        // Postcode
        if (types.includes("postal_code")) {
          postcode = component.long_name;
        }
        if (types.includes("country")) {
          country = component.long_name;
          countryCode = component.short_name;
        }
      });

      this.full_address = place.formatted_address || null;
      this.lat = lat?.toFixed(6) ?? null;
      this.lng = lng?.toFixed(6) ?? null;
      this.address1 = address1 || null;
      this.suburb = suburb || null;
      this.postcode = postcode || null;
      this.country = country || null;
      this.countryCode = countryCode || null;
    },
    async toggleEditor() {
      this.showEditor = !this.showEditor;
      if (this.showEditor) {
        await this.initGeoJSONEditor(true);
        setTimeout(() => this.updateEditorFromMap(), 100);
      }
      // The map changes size when the editor panel opens or closes.
      this.$nextTick(() => setTimeout(() => this.map?.invalidateSize(), 50));
    },
    addTileAttribution() {
      L.control
        .attribution({ prefix: false, position: "bottomright" })
        .addAttribution(this._tileAttribution)
        .addTo(this.map);
    },
    // Google rejected the key or failed to load: keep the field working with OpenStreetMap.
    useOsmFallback() {
      if (this.provider === "osm" || !this.map) return;
      this.provider = "osm";
      this.googleFailed = true;
      // Neutralise the Google layer instead of removing it: it registers map handlers only after Google
      // finishes loading (which may be after this), and those throw once the key has been rejected.
      const layer = this._googleLayer;
      if (layer) {
        this.map.off("moveend", layer._update, layer);
        this.map.off("zoomend", layer._handleZoomAnim, layer);
        const noop = () => {};
        layer._update = noop;
        layer._handleZoomAnim = noop;
        layer._checkZoomLevels = noop;
        // Stop Leaflet's grid machinery from driving the dead layer (removing it breaks the map).
        const events = layer.getEvents?.();
        if (events) this.map.off(events, layer);
        layer._pruneTile = noop;
        layer._pruneTiles = noop;
        layer._resetView = noop;
        layer._onMoveEnd = noop;
        layer.createTile = () => document.createElement("div");
        const container = layer.getContainer?.();
        if (container) container.style.display = "none";
      }
      this.addTileAttribution();
    },
    get hasLocation() {
      return this.lat !== null && this.lat !== "" && this.lng !== null && this.lng !== "";
    },
    setRefValue(refName, value) {
      const el = this.$refs[refName];
      if (el) {
        el.value = value || "";
      } else {
        console.warn(`⚠️ x-ref="${refName}" not found`);
      }
    },
    // ########################################## Init Geo tools
    initGeoman() {
      this.map.pm.setPathOptions({
        color: "#2563eb",
        fillColor: "#2563eb",
        fillOpacity: 0.2,
        weight: 2,
      });
      // GeoJSON has no circles, so circle tools are left out (they'd save as a bare point).
      this.map.pm.addControls({
        position: "topleft",
        drawText: false,
        drawMarker: true,
        drawPolyline: true,
        drawRectangle: true,
        drawPolygon: true,
        drawCircle: false,
        drawCircleMarker: false,
        editMode: true,
        dragMode: true,
        cutPolygon: true,
        removalMode: true,
        rotateMode: false,
      });
      this.map.pm.getGeomanLayers().forEach((layer) => {
        this.setLayerEvents(layer);
      });
      this.map.on("pm:create", (e) => {
        this.updateEditorFromMap();
        this.setLayerEvents(e.layer);
      });

      this.map.on("click", (e) => {
        if (this.map.pm.globalDrawModeEnabled() || this.map.pm.globalEditModeEnabled?.()) return;
        if (this.featureEditorOpen) {
          this.closeFeatureEditor();
          return;
        }
        // Click the map to drop the pin there (not for shape-only fields).
        if (!this.showMarker) return;
        this.createMarker(e.latlng.lat, e.latlng.lng);
        this.setLocation(e.latlng.lat, e.latlng.lng);
      });
      // Layer removed
      this.map.on("pm:remove", () => {
        this.updateEditorFromMap();
      });
      // Close feature editor on geojson tool toggled
      this.map.on("pm:globaldrawmodetoggled", (e) => {
        if (e.enabled && this.featureEditorOpen) {
          this.closeFeatureEditor();
        }
      });
      this.map.on("pm:globalremovalmodetoggled", (e) => {
        if (e.enabled && this.featureEditorOpen) {
          this.closeFeatureEditor();
        }
      });
    },
    setLayerEvents(layer) {
      layer.on("pm:edit", () => {
        this.updateEditorFromMap();
        if (this.selectedLayer === layer) {
          this._syncVerticesFromLayer(layer);
        }
      });
      layer.on("pm:drag", () => {
        this.updateEditorFromMap();
        if (this.selectedLayer === layer) {
          this._syncVerticesFromLayer(layer);
        }
      });
      layer.on("pm:rotate", () => {
        this.updateEditorFromMap();
        if (this.selectedLayer === layer) {
          this._syncVerticesFromLayer(layer);
        }
      });
      layer.on("pm:vertexadded pm:vertexremoved", () => {
        if (this.selectedLayer === layer) {
          this._syncVerticesFromLayer(layer);
        }
      });
      // Click to open feature editor
      layer.on("click", (e) => {
        if (this.map.pm.globalRemovalModeEnabled()) return;
        if (this.map.pm.globalDrawModeEnabled()) return;
        if (this.map.pm.globalEditModeEnabled()) return;
        L.DomEvent.stopPropagation(e);
        if (this.selectedLayer && this.selectedLayer !== layer) {
          this.selectedLayer.pm.disable();
          this.selectedLayer.setStyle?.(this._shapeStyle(this.selectedLayer.feature?.properties));
        }
        layer.pm.enable({ allowSelfIntersection: false });
        layer.setStyle?.(this._shapeStyle(layer.feature?.properties, true));

        this.openFeatureEditor(layer);
      });
    },
    openFeatureEditor(layer) {
      this.selectedLayer = layer;
      const geom = layer.toGeoJSON().geometry;
      this.featureGeomType = geom.type;

      const props = layer.feature?.properties || {};
      this.featureProps = Object.entries(props)
        .filter(([k]) => !["shape", "radius"].includes(k))
        .map(([key, val]) => ({ key, 
        val: (typeof val === 'object' && val !== null)
            ? JSON.stringify(val, null, 2)
            : String(val ?? ''),
        type: this._detectType(val),
        }));
      this._syncVerticesFromLayer(layer);
      this.featureEditorOpen = true;
      this.featureEditorTab = "properties";
    },

    closeFeatureEditor() {
      if (this.selectedLayer) {
        this.selectedLayer.pm.disable();
        this.selectedLayer.setStyle?.(this._shapeStyle(this.selectedLayer.feature?.properties));
      }
      this.featureEditorOpen = false;
      this.selectedLayer = null;
      this.featureProps = [];
      this.featureVertices = [];
    },

    _syncVerticesFromLayer(layer) {
      if (!layer) return;
      const geom = layer.toGeoJSON().geometry;
      this.featureMeasure = this._measure(geom);
      if (geom.type === "Polygon") {
        this.featureVertices = (geom.coordinates[0] || []).map((c) => ({
          lng: c[0],
          lat: c[1],
        }));
      } else if (geom.type === "LineString") {
        this.featureVertices = (geom.coordinates || []).map((c) => ({
          lng: c[0],
          lat: c[1],
        }));
      } else if (geom.type === "Point") {
        this.featureVertices = [
          { lng: geom.coordinates[0], lat: geom.coordinates[1] },
        ];
      } else {
        this.featureVertices = [];
      }
    },

    featureAddProp() {
      const k = this.featurePropNewKey.trim();
      if (!k) return;
      this.featureProps.push({ key: k, val: this.featurePropNewVal, type: 'text' });
      this.featurePropNewKey = "";
      this.featurePropNewVal = "";
      this._writePropsToLayer();
    },
    featureRemoveProp(index) {
      this.featureProps.splice(index, 1);
      this._writePropsToLayer();
    },
    featurePropChanged() {
      this._writePropsToLayer();
    },
    _writePropsToLayer() {
      if (!this.selectedLayer) return;
      if (!this.selectedLayer.feature)
        this.selectedLayer.feature = { type: "Feature", properties: {} };
      const newProps = {};
      // Preserve internal geoman props
      const existing = this.selectedLayer.feature.properties || {};
      if (existing.shape) newProps.shape = existing.shape;
      if (existing.radius) newProps.radius = existing.radius;
      this.featureProps.forEach((p) => {
        if (p.key) newProps[p.key] = this._castPropValue(p.val, p.type);;
      });
      this.selectedLayer.feature.properties = newProps;
      this.selectedLayer.setStyle?.(this._shapeStyle(newProps, true));
      this.updateEditorFromMap();
    },

    // ########################################## Shape panel helpers
    /** Leaflet path style from a shape's `color`, `stroke` and `fill` properties (as on front-end maps). */
    _shapeStyle(props = {}, selected = false) {
      const p = props || {};
      const color = p.stroke || p.color || "#2563eb";
      const fill = p.fill || p.color || "#2563eb";
      return { color, fillColor: fill, weight: selected ? 4 : 2, fillOpacity: selected ? 0.3 : 0.18, dashArray: selected ? "6 4" : null };
    },

    /** Human-readable size of a geometry: area for polygons, length for lines. */
    _measure(geom) {
      if (!geom) return "";
      const R = 6378137;
      const rad = (d) => (d * Math.PI) / 180;
      const ringArea = (ring) => {
        // Spherical polygon area (sum of trapezoids on the sphere).
        let total = 0;
        for (let i = 0; i < ring.length - 1; i++) {
          const [lng1, lat1] = ring[i];
          const [lng2, lat2] = ring[i + 1];
          total += rad(lng2 - lng1) * (2 + Math.sin(rad(lat1)) + Math.sin(rad(lat2)));
        }
        return Math.abs((total * R * R) / 2);
      };
      const polygonArea = (rings) => (rings.length ? ringArea(rings[0]) - rings.slice(1).reduce((a, r) => a + ringArea(r), 0) : 0);
      const lineLength = (coords) => {
        let total = 0;
        for (let i = 1; i < coords.length; i++) {
          total += L.latLng(coords[i - 1][1], coords[i - 1][0]).distanceTo(L.latLng(coords[i][1], coords[i][0]));
        }
        return total;
      };
      const fmtArea = (m2) => (m2 >= 1e6 ? `${(m2 / 1e6).toFixed(2)} km²` : m2 >= 1e4 ? `${(m2 / 1e4).toFixed(1)} ha` : `${Math.round(m2)} m²`);
      const fmtLength = (m) => (m >= 1000 ? `${(m / 1000).toFixed(2)} km` : `${Math.round(m)} m`);

      switch (geom.type) {
        case "Polygon": return fmtArea(polygonArea(geom.coordinates));
        case "MultiPolygon": return fmtArea(geom.coordinates.reduce((a, p) => a + polygonArea(p), 0));
        case "LineString": return fmtLength(lineLength(geom.coordinates));
        case "MultiLineString": return fmtLength(geom.coordinates.reduce((a, l) => a + lineLength(l), 0));
        case "Point": return `${geom.coordinates[1].toFixed(5)}, ${geom.coordinates[0].toFixed(5)}`;
        default: return "";
      }
    },

    get featureKindLabel() {
      return { Polygon: "Area", MultiPolygon: "Areas", LineString: "Line", MultiLineString: "Lines", Point: "Point" }[this.featureGeomType] || this.featureGeomType;
    },

    _featureProp(key) {
      return this.featureProps.find((p) => p.key === key);
    },

    get featureName() {
      return this._featureProp("name")?.val ?? "";
    },

    setFeatureName(value) {
      this._setFeatureProp("name", value);
    },

    get featureColor() {
      return this._featureProp("color")?.val || "";
    },

    setFeatureColor(value) {
      this._setFeatureProp("color", value);
    },

    _setFeatureProp(key, value) {
      const existing = this._featureProp(key);
      const empty = value === null || value === undefined || String(value).trim() === "";
      if (existing && empty) {
        this.featureProps.splice(this.featureProps.indexOf(existing), 1);
      } else if (existing) {
        existing.val = value;
      } else if (!empty) {
        this.featureProps.unshift({ key, val: value, type: key === "color" ? "color" : "text" });
      }
      this._writePropsToLayer();
    },

    zoomToFeature() {
      const layer = this.selectedLayer;
      if (!layer || !this.map) return;
      if (layer.getBounds) {
        this.map.fitBounds(layer.getBounds(), { padding: [40, 40], maxZoom: 17 });
      } else if (layer.getLatLng) {
        this.map.setView(layer.getLatLng(), Math.max(this.map.getZoom(), 15));
      }
    },

    deleteFeature() {
      const layer = this.selectedLayer;
      if (!layer) return;
      this.closeFeatureEditor();
      layer.remove();
      this.updateEditorFromMap();
    },

    /** Centre of everything drawn (the location of shape-only fields), or null. */
    get shapesCentre() {
      if (!this.geojsonText) return null;
      let data;
      try { data = JSON.parse(this.geojsonText); } catch { return null; }
      let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
      const walk = (node) => {
        if (!Array.isArray(node)) {
          if (node && typeof node === "object") ["coordinates", "geometry", "geometries", "features"].forEach((k) => node[k] && walk(node[k]));
          return;
        }
        if (typeof node[0] === "number" && typeof node[1] === "number") {
          minLng = Math.min(minLng, node[0]); maxLng = Math.max(maxLng, node[0]);
          minLat = Math.min(minLat, node[1]); maxLat = Math.max(maxLat, node[1]);
          return;
        }
        node.forEach(walk);
      };
      walk(data);
      return minLat === Infinity ? null : { lat: (minLat + maxLat) / 2, lng: (minLng + maxLng) / 2 };
    },

    get locationLabel() {
      if (this.hasLocation) return `${this.lat}, ${this.lng}`;
      const c = !this.showMarker ? this.shapesCentre : null;
      return c ? `${c.lat.toFixed(6)}, ${c.lng.toFixed(6)}` : null;
    },
    _castPropValue(val, type) {
        switch (type) {
            case 'boolean':
                return val === 'true';                      // actual JS boolean
            case 'number':
                return isNaN(val) ? val : Number(val);      // actual JS number
            case 'json':
                try { return JSON.parse(val); } catch { return val; }
            default:
                return val;                                 // string as-is
        }
    },
    featureUpdateVertex(index, field, value) {
      const v = this.featureVertices[index];
      if (!v || !this.selectedLayer) return;
      v[field] = parseFloat(value) || 0;

      // Sync closing vertex for polygons
      if (this.featureGeomType === "Polygon" && index === 0) {
        this.featureVertices[this.featureVertices.length - 1] = { ...v };
      }
      if (
        this.featureGeomType === "Polygon" &&
        index === this.featureVertices.length - 1
      ) {
        this.featureVertices[0] = { ...v };
      }
      this._writeVerticesToLayer();
    },

    featureDeleteVertex(index) {
      const minVertices = this.featureGeomType === "Polygon" ? 4 : 2;
      if (this.featureVertices.length <= minVertices) return;
      this.featureVertices.splice(index, 1);
      // Re-close polygon
      if (this.featureGeomType === "Polygon") {
        this.featureVertices[this.featureVertices.length - 1] = {
          ...this.featureVertices[0],
        };
      }
      this._writeVerticesToLayer();
    },

    featureAddVertex() {
      if (!this.selectedLayer) return;
      // Insert a copy of the second-to-last vertex as a starting point
      const insertAt = Math.max(0, this.featureVertices.length - 1);
      const ref = this.featureVertices[insertAt - 1] || { lat: 0, lng: 0 };
      this.featureVertices.splice(insertAt, 0, {
        lat: ref.lat + 0.01,
        lng: ref.lng + 0.01,
      });
      this._writeVerticesToLayer();
    },

    _writeVerticesToLayer() {
      if (!this.selectedLayer) return;
      let newCoords;
      if (this.featureGeomType === "Polygon") {
        newCoords = [this.featureVertices.map((v) => [v.lng, v.lat])];
        this.selectedLayer.setLatLngs(
          this.featureVertices.map((v) => L.latLng(v.lat, v.lng)),
        );
      } else if (this.featureGeomType === "LineString") {
        this.selectedLayer.setLatLngs(
          this.featureVertices.map((v) => L.latLng(v.lat, v.lng)),
        );
      } else if (this.featureGeomType === "Point") {
        this.selectedLayer.setLatLng(
          L.latLng(this.featureVertices[0].lat, this.featureVertices[0].lng),
        );
      }
      this.updateEditorFromMap();
    },

    featureCopyGeoJSON() {
      if (!this.selectedLayer) return;
      const gj = this.selectedLayer.toGeoJSON();
      if (this.selectedLayer.feature?.properties) {
        gj.properties = { ...this.selectedLayer.feature.properties };
      }
      navigator.clipboard.writeText(JSON.stringify(gj, null, 2)).then(() => {
        this.featureCopied = true;
        setTimeout(() => {
          this.featureCopied = false;
        }, 2000);
      });
    },

    getFeatureGeoJSONString() {
      if (!this.selectedLayer) return "{}";
      const gj = this.selectedLayer.toGeoJSON();
      if (this.selectedLayer.feature?.properties) {
        gj.properties = { ...this.selectedLayer.feature.properties };
      }
      return JSON.stringify(gj, null, 2);
    },
    clearAddress() {
      this.lat = null;
      this.lng = null;
      this.full_address =
        this.address1 =
        this.postcode =
        this.suburb =
        this.country =
        this.countryCode =
          null;
      this.searchQuery = "";
      const searchInput = this.$refs["apgeo-search"]?.querySelector("input");
      if (searchInput) searchInput.value = "";
      this.createMarker(this.defaultLat, this.defaultLng);
      this.map.flyTo([this.defaultLat, this.defaultLng], this.defaultZoom, {
        duration: 1.5,
      });
    },
    createMarker(lat, lng) {
      if (this.marker) this.marker.remove();
      // Shape-only fields (e.g. collection zones) have no pin; their location is the centre of the shapes.
      if (!this.showMarker) {
        this.marker = null;
        return null;
      }
      this.marker = L.marker([lat, lng], {
        draggable: true,
        autoPan: true,
        pmIgnore: true,
      }).addTo(this.markerLayer);
      this.initMarkerDrag(this.marker);
      return this.marker;
    },
    initMarkerDrag(marker) {
      marker.on("dragend", () => {
        const { lat, lng } = marker.getLatLng();
        this.setLocation(lat, lng);
      });
    },
    // Sets the coordinates and fills in the address for that point.
    async setLocation(lat, lng) {
        this.lat = lat.toFixed(6);
        this.lng = lng.toFixed(6);
        if (!this.isInput) return;

        if (this.provider === "osm") {
          await this.reverseGeocodeOsm(lat, lng);
          return;
        }

        const lib = await loadGoogleLibrary("geocoding");
        if (!lib?.Geocoder) return;
        const geocoder = new lib.Geocoder();

        geocoder.geocode({ location: { lat, lng } }, (results, status) => {
          if (status === "OK" && results[0]) {
            const place = {
              formatted_address: results[0].formatted_address,
              address_components: results[0].address_components,
            };
            this.fillAddressFields(place, lat, lng);
          } else {
            console.warn("Geocoder failed:", status);
          }
        });
    },
    async reverseGeocodeOsm(lat, lng) {
      const params = new URLSearchParams({
        lat: String(lat),
        lon: String(lng),
        format: "jsonv2",
        addressdetails: "1",
      });
      if (this.language) params.set("accept-language", this.language);
      try {
        const res = await fetch("https://nominatim.openstreetmap.org/reverse?" + params);
        const json = await res.json();
        if (json && json.address) {
          this.fillOsmAddressFields(json.address, json.display_name, lat, lng);
        }
      } catch (e) {
        console.warn("Geo Maps: OpenStreetMap reverse geocoding failed", e);
      }
    },
   // ########################################## Upload and Download
    download(elementId) {
      const a = document.createElement("a");
      const text = this.geojsonText || JSON.stringify({ type: "FeatureCollection", features: [] }, null, 2);
      const file = new Blob([text], { type: "application/geo+json" });
      a.href = URL.createObjectURL(file);
      a.download = (elementId ? "shapes-" + elementId : "shapes") + ".geojson";
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 0);
    },
    uploadGeoJSON(file) {
      if (!file) return;

      const hasExistingShapes = (() => {
        try {
          const existing = JSON.parse(this.geojsonText);
          return (
            Array.isArray(existing?.features) && existing.features.length > 0
          );
        } catch {
          return false;
        }
      })();

      if (hasExistingShapes) {
        const confirmed = window.confirm(
          "Uploading a new file will replace all existing shapes on the map.\n\nContinue?",
        );
        if (!confirmed) return;
      }
      // Validate
      const validTypes = [
        "application/json",
        "application/geo+json",
        "text/plain",
      ];
      const validExt = /\.(geojson|json)$/i.test(file.name);
      if (!validExt && !validTypes.includes(file.type)) {
        this.$dispatch("supgeo:upload-error", {
          message: "Invalid file type. Please upload a .geojson or .json file.",
        });
        return;
      }

      // Progress
      this.uploadProgress = 0;
      this.uploadStatus = "reading";
      this.uploadError = null;

      const reader = new FileReader();

      reader.onprogress = (e) => {
        if (e.lengthComputable) {
          this.uploadProgress = Math.round((e.loaded / e.total) * 80);
        }
      };

      reader.onload = (e) => {
        this.uploadProgress = 85;
        this.uploadStatus = "parsing";
        setTimeout(() => {
          try {
            const geojson = JSON.parse(e.target.result);

            if (!geojson.type) {
              throw new Error('Missing "type" property — not valid GeoJSON.');
            }
            if (
              geojson.type === "FeatureCollection" &&
              !Array.isArray(geojson.features)
            ) {
              throw new Error(
                'FeatureCollection must have a "features" array.',
              );
            }

            // Normalise to FeatureCollection if a bare Feature or Geometry was uploaded
            let normalised = geojson;
            if (geojson.type === "Feature") {
              normalised = { type: "FeatureCollection", features: [geojson] };
            } else if (
              !["FeatureCollection", "Feature"].includes(geojson.type)
            ) {
              // Raw geometry
              normalised = {
                type: "FeatureCollection",
                features: [
                  { type: "Feature", geometry: geojson, properties: {} },
                ],
              };
            }

            this.uploadProgress = 100;
            this.uploadStatus = "done";
            this.geojsonText = JSON.stringify(normalised, null, 2);

            if (this.editor) {
              const currentDoc = this.editor.state.doc.toString();
              this.editor.dispatch({
                changes: {
                  from: 0,
                  to: currentDoc.length,
                  insert: this.geojsonText,
                },
              });
            }
            this.drawGeoJSONOnMap(normalised, true);

            if (this.geoJsonLayer && this.geoJsonLayer.getLayers().length) {
              try {
                this.map.fitBounds(this.geoJsonLayer.getBounds(), {
                  padding: [40, 40],
                });
              } catch (_) {
                /* empty layer / single point — ignore */
              }
            }
            setTimeout(() => {
              this.uploadStatus = null;
              this.uploadProgress = 0;
            }, 2000);
          } catch (err) {
            this.uploadProgress = 100;
            this.uploadStatus = "error";
            this.uploadError = err.message || "Could not parse GeoJSON.";
          }
        }, 120);
      };

      reader.onerror = () => {
        this.uploadStatus = "error";
        this.uploadError = "File could not be read.";
      };

      reader.readAsText(file);
    },
    triggerUpload() {
      this.$refs["geojson-upload-input"]?.click();
    },
    // ########################################## Prop Editor for type
    openPropEditor(index) {
      const prop = this.featureProps[index];
      if (!prop) return;

      this.featureEditingPropIndex = index;
      this.featureEditingPropType = prop.type || this._detectType(prop.val);
      this.featureEditingPropRaw = prop.val;
      this.featureEditingPropError = null;
      this.featurePropEditorOpen = true;

      // Auto-detect type
         const val = prop.val;
      if (typeof val === 'object' && val !== null) {
          this.featureEditingPropRaw = JSON.stringify(val, null, 2);
      } else {
          this.featureEditingPropRaw = String(val ?? '');
      }
    },
    closePropEditor() {
      this.featurePropEditorOpen = false;
      this.featureEditingPropIndex = null;
      this.featureEditingPropError = null;
    },
    savePropEditor() {
      const i = this.featureEditingPropIndex;
      if (i === null) return;

      let val = this.featureEditingPropRaw;

      // Validate based on type
      if (this.featureEditingPropType === "json") {
        try {
          JSON.parse(val);
        } catch {
          this.featureEditingPropError = "Invalid JSON";
          return;
        }
      } else if (this.featureEditingPropType === "number") {
        if (isNaN(val) || val.trim() === "") {
          this.featureEditingPropError = "Must be a valid number";
          return;
        }
        val = String(Number(val));
      } else if (this.featureEditingPropType === "boolean") {
        if (val !== "true" && val !== "false") {
          this.featureEditingPropError = "Must be true or false";
          return;
        }
      }

      this.featureProps[i].val = val;
      this.featureProps[i].type = this.featureEditingPropType;

      this._writePropsToLayer();
      this.closePropEditor();
    },
    setPropType(type) {
      this.featureEditingPropType = type;
      this.featureEditingPropError = null;

      // Convert current raw value to the new type
      const current = this.featureEditingPropRaw;
      if (type === "boolean") {
        this.featureEditingPropRaw = current === "false" ? "false" : "true";
      } else if (type === "number") {
        this.featureEditingPropRaw = isNaN(current) ? "0" : current;
      } else if (type === "json") {
        // Try to wrap in object if not already JSON
        try {
            const parsed = JSON.parse(current);
            this.featureEditingPropRaw = JSON.stringify(parsed, null, 2);
        } catch {
          this.featureEditingPropRaw = "{}";
        }
      } else if (type === "color") {
        this.featureEditingPropRaw = /^#/.test(current) ? current : "#6366f1";
      }
    },
    _detectType(val) {
        if (typeof val === 'boolean') return 'boolean';
        if (typeof val === 'number')  return 'number';
        if (typeof val === 'object' && val !== null) return 'json';
        // Sniff strings
        const str = String(val ?? '');
        if (str === 'true' || str === 'false') return 'boolean';
        if (str.trim() !== '' && !isNaN(Number(str))) return 'number';
        if (str.trimStart().startsWith('{') || str.trimStart().startsWith('[')) return 'json';
        if (/^#[0-9a-fA-F]{3,6}$/.test(str)) return 'color';
        return 'text';
    },
    deletePropFromEditor() {
      const i = this.featureEditingPropIndex;
      if (i === null) return;
      this.featureProps.splice(i, 1);
      this._writePropsToLayer();
      this.closePropEditor();
    },
  }));
});

Alpine.start();
