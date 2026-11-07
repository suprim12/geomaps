<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\web;

use craft\web\AssetBundle;

/**
 * Locator component layout and behaviour (`craft.geomaps.locator()`, Pro).
 */
class LocatorAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/assets';
        $this->depends = [AddressAsset::class, EmbedAsset::class];
        $this->css = ['css/locator.css'];
        $this->js = ['js/locator.js'];

        parent::init();
    }
}
