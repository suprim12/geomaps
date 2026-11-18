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

/**
 * Coordinates for Craft addresses, such as a Commerce order's shipping address or the store's location.
 */
class AddressLocation
{
    /**
     * Whether the delivery conditions can match. They need Pro: on Lite they never match, so a
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
        return Plugin::getInstance()->addresses->point($address);
    }
}
