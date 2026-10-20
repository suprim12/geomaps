<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim <support@suprim.com.au> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\models;

use Craft;
use craft\base\Model;
use suprim\geomaps\Plugin;

class StaticMap extends Model
{
    public mixed  $center = null;
    public mixed  $centerFallback = null;
    public mixed  $mapId = null;
    public int    $width = 640;
    public int    $height = 400;
    public int    $zoom = 14;
    public int    $scale = 1;
    public array  $markers = [];
    public string $format = 'png';
    public string $mapType = 'roadmap';
    public mixed $colorScheme = null;
    private ?string $_resolvedCenter = null;

    public function img(array $options = []): ?string
    {
        // Options apply to this image only, so `img({ zoom: 10 })` doesn't change the next `img()`.
        $map = clone $this;
        $map->setOptions($options);
        $map->_resolvedCenter = null;
        return $map->buildUrl(1);
    }

    public function imgSrcSet(array $options = []): ?string
    {
        $map = clone $this;
        $map->setOptions($options);
        $map->_resolvedCenter = $map->resolveCenter();
        if ($map->_resolvedCenter === null && empty($map->markers)) {
            return null;
        }
        $url1x = $map->buildUrl(1);
        $url2x = $map->buildUrl(2);

        if (!$url1x || !$url2x) {
            return null;
        }

        return "{$url1x} 1x, {$url2x} 2x";
    }

    private function setOptions(array $options): void
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    private function buildUrl(int $scale = 1): ?string
    {
        $settings = Plugin::getInstance()->getSettings();
        $apiKey = $settings->getMapToken();

        if (!$apiKey) {
            Craft::warning('GeoMaps: mapToken is required for static maps', 'geomaps');
            return null;
        }
        $center = $this->_resolvedCenter ?? $this->resolveCenter();

        if (!$center && count($this->markers) <= 0) {
            Craft::warning('GeoMaps: could not resolve center for static map', 'geomaps');
            return null;
        }

        $params = array_filter([
            'center' => $center,
            'size' => $this->width . 'x' . $this->height,
            'zoom' => $this->zoom,
            'scale' => $scale,
            'format' => $this->format,
            'maptype' => $this->mapType ?: 'roadmap',
            'map_id' => $this->mapId ?: null,
            'colorScheme' => $this->colorScheme ?: null,
            'key' => $apiKey,
        ], fn($v) => $v !== null && $v !== '');

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        foreach ($this->buildMarkerParams($center) as $marker) {
            $query .= '&markers=' . rawurlencode($marker);
        }

        return 'https://maps.googleapis.com/maps/api/staticmap?' . $query;
    }

    private function resolveCenter(): ?string
    {
        $center = $this->center;

        if (is_array($center)) {
            $lat = $center['lat'] ?? $center[0] ?? null;
            $lng = $center['lng'] ?? $center[1] ?? null;

            if (is_numeric($lat) && is_numeric($lng)) {
                return "{$lat},{$lng}";
            }
        }

        if (is_string($center) && !empty(trim($center))) {
            return trim($center);
        }

        if ($this->centerFallback) {
            $fb = $this->centerFallback;
            $lat = $fb['lat'] ?? $fb[0] ?? null;
            $lng = $fb['lng'] ?? $fb[1] ?? null;

            if (is_numeric($lat) && is_numeric($lng)) {
                return "{$lat},{$lng}";
            }
        }

        // Last resort: derive center from the first marker's location
        $first = $this->markers[0] ?? null;
        if ($first !== null) {
            $location = $first['location'] ?? null;

            if (is_array($location)) {
                $lat = $location['lat'] ?? $location[0] ?? null;
                $lng = $location['lng'] ?? $location[1] ?? null;

                if (is_numeric($lat) && is_numeric($lng)) {
                    return "{$lat},{$lng}";
                }
            }

            if (is_string($location) && !empty(trim($location))) {
                return trim($location);
            }
        }

        return null;
    }

    private function buildMarkerParams(?string $center): array
    {
        $markers = $this->markers;

        if (empty($markers)) {
            return $center ? ["color:red|{$center}"] : [];
        }

        $params = [];

        foreach ($markers as $marker) {
            $parts = [];

            $color = $marker['color'] ?? '#ff0000';
            $color = ltrim($color, '#');
            $parts[] = "color:0x{$color}";

            if (!empty($marker['label'])) {
                $label = strtoupper(substr($marker['label'], 0, 1));
                $parts[] = "label:{$label}";
            }

            $location = $marker['location'] ?? null;

            if (is_array($location)) {
                $lat = $location['lat'] ?? $location[0] ?? null;
                $lng = $location['lng'] ?? $location[1] ?? null;
                if (is_numeric($lat) && is_numeric($lng)) {
                    $parts[] = "{$lat},{$lng}";
                }
            } elseif (is_string($location) && !empty($location)) {
                $parts[] = $location;
            } elseif ($center) {
                $parts[] = $center;
            }

            $params[] = implode('|', $parts);
        }

        return $params;
    }
}
