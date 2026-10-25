/**
 *  https://suprim.com.au
 * author suprim <support@suprim.com.au> <https://suprim.com.au>
 * copyright Copyright (c) 2026 suprim
 * 
 */
import "./supgeo.css";

import Alpine from "alpinejs";
import intersect from "@alpinejs/intersect";
import * as L from "leaflet";
import "leaflet.gridlayer.googlemutant";
import "@geoman-io/leaflet-geoman-free";
import { EditorView, basicSetup } from "codemirror";
import { EditorState } from "@codemirror/state";
import { highlightActiveLine, highlightSpecialChars } from "@codemirror/view";
import { bracketMatching } from "@codemirror/language";
import { closeBrackets } from "@codemirror/autocomplete";
import { json } from "@codemirror/lang-json";
import { setDiagnostics } from "@codemirror/lint";
import "leaflet.fullscreen";
import "leaflet.fullscreen/Control.FullScreen.css";

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
    geoCodeKey: null,
    isInput: false,
    hideAdress: false,
    hideSearch: false,
    uploadProgress: 0,
    uploadStatus: null,
    uploadError: null,
    lat: null,
    lng: null,
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
    _mapIsSource: false,
    featureEditingPropIndex: null,
    featureEditingPropType: "text", // 'text' | 'json' | 'number' | 'boolean' | 'color'
    featureEditingPropValue: "",
    featureEditingPropRaw: "", // raw string in the editor
    featureEditingPropError: null,
    featurePropEditorOpen: false,
    // ########################################## Init Map
    initMap(data, isInput = false, settingData = {}) {
      const { lat, lng, zoom, geoToken } = data;

      const hasLat = !isNaN(lat) && Number(lat) % 1 !== 0;
      const hasLng = !isNaN(lng) && Number(lng) % 1 !== 0;
      if (hasLat) {
        this.defaultLat = parseFloat(lat);
      } else {
        this.defaultLat = parseFloat(settingData?.lat);
      }
      if (hasLng) {
        this.defaultLng = parseFloat(lng);
      } else {
        this.defaultLng = parseFloat(settingData?.lng);
      }
      this.defaultZoom = zoom;
      this.isInput = isInput ?? false;

      this.hideAdress = data?.hideAddress ?? false;
      this.hideSearch = data?.hideSearch ?? false;

      if (geoToken) {
        this.geoCodeKey = geoToken;
      }
      const tiles = L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
          maxZoom: 19,
          attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        },
      );

      const map = L.map(this.$refs["appgeo-map"], {
        tap: false,
        zoomControl: false,
        // pmIgnore: false,
        fullscreenControl: false,
        attributionControl: false,
      })
        .setView([this.defaultLat, this.defaultLng], this.defaultZoom)
        .addLayer(tiles);

      this.map = map;

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

      // Google
      this.map.whenReady(() => {
        let roads = L.gridLayer
          .googleMutant({
            type: "roadmap",
          })
          .addTo(map);
      });

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

      // Initalize geojson
      this.geojsonText = data.geoJson;
      this.initGeoJSONEditor(isInput);

      // Inital Data set
      this.lat = hasLat ? parseFloat(lat)?.toFixed(6) : null;
      this.lng = hasLng ? parseFloat(lng)?.toFixed(6) : null;

      this.full_address = data.full_address || null;
      this.address1 = data.address1 || null;
      this.postcode = data.postcode || null;
      this.suburb = data.suburb || null;
      this.country = data.country || null;

      // Initialize Geo JSON data
      if (data.geoJson) {
        this.drawGeoJSONOnMap(JSON.parse(data.geoJson));
      }
      // Initalize GeoMan
      this.initGeoman();
    },
    validateGeoJSON(doc) {
      if (this._mapIsSource) return;
      if (!this.editor) return;

      if (!doc?.trim()) {
        this.resetMap();
        this.editor.dispatch(setDiagnostics(this.editor.state, []));
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
        this.editor.dispatch(setDiagnostics(this.editor.state, []));
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
        this.editor.dispatch(setDiagnostics(this.editor.state, diagnostics));

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
          let latlng = L.latLng(lat, lng);
          this.map.flyTo(latlng, 16, {
            duration: 1.5,
          });
          if (this.marker) this.marker.remove();
          this.createMarker(lat, lng);
        }
      });
    },
    initAutocomplete(isInput) {
      this._autocompleteService = new google.maps.places.AutocompleteService();
      this._placesService = new google.maps.places.PlacesService(
        this.$refs["supgeo-places-attr"],
      );
      this._sessionToken = new google.maps.places.AutocompleteSessionToken();
    },
    onSearchKeydown(e) {
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
        e.preventDefault();
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
      if (!this._autocompleteService) return;

      this.loadingSuggestions = true;

      this._autocompleteService.getPlacePredictions(
        {
          input: query,
          sessionToken: this._sessionToken,
          // Uncomment to restrict: componentRestrictions: { country: 'au' },
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
    selectSuggestion(suggestion) {
      this.searchQuery = suggestion.fullText;
      this.suggestionsOpen = false;
      this.suggestions = [];

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

          this.map.flyTo(L.latLng(lat, lng), 16, { duration: 1.5 });
          if (this.marker) this.marker.remove();
          this.createMarker(lat, lng);
        },
      );
    },
    closeSuggestions() {
      // Small delay so a click on a suggestion fires before the list disappears
      setTimeout(() => {
        this.suggestionsOpen = false;
      }, 150);
    },
    // ########################################## Init GeoJson Editor
    initGeoJSONEditor(isInput) {
      const container = this.$refs["geojson-editor"];
      if (!container) return;
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
      this._mapIsSource = true;
      if (!this.map) return;
      const geoLayers = this.map.pm.getGeomanLayers();
      if (!geoLayers || geoLayers.length === 0) {
        this.geojsonText = null;
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
      const features = [];

      if (geoLayers?.length <= 0) {
        this.geojsonText = null;
        return;
      }
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

      this.$nextTick(() => {
        this._mapIsSource = false;
      });
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
        pointToLayer: (feature, latlng) => {
          if (feature.properties.radius) {
            return new L.Circle(latlng, feature.properties.radius);
          } else if (feature.properties.shape == "CircleMarker") {
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
          countryCode = component.short_name;
        }
        // County / Region
        if (types.includes("administrative_area_level_2")) {
          country = component.long_name;
        } else if (!country && types.includes("administrative_area_level_1")) {
          country = component.long_name;
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
    toggleEditor() {
      this.showEditor = !this.showEditor;
      if (this.showEditor) {
        setTimeout(() => this.updateEditorFromMap(), 100);
      }
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
        color: "purple",
        fillColor: "purple",
        fillOpacity: 0.4,
      });
      this.map.pm.addControls({
        position: "topright",
        drawText: false,
        drawMarker: true,
        drawPolyline: true,
        drawRectangle: true,
        drawPolygon: true,
        drawCircle: true,
        editMode: true,
        removalMode: true,
      });
      this.map.pm.getGeomanLayers().forEach((layer) => {
        this.setLayerEvents(layer);
      });
      this.map.on("pm:create", (e) => {
        this.updateEditorFromMap();
        this.setLayerEvents(e.layer);
      });

      this.map.on("click", (e) => {
        if (this.map.pm.globalDrawModeEnabled()) return;
        if (this.featureEditorOpen) {
          this.closeFeatureEditor();
        }
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
          this.selectedLayer.setStyle?.({
            color: "purple",
            fillColor: "purple",
            fillOpacity: 0.4,
          });
        }
        layer.pm.enable({ allowSelfIntersection: false });
        layer.setStyle?.({
          color: "#4f46e5",
          fillColor: "#4f46e5",
          fillOpacity: 0.35,
        });

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
        this.selectedLayer.setStyle?.({
          color: "purple",
          fillColor: "purple",
          fillOpacity: 0.4,
        });
      }
      this.featureEditorOpen = false;
      this.selectedLayer = null;
      this.featureProps = [];
      this.featureVertices = [];
    },

    _syncVerticesFromLayer(layer) {
      if (!layer) return;
      const geom = layer.toGeoJSON().geometry;
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
      this.updateEditorFromMap();
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
          null;
      const searchInput = this.$refs["apgeo-search"]?.querySelector("input");
      if (searchInput) searchInput.value = "";
      if (this.marker) this.marker.remove();
      this.marker = L.marker([this.defaultLat, this.defaultLng], {
        draggable: true,
        autoPan: true,
        pmIgnore: true,
      }).addTo(this.markerLayer);
      this.map.flyTo([this.defaultLat, this.defaultLng], this.defaultZoom, {
        duration: 1.5,
      });
      this.initMarkerDrag(this.marker);
    },
    createMarker(lat, lng) {
      if (this.marker) this.marker.remove();
      this.marker = L.marker([lat, lng], {
        draggable: true,
        autoPan: true,
        pmIgnore: true,
      }).addTo(this.markerLayer);
      this.initMarkerDrag(this.marker);
      return this.marker;
    },
    initMarkerDrag(marker) {
      let geocoder;
      if (this.geocodeKey) {
        geocoder = new google.maps.Geocoder({ apiKey: this.geocodeKey });
      } else {
        geocoder = new google.maps.Geocoder();
      }

      marker.on("dragend", () => {
        const { lat, lng } = marker.getLatLng();
        this.lat = lat.toFixed(6);
        this.lng = lng.toFixed(6);

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
      });
    },
   // ########################################## Upload and Download
    download(elementId) {
      const a = document.createElement("a");
      const file = new Blob([this.geojsonText], { type: "text/plain" });
      a.href = URL.createObjectURL(file);
      a.download = "supgeo-" + elementId + ".json";
      a.click();
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
      // const v = prop.val;
      // if (v === "true" || v === "false") {
      //   this.featureEditingPropType = "boolean";
      // } else if (!isNaN(v) && v !== "") {
      //   this.featureEditingPropType = "number";
      // } else if (v.startsWith("{") || v.startsWith("[")) {
      //   this.featureEditingPropType = "json";
      // } else if (/^#[0-9a-fA-F]{3,6}$/.test(v)) {
      //   this.featureEditingPropType = "color";
      // } else {
      //   this.featureEditingPropType = "text";
      // }
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
