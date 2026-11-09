/**
 * Helpers for OpenStreetMap (Photon / Nominatim) results, shared by the field and addressInput().
 */

/** A house number at the start of what was typed, such as "19c" or "3/12". */
const HOUSE_NUMBER = /^\s*(\d+[a-z]?(?:\s*[/-]\s*\d+[a-z]?)?)\s+\S/i;

/**
 * Photon's properties with the house number the person typed added, when Photon only knows the
 * street. Many Australian and other addresses have no house numbers in OpenStreetMap, so this
 * keeps "19c Marleston Avenue" rather than just "Marleston Avenue". The point stays on the street.
 */
export function withTypedHouseNumber(p, query) {
  // Only streets: Photon also returns bus stops and crossings under "highway".
  if (!p || p.housenumber || p.type !== "street" || p.osm_key !== "highway" || !p.name) return p;
  const match = HOUSE_NUMBER.exec(query || "");
  if (!match) return p;
  const number = match[1].replace(/\s+/g, "");
  // A postcode typed first ("5000 King William St") isn't a house number.
  if (p.postcode && number === String(p.postcode).replace(/\s+/g, "")) return p;
  return { ...p, housenumber: number, street: p.name, name: null };
}

/** The suburb, town or city: Photon calls a suburb a "district". */
export function osmSuburb(a) {
  return a.suburb || a.district || a.city || a.town || a.village || a.locality || "";
}
