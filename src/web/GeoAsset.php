<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace suprim\geomaps\web;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class GeoAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/assets';

        $this->depends = [
            CpAsset::class,
        ];


        $this->css = [
            'css/supgeo.css',
        ];

        $this->js = [
            'js/supgeo.js',
        ];

        // An ES module, so the GeoJSON editor can be loaded only when it's opened.
        $this->jsOptions = ['type' => 'module'];

        parent::init();
    }
}
