<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\variables;

use suprim\geomaps\models\AddressInput;
use suprim\geomaps\models\EmbedMap;
use suprim\geomaps\models\Locator;
use suprim\geomaps\models\StaticMap;
use suprim\geomaps\services\GeoService;
use suprim\geomaps\services\MapService;

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

    /**
     * An address search box with suggestions for front-end forms.
     */
    public function addressInput(array $options = []): \Twig\Markup
    {
        return (new AddressInput())->render($options);
    }

    /**
     * The locator component: address search, "use my location", a results list and a map.
     */
    public function locator(array $options = []): \Twig\Markup
    {
        return (new Locator())->render($options);
    }

    /**
     * The straight-line distance between two places, or null when either can't be found. Each place is an address,
     * `{ lat, lng }`, a Craft address or a location field's value; `unit` is `km` or `mi`.
     *
     *   {{ craft.geomaps.distance(entry.location, cart.shippingAddress)|number_format(1) }} km
     */
    public function distance(mixed $from, mixed $to, string $unit = 'km'): ?float
    {
        $a = GeoService::resolveLocation($from);
        $b = GeoService::resolveLocation($to);
        if (!$a || !$b) {
            return null;
        }

        $perDegree = GeoService::normalizeUnit($unit) === 'mi' ? MapService::MI_PER_DEGREE : MapService::KM_PER_DEGREE;

        return round(MapService::distance($a['lat'], $a['lng'], $b['lat'], $b['lng'], $perDegree), 3);
    }
}
