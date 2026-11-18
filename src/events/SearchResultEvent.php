<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\events;

use craft\base\ElementInterface;
use suprim\geomaps\models\GeoValue;
use yii\base\Event;

/**
 * Triggered for each result of a search endpoint or the locator. Change `result` to add or remove what’s returned.
 */
class SearchResultEvent extends Event
{
    public ElementInterface $element;
    /** The element’s location, with `distance` set when the search had a location. */
    public ?GeoValue $location = null;
    public array $result = [];
}
