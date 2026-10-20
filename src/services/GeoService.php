<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim <support@suprim.com.au> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\services;

use Craft;
use craft\helpers\Json;
use suprim\geomaps\Plugin;
use Throwable;
use yii\base\Component;

/**
 * Geocoding helpers (Google Geocoding API).
 */
class GeoService extends Component
{
    public const GEOCODE_ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';
    public const NOMINATIM_ENDPOINT = 'https://nominatim.openstreetmap.org';
    private const GOOGLE_UNAVAILABLE_KEY = 'geo:google-geocoding-unavailable';

    /** Results are cached so repeated searches don't burn API quota. */
    public const CACHE_DURATION = 60 * 60 * 24 * 30;

    /**
     * Returns `['lat' => float, 'lng' => float]` for a coordinate array or an address string.
     */
    public static function resolveLocation(mixed $location, ?string $country = null): ?array
    {
        if (is_array($location)) {
            $lat = $location['lat'] ?? $location[0] ?? null;
            $lng = $location['lng'] ?? $location[1] ?? null;

            if (is_numeric($lat) && is_numeric($lng)) {
                return ['lat' => (float)$lat, 'lng' => (float)$lng];
            }

            return null;
        }

        if (is_string($location) && trim($location) !== '') {
            return static::geocode(trim($location), $country);
        }

        return null;
    }

    /**
     * Geocodes an address. Returns null (and logs why) when no key is set or nothing is found.
     */
    public static function geocode(string $address, ?string $country = null): ?array
    {
        $country = $country ? strtoupper(trim($country)) : null;
        if (!$country || $country === '*' || !static::isCountryCode($country)) {
            $country = null;
        }

        if (self::geocoder() === 'nominatim') {
            return self::geocodeWithNominatim($address, $country);
        }

        $params = ['address' => $address];
        if ($country) {
            $params['components'] = "country:$country";
        }

        $result = self::request($params);

        if ($result === false) {
            // Google refused or failed (bad key, quota, network); keep search working with OpenStreetMap.
            return self::geocodeWithNominatim($address, $country);
        }

        if (!$result) {
            return null;
        }

        $location = $result['geometry']['location'] ?? null;

        if (!isset($location['lat'], $location['lng'])) {
            return null;
        }

        return ['lat' => (float)$location['lat'], 'lng' => (float)$location['lng']];
    }

    /**
     * Reverse geocodes a coordinate. Returns `['address' => string, 'parts' => array]`.
     */
    public static function reverseGeocode(float $lat, float $lng): ?array
    {
        if (self::geocoder() === 'nominatim') {
            return self::reverseGeocodeWithNominatim($lat, $lng);
        }

        $result = self::request(['latlng' => "$lat,$lng"]);

        if ($result === false) {
            return self::reverseGeocodeWithNominatim($lat, $lng);
        }

        if (!$result) {
            return null;
        }

        return [
            'address' => $result['formatted_address'] ?? null,
            'parts' => $result['address_components'] ?? [],
        ];
    }

    /**
     * Normalises a distance unit to `km` or `mi`.
     */
    public static function normalizeUnit(?string $unit): string
    {
        return match (strtolower((string)$unit)) {
            'mi', 'mile', 'miles' => 'mi',
            default => 'km',
        };
    }

    /**
     * Country options keyed by ISO 3166-1 alpha-2 code, from Craft's address repository.
     *
     * @return array<string, string>
     */
    public static function countryOptions(): array
    {
        // Loading the country list parses locale data, so keep it for the rest of the request.
        static $cache = [];
        $language = Craft::$app->language;

        if (!isset($cache[$language])) {
            $countries = Craft::$app->getAddresses()->getCountryRepository()->getList($language);
            asort($countries);
            $cache[$language] = $countries;
        }

        return $cache[$language];
    }

    /**
     * Returns the ISO 3166-1 alpha-2 code for a country name (in any of the site languages or English).
     */
    public static function countryCodeFromName(string $name): ?string
    {
        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }

        if (strlen($needle) === 2 && static::isCountryCode($needle)) {
            return strtoupper($needle);
        }

        $repo = Craft::$app->getAddresses()->getCountryRepository();
        foreach (array_unique([Craft::$app->language, 'en']) as $locale) {
            foreach ($repo->getList($locale) as $code => $label) {
                if (mb_strtolower($label) === $needle) {
                    return $code;
                }
            }
        }

        return null;
    }

    public static function isCountryCode(string $code): bool
    {
        return array_key_exists(strtoupper($code), static::countryOptions());
    }

    private static function geocodeWithNominatim(string $address, ?string $country): ?array
    {
        $params = ['q' => $address, 'format' => 'jsonv2', 'limit' => 1];
        if ($country) {
            $params['countrycodes'] = strtolower($country);
        }

        $result = self::nominatim('search', $params)[0] ?? null;

        return isset($result['lat'], $result['lon'])
            ? ['lat' => (float)$result['lat'], 'lng' => (float)$result['lon']]
            : null;
    }

    private static function reverseGeocodeWithNominatim(float $lat, float $lng): ?array
    {
        $result = self::nominatim('reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'addressdetails' => 1]);

        return isset($result['display_name'])
            ? ['address' => $result['display_name'], 'parts' => $result['address'] ?? []]
            : null;
    }

    private static function geocoder(): string
    {
        return Plugin::getInstance()->getSettings()->getGeocoder();
    }

    /**
     * Queries Nominatim (OpenStreetMap). Results are cached and requests identify the site,
     * per the Nominatim usage policy: https://operations.osmfoundation.org/policies/nominatim/
     */
    private static function nominatim(string $endpoint, array $params): ?array
    {
        $params['accept-language'] = Craft::$app->language;
        $cacheKey = "geo:nominatim:$endpoint:" . md5(Json::encode($params));

        $cached = Craft::$app->getCache()->get($cacheKey);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $siteUrl = Craft::$app->getSites()->getPrimarySite()->getBaseUrl() ?? '';

        try {
            $response = Craft::createGuzzleClient(['timeout' => 5])->get(self::NOMINATIM_ENDPOINT . "/$endpoint", [
                'query' => $params,
                'headers' => [
                    'User-Agent' => trim('CraftGeoMaps/1.0 ' . $siteUrl),
                    'Referer' => $siteUrl,
                ],
            ]);
            $data = Json::decode((string)$response->getBody());
        } catch (Throwable $e) {
            Craft::error('Geo Maps: Nominatim request failed: ' . $e->getMessage(), __METHOD__);
            return null;
        }

        if (!is_array($data) || isset($data['error'])) {
            Craft::$app->getCache()->set($cacheKey, [], self::CACHE_DURATION);
            return null;
        }

        Craft::$app->getCache()->set($cacheKey, $data, self::CACHE_DURATION);

        return $data ?: null;
    }

    /**
     * Sends a Geocoding API request.
     *
     * Returns the first result, null when nothing was found, or false when Google refused or failed
     * (so callers can fall back to OpenStreetMap).
     */
    private static function request(array $params): array|false|null
    {
        $apiKey = Plugin::getInstance()->getSettings()->getGeoToken();
        $cache = Craft::$app->getCache();

        // After a refusal (bad key, quota), skip Google for a while instead of failing every search.
        if (!$apiKey || $cache->get(self::GOOGLE_UNAVAILABLE_KEY)) {
            return false;
        }

        $params['language'] = Craft::$app->language;
        $cacheKey = 'geo:geocode:' . md5(Json::encode($params));

        $cached = $cache->get($cacheKey);
        if ($cached !== false) {
            return $cached ?: null;
        }

        try {
            $response = Craft::createGuzzleClient(['timeout' => 5])->get(self::GEOCODE_ENDPOINT, [
                'query' => $params + ['key' => $apiKey],
            ]);
            $data = Json::decode((string)$response->getBody());
        } catch (Throwable $e) {
            Craft::error('Geo Maps: Google geocoding request failed, using OpenStreetMap instead: ' . $e->getMessage(), __METHOD__);
            return false;
        }

        $status = $data['status'] ?? 'UNKNOWN';

        if ($status === 'ZERO_RESULTS') {
            $cache->set($cacheKey, [], self::CACHE_DURATION);
            return null;
        }

        if ($status !== 'OK' || empty($data['results'][0])) {
            Craft::warning("Geo Maps: Google geocoding returned $status, using OpenStreetMap instead" . (isset($data['error_message']) ? ": {$data['error_message']}" : ''), __METHOD__);

            if (in_array($status, ['REQUEST_DENIED', 'OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT'], true)) {
                $cache->set(self::GOOGLE_UNAVAILABLE_KEY, $status, 600);
            }

            return false;
        }

        $result = $data['results'][0];
        $cache->set($cacheKey, $result, self::CACHE_DURATION);

        return $result;
    }
}
