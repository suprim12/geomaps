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
 * Indexes polygons so entries can be found by the area that contains a point (`contains` search).
 */
class m261020_000000_lookup_shapes extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%supgeo}}';

        if (!$this->db->columnExists($table, 'shape')) {
            $this->addColumn($table, 'shape', $this->mediumText()->after('lng'));
            $this->addColumn($table, 'minLat', $this->double()->after('shape'));
            $this->addColumn($table, 'maxLat', $this->double()->after('minLat'));
            $this->addColumn($table, 'minLng', $this->double()->after('maxLat'));
            $this->addColumn($table, 'maxLng', $this->double()->after('minLng'));
            $this->createIndex(null, $table, ['fieldId', 'minLat', 'maxLat']);
        }

        Plugin::getInstance()->maps->rebuildLookup();

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
