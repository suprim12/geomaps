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
use craft\helpers\Db;

/**
 * The lookup table holds a row for each location, so a field with several locations (Pro) can be searched by
 * any of them. Existing rows become location 0, so search works the same before and after.
 */
class m261119_000000_lookup_locations extends Migration
{
    public function safeUp(): bool
    {
        self::addLocationIndex($this);

        return true;
    }

    /**
     * Adds the `locationIndex` column and makes it part of the unique key. Safe to run on a table that already
     * has it.
     */
    public static function addLocationIndex(Migration $migration): void
    {
        $table = '{{%supgeo}}';
        $db = $migration->db;

        if (!$db->columnExists($table, 'locationIndex')) {
            $migration->addColumn($table, 'locationIndex', $migration->smallInteger()->notNull()->defaultValue(0)->after('fieldId'));
        }

        // The foreign keys use the old unique index, so add the new one first.
        if (!Db::findIndex($table, ['ownerId', 'ownerSiteId', 'fieldId', 'locationIndex'], true, $db)) {
            $migration->createIndex(null, $table, ['ownerId', 'ownerSiteId', 'fieldId', 'locationIndex'], true);
        }

        $old = Db::findIndex($table, ['ownerId', 'ownerSiteId', 'fieldId'], true, $db);
        if ($old) {
            $migration->dropIndex($old, $table);
        }
    }

    public function safeDown(): bool
    {
        echo "m261119_000000_lookup_locations can't be reverted.\n";
        return false;
    }
}
