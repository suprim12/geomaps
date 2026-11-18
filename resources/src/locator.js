/**
 * Locator component behaviour for craft.geomaps.locator().
 *
 * The locator is a plain GET form rendered on the server; this adds "use my location",
 * searching again when the distance or a filter changes, and opening a result's pin on the map.
 *
 * Live locators (Geo Maps Pro) search without reloading the page: the same URL is fetched, and the
 * results list and pins are swapped in place, so custom result templates keep working. "Search this
 * area" searches the part of the map in view.
 */
import "./locator.css";

function init(root) {
  if (root.dataset.geomapsReady) return;
  root.dataset.geomapsReady = "1";

  const form = root.querySelector("[data-locator-form]");
  const status = root.querySelector("[data-locator-status]");
  const locate = root.querySelector("[data-locator-locate]");
  const radius = root.querySelector("[data-locator-radius]");
  const within = root.querySelector("[data-locator-within]");
  const areaButton = root.querySelector("[data-locator-area]");
  const hidden = (key) => form.querySelector(`[data-geomaps-value="${key}"]`);
  const query = form.querySelector("[data-geomaps-query]");
  const live = "live" in root.dataset && "fetch" in window && "DOMParser" in window;
  const map = () => root.querySelector("[data-geo-embed]")?.geomaps;

  const say = (text) => {
    if (status) status.textContent = text || "";
  };

  const search = ({ keepArea = false } = {}) => {
    if (within && !keepArea) within.value = "";
    if (areaButton) areaButton.hidden = true;
    form.requestSubmit ? form.requestSubmit() : form.submit();
  };

  // "Use my location": fill the coordinates and search.
  if (locate) {
    if (!("geolocation" in navigator)) {
      locate.hidden = true;
    } else {
      locate.addEventListener("click", () => {
        locate.disabled = true;
        say(root.dataset.msgLocating);
        navigator.geolocation.getCurrentPosition(
          (pos) => {
            locate.disabled = false;
            hidden("lat").value = pos.coords.latitude.toFixed(6);
            hidden("lng").value = pos.coords.longitude.toFixed(6);
            query.value = root.dataset.msgYourLocation;
            search();
          },
          () => {
            locate.disabled = false;
            say(root.dataset.msgLocationFailed);
            query.focus();
          },
          { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
        );
      });
    }
  }

  // Changing the distance searches again, once there's a location.
  radius?.addEventListener("change", () => {
    if (hidden("lat")?.value) search();
  });

  // Choosing a suggestion searches straight away.
  form.addEventListener("geomaps:select", () => search());

  // Changing a filter searches again, in the same area.
  root.querySelector("[data-locator-filters]")?.addEventListener("change", () => search({ keepArea: true }));

  // Clicking a result (outside its links) opens its pin. Delegated, so it works on results swapped in later.
  const select = (item) => {
    const items = [...root.querySelectorAll("[data-locator-result]")];
    items.forEach((el) => el.classList.toggle("is-active", el === item));
    // Looked up each time: if Google rejects the key, the map is redrawn as a new element.
    map()?.open(items.indexOf(item));
  };
  root.addEventListener("click", (e) => {
    const item = e.target.closest("[data-locator-result]");
    if (item && !e.target.closest("a, button")) select(item);
  });
  root.addEventListener("keydown", (e) => {
    const item = e.target.closest?.("[data-locator-result]");
    if (item && e.target === item && (e.key === "Enter" || e.key === " ")) {
      e.preventDefault();
      select(item);
    }
  });

  if (!live) return;

  // "Search this area": shown once the visitor moves the map themselves.
  root.addEventListener("geomaps:moveend", (e) => {
    if (areaButton && e.detail?.byVisitor) areaButton.hidden = false;
  });
  areaButton?.addEventListener("click", () => {
    const b = map()?.getBounds();
    if (!b || !within) return;
    within.value = [b.south, b.west, b.north, b.east].map((n) => n.toFixed(5)).join(",");
    search({ keepArea: true });
  });

  let controller = null;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const params = new URLSearchParams(new FormData(form));
    // Leave empty values out of the address bar, such as no area.
    [...params.keys()].forEach((key) => {
      if (params.get(key) === "") params.delete(key);
    });
    const url = new URL(form.getAttribute("action") || location.href, location.href);
    url.search = params.toString();

    controller?.abort();
    controller = new AbortController();
    root.classList.add("is-loading");
    root.setAttribute("aria-busy", "true");
    say(root.dataset.msgSearching);

    try {
      const response = await fetch(url, {
        headers: { "X-Requested-With": "XMLHttpRequest", Accept: "text/html" },
        credentials: "same-origin",
        signal: controller.signal,
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const doc = new DOMParser().parseFromString(await response.text(), "text/html");
      const index = [...document.querySelectorAll("[data-geomaps-locator]")].indexOf(root);
      const next = doc.getElementById(root.id) || doc.querySelectorAll("[data-geomaps-locator]")[index];
      const list = next?.querySelector("[data-locator-list]");
      if (!list) throw new Error("No locator in the response");

      root.querySelector("[data-locator-list]").replaceWith(document.importNode(list, true));
      const markers = JSON.parse(next.dataset.markers || "[]");
      // In an area the map stays where the visitor put it; a new search zooms to its results.
      map()?.setMarkers(markers, !within?.value);
      history.replaceState(history.state, "", url);
      say("");
      root.dispatchEvent(new CustomEvent("geomaps:results", { bubbles: true, detail: { url: url.toString() } }));
    } catch (err) {
      if (err.name === "AbortError") return;
      // Anything unexpected: search the ordinary way.
      location.href = url.toString();
    } finally {
      root.classList.remove("is-loading");
      root.removeAttribute("aria-busy");
    }
  });
}

function initAll(scope = document) {
  scope.querySelectorAll("[data-geomaps-locator]").forEach(init);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => initAll());
} else {
  initAll();
}

window.GeoMaps = Object.assign(window.GeoMaps || {}, { initLocators: initAll });
