<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
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
