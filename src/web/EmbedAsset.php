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
 * Leaflet for front-end embedded maps.
 */
class EmbedAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/assets';
        $this->css = ['css/embed.css'];
        $this->js = ['js/embed.js'];

        parent::init();
    }
}
