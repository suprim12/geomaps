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
 * Marker clustering for `embed()` maps (Pro). Loads after the embed bundle, which provides Leaflet.
 */
class ClusterAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/assets';
        $this->depends = [EmbedAsset::class];
        $this->css = ['css/cluster.css'];
        $this->js = ['js/cluster.js'];

        parent::init();
    }
}
