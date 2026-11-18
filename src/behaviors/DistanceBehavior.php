<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\behaviors;

use yii\base\Behavior;

/**
 * `distance` on an address found with `near()`, in the search’s unit.
 */
class DistanceBehavior extends Behavior
{
    public ?float $distance = null;
}
