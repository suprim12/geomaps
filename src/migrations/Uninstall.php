<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace suprim\geomaps\migrations;

use craft\db\Migration;

class Uninstall extends Migration
{
    public function safeUp(): bool
    {
        $this->dropTableIfExists('{{%supgeo}}');
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
