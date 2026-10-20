<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim <support@suprim.com.au> <https://suprimgolay.com.np>
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
