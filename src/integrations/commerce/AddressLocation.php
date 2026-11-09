<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\integrations\commerce;

use Craft;
use craft\elements\Address;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\GeoService;

/**
 * Coordinates for Craft addresses, such as a Commerce order's shipping address or the store's location.
 */
class AddressLocation
{
    /** @var array<string, array{lat: float, lng: float}|null> Lookups made during this request. */
    private static array $_memo = [];

    /**
     * Whether the delivery conditions can match. They need Pro: on the free edition they never match, so a
     * shipping rule or discount that uses them isn't applied to every order.
     */
    public static function isAvailable(): bool
    {
        if (Plugin::getInstance()->isPro()) {
            return true;
        }
        Craft::warning('Geo Maps delivery zone and distance conditions need Geo Maps Pro, so they don’t match any orders.', 'geomaps');

        return false;
    }

    /**
     * The address's latitude and longitude, or the address looked up with the map service (cached).
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function point(?Address $address): ?array
    {
        if (!$address) {
            return null;
        }

        if (is_numeric($address->latitude) && is_numeric($address->longitude)) {
            return ['lat' => (float)$address->latitude, 'lng' => (float)$address->longitude];
        }

        $text = implode(', ', array_filter([
            $address->addressLine1,
            $address->addressLine2,
            $address->locality,
            $address->administrativeArea,
            $address->postalCode,
        ], fn($part) => is_string($part) && trim($part) !== ''));

        if ($text === '') {
            return null;
        }

        $country = $address->countryCode ?: null;
        $key = $text . '|' . $country;
        if (!array_key_exists($key, self::$_memo)) {
            $found = GeoService::geocode($text, $country);
            self::$_memo[$key] = $found ? ['lat' => (float)$found['lat'], 'lng' => (float)$found['lng']] : null;
        }

        return self::$_memo[$key];
    }
}
