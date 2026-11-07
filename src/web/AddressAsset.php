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
 * Address autocomplete for front-end forms (`craft.geomaps.addressInput()`).
 */
class AddressAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/assets';
        $this->css = ['css/address.css'];
        $this->js = ['js/address.js'];
        $this->jsOptions = ['type' => 'module'];

        parent::init();
    }
}
