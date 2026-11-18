<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\events;

use yii\base\Event;

/**
 * Triggered before and after an address is looked up.
 *
 * Before: set `result` and `handled` to answer from your own geocoder or data, and the map service isn’t called.
 * After: change `result` to adjust what was found.
 */
class GeocodeEvent extends Event
{
    /** Looking up an address for a search, such as a distance search or a map’s centre. */
    public const PURPOSE_SEARCH = 'search';
    /** Looking up coordinates that will be saved with content, such as missing coordinates. */
    public const PURPOSE_SAVE = 'save';

    public string $address;
    /** Two-letter country code the lookup is limited to, if any. */
    public ?string $country = null;
    public string $purpose = self::PURPOSE_SEARCH;
    /** @var array<string, mixed>|null The coordinates as `lat` and `lng`, or null when nothing was found. */
    public ?array $result = null;
}
