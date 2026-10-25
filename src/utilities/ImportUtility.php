<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\UrlHelper;
use suprim\geomaps\Plugin;

/**
 * One-click import from Maps by Ether (ether/simplemap).
 */
class ImportUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('geomaps', 'Geo Maps Import');
    }

    public static function id(): string
    {
        return 'geomaps-import';
    }

    public static function icon(): ?string
    {
        return 'location-dot';
    }

    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('geomaps/utilities/import', [
            'fields' => Plugin::getInstance()->import->getImportableFields(),
            'simpleMapInstalled' => Craft::$app->getPlugins()->isPluginInstalled('simplemap'),
            'pluginsUrl' => UrlHelper::cpUrl('settings/plugins'),
        ]);
    }
}
