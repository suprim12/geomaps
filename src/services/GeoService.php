<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\services;

use Craft;
use craft\helpers\Json;
use sup\craftgeo\Geo;
use Throwable;
use yii\base\Component;

/**
 * Geocoding helpers (Google Geocoding API).
 */
class GeoService extends Component
{
    public const GEOCODE_ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

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
        $params = ['address' => $address];

        $country = $country ? strtoupper(trim($country)) : null;
        if ($country && $country !== '*' && static::isCountryCode($country)) {
            $params['components'] = "country:$country";
        }

        $result = static::request($params);

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
        $result = static::request(['latlng' => "$lat,$lng"]);

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
        $countries = Craft::$app->getAddresses()->getCountryRepository()->getList(Craft::$app->language);
        asort($countries);

        return $countries;
    }

    public static function isCountryCode(string $code): bool
    {
        return array_key_exists(strtoupper($code), static::countryOptions());
    }

    /**
     * Sends a Geocoding API request and returns the first result.
     */
    private static function request(array $params): ?array
    {
        $apiKey = Geo::getInstance()->getSettings()->getGeoToken();

        if (!$apiKey) {
            Craft::warning('Geo Maps: geocoding skipped because no Geocoding API key is configured.', __METHOD__);
            return null;
        }

        $params['language'] = Craft::$app->language;
        $cacheKey = 'geo:geocode:' . md5(Json::encode($params));

        $cached = Craft::$app->getCache()->get($cacheKey);
        if ($cached !== false) {
            return $cached ?: null;
        }

        try {
            $response = Craft::createGuzzleClient(['timeout' => 5])->get(self::GEOCODE_ENDPOINT, [
                'query' => $params + ['key' => $apiKey],
            ]);
            $data = Json::decode((string)$response->getBody());
        } catch (Throwable $e) {
            Craft::error('Geo Maps: geocoding request failed: ' . $e->getMessage(), __METHOD__);
            return null;
        }

        $status = $data['status'] ?? 'UNKNOWN';

        if ($status === 'ZERO_RESULTS') {
            Craft::$app->getCache()->set($cacheKey, [], self::CACHE_DURATION);
            return null;
        }

        if ($status !== 'OK' || empty($data['results'][0])) {
            // Don't cache errors like OVER_QUERY_LIMIT or REQUEST_DENIED.
            Craft::warning("Geo Maps: geocoding returned $status" . (isset($data['error_message']) ? ": {$data['error_message']}" : ''), __METHOD__);
            return null;
        }

        $result = $data['results'][0];
        Craft::$app->getCache()->set($cacheKey, $result, self::CACHE_DURATION);

        return $result;
    }
}
