<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\UrlHelper;
use sup\craftgeo\Geo;

/**
 * One-click import from Maps by Ether (ether/simplemap).
 */
class ImportUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('geo', 'Geo Maps Import');
    }

    public static function id(): string
    {
        return 'geo-import';
    }

    public static function icon(): ?string
    {
        return 'location-dot';
    }

    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('geo/utilities/import', [
            'fields' => Geo::getInstance()->import->getSimpleMapFields(),
            'simpleMapInstalled' => Craft::$app->getPlugins()->isPluginInstalled('simplemap'),
            'pluginsUrl' => UrlHelper::cpUrl('settings/plugins'),
        ]);
    }
}
