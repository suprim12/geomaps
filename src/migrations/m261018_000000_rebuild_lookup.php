<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim <support@suprim.com.au> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\migrations;

use craft\db\Migration;
use suprim\geomaps\Plugin;

/**
 * Versions before 1.0.0 never wrote coordinates to the lookup table, so location search returned nothing.
 */
class m261018_000000_rebuild_lookup extends Migration
{
    public function safeUp(): bool
    {
        Plugin::getInstance()->maps->rebuildLookup();
        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
