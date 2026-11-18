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
 * Triggered before an interactive map is output, with the options it was given, such as `markers`, `center`,
 * `zoom` and `cluster`. Change `options` to change the map.
 */
class MapEvent extends Event
{
    public array $options = [];
}
