<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */


namespace suprim\geomaps\services;

use yii\base\Component;

/**
 * Point-in-polygon tests for drawn areas (the `contains` search).
 */
class ZoneService extends Component
{
    /**
     * Whether any polygon in a GeoJSON object (Polygon, MultiPolygon, Feature, FeatureCollection or
     * GeometryCollection) contains the point. Holes are respected.
     */
    public function contains(array $geoJson, float $lat, float $lng): bool
    {
        return $this->polygonsContain($this->polygons($geoJson), $lat, $lng);
    }

    /**
     * Every polygon in a GeoJSON object, as lists of rings of [lng, lat] positions.
     * The polygons come from saved content, so callers check their shape before use.
     *
     * @return array<int, mixed>
     */
    public function polygons(array $geoJson): array
    {
        $type = $geoJson['type'] ?? null;

        return match ($type) {
            'Polygon' => is_array($geoJson['coordinates'] ?? null) ? [$geoJson['coordinates']] : [],
            'MultiPolygon' => is_array($geoJson['coordinates'] ?? null) ? array_values($geoJson['coordinates']) : [],
            'Feature' => is_array($geoJson['geometry'] ?? null) ? $this->polygons($geoJson['geometry']) : [],
            'FeatureCollection' => array_merge([], ...array_map(
                fn($feature) => is_array($feature) ? $this->polygons($feature) : [],
                is_array($geoJson['features'] ?? null) ? $geoJson['features'] : []
            )),
            'GeometryCollection' => array_merge([], ...array_map(
                fn($geometry) => is_array($geometry) ? $this->polygons($geometry) : [],
                is_array($geoJson['geometries'] ?? null) ? $geoJson['geometries'] : []
            )),
            default => [],
        };
    }

    /**
     * @param array $polygons Polygons as returned by polygons() — may come from stored user data, so entries are checked.
     */
    public function polygonsContain(array $polygons, float $lat, float $lng): bool
    {
        foreach ($polygons as $polygon) {
            if (is_array($polygon) && $this->isPointInPolygonCoordinates($lat, $lng, $polygon)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bounding box of the polygons’ outer rings, or null when there are none.
     *
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}|null
     */
    public function bounds(array $polygons): ?array
    {
        $minLat = $minLng = INF;
        $maxLat = $maxLng = -INF;

        foreach ($polygons as $polygon) {
            foreach ($polygon[0] ?? [] as $position) {
                if (!isset($position[0], $position[1]) || !is_numeric($position[0]) || !is_numeric($position[1])) {
                    continue;
                }
                $minLng = min($minLng, (float)$position[0]);
                $maxLng = max($maxLng, (float)$position[0]);
                $minLat = min($minLat, (float)$position[1]);
                $maxLat = max($maxLat, (float)$position[1]);
            }
        }

        return $minLat === INF ? null : compact('minLat', 'maxLat', 'minLng', 'maxLng');
    }

    private function isPointInPolygonCoordinates(float $lat, float $lng, array $coordinates): bool
    {
        if (empty($coordinates)) {
            return false;
        }

        if (!$this->raycast($lat, $lng, $coordinates[0])) {
            return false;
        }

        foreach (array_slice($coordinates, 1) as $hole) {
            if ($this->raycast($lat, $lng, $hole)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ray-casting algorithm for point-in-polygon.
     * Counts how many times a ray from the point crosses the polygon boundary.
     * Odd = inside, even = outside.
     *
     * @param  array $ring  Array of [lng, lat] pairs (GeoJSON order)
     */
    private function raycast(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        // Ignore malformed positions rather than erroring on user-supplied GeoJSON.
        $ring = array_values(array_filter($ring, fn($p) => is_array($p) && isset($p[0], $p[1]) && is_numeric($p[0]) && is_numeric($p[1])));
        $n = count($ring);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            if (
                (($yi > $lat) !== ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)
            ) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
