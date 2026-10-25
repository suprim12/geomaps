<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\variables;

use suprim\geomaps\models\EmbedMap;
use suprim\geomaps\models\StaticMap;

class GeoVariable
{
    public function img(array $options = []): ?string
    {
        $map = new StaticMap();
        return $map->img($options);
    }

    public function imgSrcSet(array $options = []): ?string
    {
        $map = new StaticMap();
        return $map->imgSrcSet($options);
    }

    public function staticMap(array $options = []): StaticMap
    {
        $map = new StaticMap();
        foreach ($options as $key => $value) {
            if (property_exists($map, $key)) {
                $map->$key = $value;
            }
        }
        return $map;
    }

    public function embed(array $options = []): \Twig\Markup
    {
        return (new EmbedMap())->embed($options);
    }

    public function embedMap(array $options = []): EmbedMap
    {
        $map = new EmbedMap();
        foreach ($options as $key => $value) {
            if (property_exists($map, $key)) {
                $map->$key = $value;
            }
        }
        return $map;
    }
}
