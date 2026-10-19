<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
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
