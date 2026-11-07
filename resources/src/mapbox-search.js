/**
 * Mapbox Geocoding API (v6) helpers shared by the control panel field and addressInput().
 *
 * Results are turned into Geo Maps' address fields: full_address, lat, lng, address1,
 * suburb, postcode, country and countryCode.
 */
const BASE = "https://api.mapbox.com/search/geocode/v6/";

/**
 * A Mapbox feature as { key, main, secondary, values }, or null without coordinates.
 */
export function mapboxSuggestion(feature) {
  const p = feature?.properties || {};
  const c = p.context || {};
  const coords = p.coordinates || {};
  if (typeof coords.latitude !== "number" || typeof coords.longitude !== "number") return null;

  const type = p.feature_type;
  const street =
    c.address?.name ||
    [c.address?.address_number, c.address?.street_name || c.street?.name].filter(Boolean).join(" ");

  return {
    key: p.mapbox_id || p.full_address || `${coords.latitude},${coords.longitude}`,
    main: p.name || street || p.full_address || "",
    secondary: p.place_formatted || "",
    values: {
      full_address: p.full_address || [p.name, p.place_formatted].filter(Boolean).join(", "),
      lat: coords.latitude.toFixed(6),
      lng: coords.longitude.toFixed(6),
      address1: type === "address" ? street || p.name || "" : type === "street" ? p.name || "" : "",
      suburb: c.locality?.name || c.place?.name || (["locality", "place", "neighborhood"].includes(type) ? p.name : "") || "",
      postcode: c.postcode?.name || (type === "postcode" ? p.name : "") || "",
      country: c.country?.name || (type === "country" ? p.name : "") || "",
      countryCode: (c.country?.country_code || "").toUpperCase(),
    },
  };
}

function baseParams({ token, language, permanent }) {
  const params = new URLSearchParams({ access_token: token });
  if (language) params.set("language", String(language).slice(0, 2));
  // Mapbox only allows storing results from permanent requests.
  if (permanent) params.set("permanent", "true");
  return params;
}

/**
 * Address suggestions as you type.
 */
export async function mapboxSuggest(query, { token, countries = [], language, near, permanent, limit = 6 }) {
  const params = baseParams({ token, language, permanent });
  params.set("q", query.slice(0, 256));
  params.set("autocomplete", "true");
  params.set("limit", String(limit));
  if (countries.length) params.set("country", countries.map((code) => String(code).toLowerCase()).join(","));
  if (near && near.lat != null && near.lng != null) params.set("proximity", `${near.lng},${near.lat}`);

  const res = await fetch(BASE + "forward?" + params);
  if (!res.ok) throw new Error(`Mapbox geocoding returned ${res.status}`);
  const json = await res.json();
  return (json.features || []).map(mapboxSuggestion).filter(Boolean);
}

/**
 * The address at a point, for dragging the pin.
 */
export async function mapboxReverse(lat, lng, { token, language, permanent }) {
  const params = baseParams({ token, language, permanent });
  params.set("latitude", String(lat));
  params.set("longitude", String(lng));
  params.set("limit", "1");

  const res = await fetch(BASE + "reverse?" + params);
  if (!res.ok) throw new Error(`Mapbox reverse geocoding returned ${res.status}`);
  const json = await res.json();
  return mapboxSuggestion((json.features || [])[0]);
}

export const MAPBOX_CREDIT =
  'Search by <a href="https://www.mapbox.com/about/maps" target="_blank" rel="noopener">Mapbox</a> · © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
