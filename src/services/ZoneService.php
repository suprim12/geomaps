<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo\services;

use craft\helpers\Json;
use yii\base\Component;

class ZoneService extends Component
{
    public function determineZonesFromLatLng(float $lat, float $lng, array $electoratesData): array
    {
        $result = [];

        foreach ($electoratesData['features'] as $feature) {
            $name = strtolower($feature['properties']['electorate']);

            if ($this->isLatLngInGeometry($lat, $lng, $feature['geometry'])) {
                $result[] = ['name' => $name, 'contained' => true];
            }
        }

        return $result;
    }

    private function isLatLngInGeometry(float $lat, float $lng, mixed $geometry): bool
    {
        if (is_string($geometry)) {
            $geometry = Json::decode($geometry);
        }

        $type = $geometry['type'] ?? null;

        if (!in_array($type, ['Polygon', 'MultiPolygon'], true) || !isset($geometry['coordinates'])) {
            return false;
        }

        if ($type === 'Polygon') {
            return $this->isPointInPolygonCoordinates($lat, $lng, $geometry['coordinates']);
        }

        foreach ($geometry['coordinates'] as $polygonCoordinates) {
            if ($this->isPointInPolygonCoordinates($lat, $lng, $polygonCoordinates)) {
                return true;
            }
        }

        return false;
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
