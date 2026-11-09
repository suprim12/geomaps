/**
 * Geo Maps front-end address autocomplete, rendered by craft.geomaps.addressInput().
 *
 * Suggestions come from Photon (komoot), an OpenStreetMap geocoder built for
 * search-as-you-type. Picking one fills the hidden coordinate and address inputs,
 * which post in the same shape as the control panel field.
 */
import { withTypedHouseNumber, osmSuburb } from "./osm-address.js";
import "./address.css";
import { mapboxSuggest, MAPBOX_CREDIT } from "./mapbox-search.js";

const PHOTON_URL = "https://photon.komoot.io/api/?";
const PHOTON_LANGS = ["en", "de", "fr", "it"];

function suggestionFromFeature(f, i, query) {
  const p = withTypedHouseNumber(f.properties || {}, query);
  const street = [p.housenumber, p.street].filter(Boolean).join(" ");
  const main = p.name || street || p.city || p.country || "";
  const secondary = [p.name ? street : null, p.district || p.city, p.state, p.postcode, p.country]
    .filter(Boolean)
    .join(", ");
  const [lng, lat] = f.geometry?.coordinates || [];

  return {
    key: "osm-" + (p.osm_type || "") + (p.osm_id || i),
    main,
    secondary,
    values: {
      full_address: [main, secondary].filter(Boolean).join(", "),
      lat: typeof lat === "number" ? lat.toFixed(6) : "",
      lng: typeof lng === "number" ? lng.toFixed(6) : "",
      address1: street || p.name || "",
      suburb: osmSuburb(p),
      postcode: p.postcode || "",
      country: p.country || "",
      countryCode: (p.countrycode || "").toUpperCase(),
    },
  };
}

function init(root) {
  if (root.dataset.geomapsReady) return;
  root.dataset.geomapsReady = "1";

  const input = root.querySelector("[data-geomaps-query]");
  const list = root.querySelector("[data-geomaps-list]");
  const status = root.querySelector("[data-geomaps-status]");
  const hidden = {};
  root.querySelectorAll("[data-geomaps-value]").forEach((el) => {
    hidden[el.dataset.geomapsValue] = el;
  });

  const countries = (root.dataset.countries || "").split(",").filter(Boolean);
  const minChars = parseInt(root.dataset.minChars || "3", 10);
  const messages = {
    pick: root.dataset.msgPick || "Choose an address from the list.",
    none: root.dataset.msgNone || "No matching addresses.",
    results: root.dataset.msgResults || "{count} addresses found. Use the up and down keys to choose one.",
  };
  const lang = (root.dataset.lang || document.documentElement.lang || "").slice(0, 2).toLowerCase();

  let suggestions = [];
  let source = "photon";
  const mapbox = root.dataset.provider === "mapbox" && root.dataset.mapboxToken
    ? { token: root.dataset.mapboxToken, permanent: root.dataset.permanent === "1" }
    : null;
  let active = -1;
  let timer = null;
  let requestId = 0;
  let selectedText = input.value.trim() && hidden.lat?.value ? input.value.trim() : null;

  const announce = (text) => {
    status.textContent = text;
  };

  const setValues = (values) => {
    Object.keys(hidden).forEach((key) => {
      hidden[key].value = values?.[key] ?? "";
    });
    root.classList.toggle("geomaps-address--chosen", Boolean(values?.lat));
  };

  const close = () => {
    list.hidden = true;
    list.innerHTML = "";
    input.setAttribute("aria-expanded", "false");
    input.removeAttribute("aria-activedescendant");
    suggestions = [];
    active = -1;
  };

  const highlight = (index) => {
    const options = list.querySelectorAll('[role="option"]');
    options.forEach((el, i) => el.setAttribute("aria-selected", String(i === index)));
    active = index;
    if (options[index]) {
      input.setAttribute("aria-activedescendant", options[index].id);
      options[index].scrollIntoView({ block: "nearest" });
    } else {
      input.removeAttribute("aria-activedescendant");
    }
  };

  const choose = (index) => {
    const s = suggestions[index];
    if (!s) return;
    input.value = s.values.full_address;
    selectedText = input.value.trim();
    clearError();
    setValues(s.values);
    close();
    announce("");
    root.dispatchEvent(new CustomEvent("geomaps:select", { bubbles: true, detail: { ...s.values } }));
  };

  const render = () => {
    list.innerHTML = "";
    suggestions.forEach((s, i) => {
      const li = document.createElement("li");
      li.id = `${input.id}-option-${i}`;
      li.className = "geomaps-address__option";
      li.setAttribute("role", "option");
      li.setAttribute("aria-selected", "false");

      const main = document.createElement("span");
      main.className = "geomaps-address__main";
      main.textContent = s.main;
      li.append(main);
      if (s.secondary) {
        const secondary = document.createElement("span");
        secondary.className = "geomaps-address__secondary";
        secondary.textContent = s.secondary;
        li.append(secondary);
      }

      // mousedown fires before the input's blur, so the list is still there to click.
      li.addEventListener("mousedown", (e) => {
        e.preventDefault();
        choose(i);
      });
      list.append(li);
    });

    const credit = document.createElement("li");
    credit.className = "geomaps-address__credit";
    credit.setAttribute("role", "presentation");
    credit.innerHTML = source === "mapbox"
      ? MAPBOX_CREDIT
      : 'Search by <a href="https://photon.komoot.io" target="_blank" rel="noopener">Photon</a> · © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
    list.append(credit);

    list.hidden = false;
    input.setAttribute("aria-expanded", "true");
    active = -1;
  };

  const search = async (query) => {
    const id = ++requestId;
    const params = new URLSearchParams({ q: query, limit: "8" });
    countries.forEach((c) => params.append("countrycode", c.toLowerCase()));
    if (PHOTON_LANGS.includes(lang)) params.set("lang", lang);
    if (root.dataset.lat && root.dataset.lng) {
      params.set("lat", root.dataset.lat);
      params.set("lon", root.dataset.lng);
    }

    root.classList.add("geomaps-address--loading");
    try {
      let found = null;
      if (mapbox) {
        try {
          found = await mapboxSuggest(query, {
            token: mapbox.token,
            permanent: mapbox.permanent,
            countries,
            language: lang,
            near: root.dataset.lat ? { lat: root.dataset.lat, lng: root.dataset.lng } : null,
          });
          source = "mapbox";
        } catch (e) {
          console.warn("Geo Maps: Mapbox search failed, using OpenStreetMap", e);
        }
      }
      if (found === null) {
        const res = await fetch(PHOTON_URL + params);
        const json = await res.json();
        let features = json.features || [];
        if (countries.length) {
          features = features.filter((f) => countries.includes((f.properties?.countrycode || "").toUpperCase()));
        }
        found = features.map((f, i) => suggestionFromFeature(f, i, query));
        source = "photon";
      }
      if (id !== requestId) return; // a newer search has started

      const seen = new Set();
      suggestions = found
        .filter((s) => {
          // The same place often comes back more than once.
          const key = s.values.full_address.toLowerCase();
          if (!s.values.lat || seen.has(key)) return false;
          seen.add(key);
          return true;
        })
        .slice(0, 6);

      if (suggestions.length) {
        render();
        announce(messages.results.replace("{count}", suggestions.length));
      } else {
        close();
        announce(messages.none);
      }
    } catch (e) {
      console.warn("Geo Maps: address search failed", e);
      close();
    } finally {
      if (id === requestId) root.classList.remove("geomaps-address--loading");
    }
  };

  const clearError = () => {
    input.removeAttribute("aria-invalid");
    root.querySelector("[data-geomaps-error]")?.setAttribute("hidden", "");
  };

  input.addEventListener("input", () => {
    const query = input.value.trim();
    clearError();

    // Editing the text after choosing an address clears the old coordinates.
    if (selectedText !== null && query !== selectedText) {
      selectedText = null;
      setValues(null);
    }

    clearTimeout(timer);
    if (query.length < minChars) {
      requestId++;
      close();
      return;
    }
    timer = setTimeout(() => search(query), 250);
  });

  input.addEventListener("keydown", (e) => {
    const open = !list.hidden && suggestions.length > 0;
    if (e.key === "ArrowDown" && open) {
      e.preventDefault();
      highlight((active + 1) % suggestions.length);
    } else if (e.key === "ArrowUp" && open) {
      e.preventDefault();
      highlight(active <= 0 ? suggestions.length - 1 : active - 1);
    } else if (e.key === "Enter" && open) {
      e.preventDefault();
      choose(active >= 0 ? active : 0);
    } else if (e.key === "Escape" && open) {
      e.preventDefault();
      close();
    }
  });

  input.addEventListener("blur", () => setTimeout(close, 150));

  // Typed text without a chosen suggestion has no coordinates, so don't submit it.
  input.form?.addEventListener("submit", (e) => {
    if (input.value.trim() && !hidden.lat?.value) {
      e.preventDefault();
      input.setAttribute("aria-invalid", "true");
      announce(messages.pick);
      root.querySelector("[data-geomaps-error]")?.removeAttribute("hidden");
      input.focus();
    }
  });
}

function initAll(scope = document) {
  scope.querySelectorAll("[data-geomaps-address]").forEach(init);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => initAll());
} else {
  initAll();
}

// For inputs added to the page later (e.g. loaded with fetch or htmx).
window.GeoMaps = Object.assign(window.GeoMaps || {}, { initAddressInputs: initAll });
