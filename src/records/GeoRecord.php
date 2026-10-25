<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\records;

use craft\db\ActiveRecord;

class GeoRecord extends ActiveRecord
{
    public const TABLE_NAME = '{{%supgeo}}';
    public const TABLE_NAME_CLEAN = 'supgeo';

    public static function tableName(): string
    {
        return self::TABLE_NAME;
    }
}
