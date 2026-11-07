/**
 * Locator component behaviour for craft.geomaps.locator() (Geo Maps Pro).
 *
 * The locator is a plain GET form rendered on the server; this adds "use my location",
 * searching again when the distance changes, and opening a result's pin on the map.
 */
import "./locator.css";

function init(root) {
  if (root.dataset.geomapsReady) return;
  root.dataset.geomapsReady = "1";

  const form = root.querySelector("[data-locator-form]");
  const status = root.querySelector("[data-locator-status]");
  const locate = root.querySelector("[data-locator-locate]");
  const radius = root.querySelector("[data-locator-radius]");
  const mapEl = root.querySelector("[data-geo-embed]");
  const items = [...root.querySelectorAll("[data-locator-result]")];
  const hidden = (key) => form.querySelector(`[data-geomaps-value="${key}"]`);
  const query = form.querySelector("[data-geomaps-query]");

  const say = (text) => {
    if (status) status.textContent = text || "";
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
            hidden("lat").value = pos.coords.latitude.toFixed(6);
            hidden("lng").value = pos.coords.longitude.toFixed(6);
            query.value = root.dataset.msgYourLocation;
            form.requestSubmit ? form.requestSubmit() : form.submit();
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
    if (hidden("lat")?.value) form.requestSubmit ? form.requestSubmit() : form.submit();
  });

  // Choosing a suggestion searches straight away.
  form.addEventListener("geomaps:select", () => {
    form.requestSubmit ? form.requestSubmit() : form.submit();
  });

  // Clicking a result (outside its links) opens its pin.
  const select = (index) => {
    items.forEach((el, i) => el.classList.toggle("is-active", i === index));
    mapEl?.geomaps?.open(index);
  };

  items.forEach((item, index) => {
    item.addEventListener("click", (e) => {
      if (e.target.closest("a, button")) return;
      select(index);
    });
    item.addEventListener("keydown", (e) => {
      if ((e.key === "Enter" || e.key === " ") && e.target === item) {
        e.preventDefault();
        select(index);
      }
    });
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
