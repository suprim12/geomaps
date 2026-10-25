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

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%supgeo}}')) {
            $this->createTable('{{%supgeo}}', [
                'id' => $this->primaryKey(),
                'ownerId' => $this->integer()->notNull(),
                'ownerSiteId' => $this->integer()->notNull(),
                'fieldId' => $this->integer()->notNull(),
                'lat' => $this->double(),
                'lng' => $this->double(),
                'shape' => $this->mediumText(),
                'minLat' => $this->double(),
                'maxLat' => $this->double(),
                'minLng' => $this->double(),
                'maxLng' => $this->double(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%supgeo}}', ['ownerId', 'ownerSiteId', 'fieldId'], true);
            $this->createIndex(null, '{{%supgeo}}', ['lat', 'lng']);
            $this->createIndex(null, '{{%supgeo}}', ['fieldId', 'minLat', 'maxLat']);

            $this->addForeignKey(null, '{{%supgeo}}', 'ownerId',     '{{%elements}}', 'id', 'CASCADE');
            $this->addForeignKey(null, '{{%supgeo}}', 'ownerSiteId', '{{%sites}}',    'id', 'CASCADE');
            $this->addForeignKey(null, '{{%supgeo}}', 'fieldId',     '{{%fields}}',   'id', 'CASCADE');
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%supgeo}}');
        return true;
    }
}
