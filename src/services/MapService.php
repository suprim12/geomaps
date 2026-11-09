<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\services;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQuery;
use craft\elements\db\OrderByPlaceholderExpression;
use craft\events\PopulateElementEvent;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\Plugin;
use suprim\geomaps\records\GeoRecord;
use WeakMap;
use yii\base\Component;
use yii\db\Expression;

/**
 * Maintains the `supgeo` coordinate lookup table and applies proximity searches to element queries.
 */
class MapService extends Component
{
    /** Kilometres / miles per degree of latitude. */
    public const KM_PER_DEGREE = 111.045;
    public const MI_PER_DEGREE = 69.0;

    /** @var WeakMap<ElementQuery, callable> Distance populators already attached to a query. */
    private WeakMap $_populators;

    public function init(): void
    {
        parent::init();
        $this->_populators = new WeakMap();
    }

    // Lookup table
    // -------------------------------------------------------------------------

    /**
     * Writes (or clears) the lookup row for an element’s field value.
     */
    public function saveLookup(GeoField $field, ElementInterface $element, ?GeoValue $value): void
    {
        $key = [
            'ownerId' => $element->id,
            'ownerSiteId' => $element->siteId,
            'fieldId' => $field->id,
        ];

        $columns = $value ? $this->lookupColumns($value) : null;

        if (!$columns) {
            Db::delete(GeoRecord::TABLE_NAME, $key);
            return;
        }

        Db::upsert(GeoRecord::TABLE_NAME, $key + $columns, $columns);
    }

    /**
     * Index columns for a value: its point (for distance search) and its polygons (for `contains` search).
     * Returns null when there’s nothing to index.
     */
    public function lookupColumns(GeoValue $value): ?array
    {
        $polygons = [];
        $data = $value->getGeoJsonData();
        if ($data) {
            $polygons = Plugin::getInstance()->zones->polygons($data);
        }
        $bounds = $polygons ? Plugin::getInstance()->zones->bounds($polygons) : null;

        if (!$value->hasCoordinates() && !$bounds) {
            return null;
        }

        return [
            'lat' => $value->lat,
            'lng' => $value->lng,
            'shape' => $bounds ? Json::encode($polygons) : null,
            'minLat' => $bounds['minLat'] ?? null,
            'maxLat' => $bounds['maxLat'] ?? null,
            'minLng' => $bounds['minLng'] ?? null,
            'maxLng' => $bounds['maxLng'] ?? null,
        ];
    }

    /**
     * Rebuilds the lookup table from stored field content. Returns the number of rows written.
     */
    public function rebuildLookup(?GeoField $only = null): int
    {
        $count = 0;
        $fields = $only ? [$only] : Craft::$app->getFields()->getAllFields();

        foreach ($fields as $field) {
            if (!$field instanceof GeoField) {
                continue;
            }

            Db::delete(GeoRecord::TABLE_NAME, ['fieldId' => $field->id]);

            // Content is keyed by field layout element UID, so look the key up by each element's layout.
            $keyByLayout = self::layoutElementUidsByLayout($field->uid);
            if (!$keyByLayout) {
                continue;
            }

            $rows = (new Query())
                ->select(['es.elementId', 'es.siteId', 'es.content', 'e.fieldLayoutId'])
                ->from(['es' => Table::ELEMENTS_SITES])
                ->innerJoin(['e' => Table::ELEMENTS], '[[e.id]] = [[es.elementId]]')
                ->where(['e.draftId' => null, 'e.revisionId' => null, 'e.fieldLayoutId' => array_keys($keyByLayout)])
                ->andWhere(['not', ['es.content' => null]]);

            foreach ($rows->each(500) as $row) {
                $content = Json::decodeIfJson($row['content']);
                $value = is_array($content) ? GeoValue::fromArray($content[$keyByLayout[$row['fieldLayoutId']]] ?? null) : null;
                $columns = $value ? $this->lookupColumns($value) : null;

                if ($columns) {
                    Db::upsert(GeoRecord::TABLE_NAME, [
                        'ownerId' => $row['elementId'],
                        'ownerSiteId' => $row['siteId'],
                        'fieldId' => $field->id,
                    ] + $columns, $columns);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Returns the layout element UID (content JSON key) used by a field, keyed by field layout ID.
     *
     * @return array<int, string>
     */
    public static function layoutElementUidsByLayout(string $fieldUid): array
    {
        $uids = [];

        foreach (Craft::$app->getFields()->getAllLayouts() as $layout) {
            foreach ($layout->getCustomFieldElements() as $layoutElement) {
                if ($layoutElement->fieldUid === $fieldUid && $layout->id) {
                    $uids[$layout->id] = $layoutElement->uid;
                }
            }
        }

        return $uids;
    }

    // Querying
    // -------------------------------------------------------------------------

    /**
     * Builds the query condition for a Geo field param.
     *
     * Supported values:
     * - `':empty:'` / `':notempty:'`
     * - `{ location: 'Address' | {lat, lng} | GeoValue, coordinate: {lat, lng}, radius: 50, unit: 'km'|'mi', country: 'AU' }`
     * - `{ contains: 'Address' | {lat, lng} | GeoValue }`
     * - Pro: `{ within: {north, south, east, west} | 'south,west,north,east' | GeoValue | GeoJSON }`, optionally with
     *   `location` to sort by distance
     *
     * @param GeoField[] $instances
     * @return array|string|false|null
     */
    public function queryCondition(?ElementQuery $query, array $instances, mixed $value): array|string|false|null
    {
        $fieldIds = array_values(array_unique(array_map(fn(GeoField $field) => $field->id, $instances)));

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if ($value === ':empty:' || $value === ':notempty:' || $value === 'not :empty:') {
            $exists = ['exists', (new Query())
                ->from(['g' => GeoRecord::TABLE_NAME])
                ->where('[[g.ownerId]] = [[elements.id]]')
                ->andWhere('[[g.ownerSiteId]] = [[elements_sites.siteId]]')
                ->andWhere(['g.fieldId' => $fieldIds])
                // A pin or an area both count as a value.
                ->andWhere(['or', ['not', ['g.lat' => null]], ['not', ['g.minLat' => null]]]),
            ];

            return $value === ':empty:' ? ['not', $exists] : $exists;
        }

        if (!is_array($value) || !$query?->subQuery) {
            return null;
        }

        // GraphQL passes coordinates for `contains` separately.
        if (isset($value['containsCoordinate']) && !isset($value['contains'])) {
            $value['contains'] = $value['containsCoordinate'];
        }

        if (array_key_exists('contains', $value)) {
            return $this->containsCondition($fieldIds, $value['contains'], $value['country'] ?? null);
        }

        // GraphQL passes a GeoJSON area separately.
        if (isset($value['withinGeoJson']) && !isset($value['within'])) {
            $value['within'] = $value['withinGeoJson'];
        }

        $within = null;
        if (isset($value['within'])) {
            if (Plugin::getInstance()->isPro()) {
                $within = $this->withinCondition($fieldIds, $value['within']);
                if ($within === false) {
                    return false;
                }
            } else {
                Craft::warning('Geo Maps: `within` needs Geo Maps Pro, so it was ignored.', __METHOD__);
            }
        }

        $location = $value['coordinate'] ?? $value['location'] ?? null;

        if ($location instanceof GeoValue) {
            $location = ['lat' => $location->lat, 'lng' => $location->lng];
        }

        if ($location === null) {
            return $within;
        }

        $origin = GeoService::resolveLocation($location, $value['country'] ?? null);

        if (!$origin) {
            // Location couldn’t be resolved, so nothing can match.
            return false;
        }

        $unit = GeoService::normalizeUnit($value['unit'] ?? null);
        // With an area, the location only sorts by distance unless a radius is given too.
        $radius = $within !== null && !isset($value['radius']) ? null : max(0.0, (float)($value['radius'] ?? 50));
        $perDegree = $unit === 'mi' ? self::MI_PER_DEGREE : self::KM_PER_DEGREE;

        $alias = 'supgeo_' . StringHelper::randomString(6);
        $lat = (float)$origin['lat'];
        $lng = (float)$origin['lng'];

        $query->subQuery->innerJoin(
            [$alias => GeoRecord::TABLE_NAME],
            [
                'and',
                "[[$alias.ownerId]] = [[elements.id]]",
                "[[$alias.ownerSiteId]] = [[elements_sites.siteId]]",
                ["$alias.fieldId" => $fieldIds],
            ]
        );

        // Spherical law of cosines, clamped so floating point error can’t push ACOS out of range.
        $distanceSql = sprintf(
            '(%F * DEGREES(ACOS(LEAST(1.0, GREATEST(-1.0, COS(RADIANS(%F)) * COS(RADIANS([[%s.lat]])) * COS(RADIANS(%F) - RADIANS([[%s.lng]])) + SIN(RADIANS(%F)) * SIN(RADIANS([[%s.lat]])))))))',
            $perDegree, $lat, $alias, $lng, $alias, $lat, $alias,
        );

        $query->subQuery->addSelect(['distance' => new Expression($distanceSql)]);

        // Order by distance unless the template asked for something else.
        if ($this->usesDefaultOrder($query)) {
            $query->orderBy(['distance' => SORT_ASC]);
        }

        $this->attachDistancePopulator($query, $instances[0]->handle, $lat, $lng, $perDegree);

        if ($radius === null) {
            return $within;
        }

        // Bounding box first so the lat/lng index can narrow rows before the trig runs.
        $latDelta = $radius / $perDegree;
        $lngDelta = $radius / max(0.000001, $perDegree * abs(cos(deg2rad($lat))));
        $condition = ['and', ['between', "$alias.lat", $lat - $latDelta, $lat + $latDelta]];

        // Near the poles the box covers every longitude; across the ±180° line it wraps to the other side.
        if ($lat + $latDelta < 90 && $lat - $latDelta > -90 && $lngDelta < 180) {
            $west = $lng - $lngDelta;
            $east = $lng + $lngDelta;

            $condition[] = match (true) {
                $west < -180 => ['or', ['>=', "$alias.lng", $west + 360], ['<=', "$alias.lng", $east]],
                $east > 180 => ['or', ['>=', "$alias.lng", $west], ['<=', "$alias.lng", $east - 360]],
                default => ['between', "$alias.lng", $west, $east],
            };
        }

        $condition[] = new Expression(sprintf('%s <= %F', $distanceSql, $radius));

        return $within !== null ? ['and', $within, $condition] : $condition;
    }

    /**
     * Condition matching elements whose point is inside an area: a bounding box, or the polygons of a
     * GeoValue or GeoJSON object (holes respected).
     *
     * @param int[] $fieldIds
     */
    private function withinCondition(array $fieldIds, mixed $area): array|false
    {
        $bounds = self::parseBounds($area);

        if ($bounds) {
            $condition = ['and', ['g.fieldId' => $fieldIds], ['between', 'g.lat', $bounds['south'], $bounds['north']]];
            // A box across the ±180° line has its west edge east of its east edge.
            $condition[] = $bounds['west'] <= $bounds['east']
                ? ['between', 'g.lng', $bounds['west'], $bounds['east']]
                : ['or', ['>=', 'g.lng', $bounds['west']], ['<=', 'g.lng', $bounds['east']]];

            return ['exists', (new Query())
                ->from(['g' => GeoRecord::TABLE_NAME])
                ->where('[[g.ownerId]] = [[elements.id]]')
                ->andWhere('[[g.ownerSiteId]] = [[elements_sites.siteId]]')
                ->andWhere($condition),
            ];
        }

        $polygons = self::areaPolygons($area);
        if (!$polygons) {
            return false;
        }

        $zones = Plugin::getInstance()->zones;
        $box = $zones->bounds($polygons);
        if (!$box) {
            return false;
        }

        $matches = [];
        $candidates = (new Query())
            ->select(['ownerId', 'ownerSiteId', 'lat', 'lng'])
            ->from(GeoRecord::TABLE_NAME)
            ->where(['fieldId' => $fieldIds])
            ->andWhere(['between', 'lat', $box['minLat'], $box['maxLat']])
            ->andWhere(['between', 'lng', $box['minLng'], $box['maxLng']]);

        foreach ($candidates->each() as $row) {
            if ($zones->polygonsContain($polygons, (float)$row['lat'], (float)$row['lng'])) {
                $matches[] = ['elements.id' => (int)$row['ownerId'], 'elements_sites.siteId' => (int)$row['ownerSiteId']];
            }
        }

        return $matches ? ['in', ['elements.id', 'elements_sites.siteId'], $matches] : false;
    }

    /**
     * A bounding box as `south, west, north, east`, from `{ north, south, east, west }`,
     * `[[south, west], [north, east]]` or the string `'south,west,north,east'`. Null if it isn't one.
     *
     * @return array{south: float, west: float, north: float, east: float}|null
     */
    public static function parseBounds(mixed $area): ?array
    {
        if (is_string($area)) {
            $parts = array_map('trim', explode(',', $area));
            if (count($parts) !== 4) {
                return null;
            }
            $area = ['south' => $parts[0], 'west' => $parts[1], 'north' => $parts[2], 'east' => $parts[3]];
        } elseif (is_array($area) && array_is_list($area) && count($area) === 2 && is_array($area[0]) && is_array($area[1])) {
            $area = ['south' => $area[0][0] ?? null, 'west' => $area[0][1] ?? null, 'north' => $area[1][0] ?? null, 'east' => $area[1][1] ?? null];
        }

        if (!is_array($area)) {
            return null;
        }

        $box = [];
        foreach (['south' => 90, 'west' => 180, 'north' => 90, 'east' => 180] as $edge => $max) {
            $v = $area[$edge] ?? null;
            if (!is_numeric($v) || abs((float)$v) > $max) {
                return null;
            }
            $box[$edge] = (float)$v;
        }

        return $box['south'] <= $box['north'] ? $box : null;
    }

    /**
     * The polygons of a GeoValue, a GeoJSON array or a GeoJSON string.
     */
    private static function areaPolygons(mixed $area): array
    {
        if ($area instanceof GeoValue) {
            $area = $area->getGeoJsonData();
        } elseif (is_string($area)) {
            $area = Json::decodeIfJson($area);
        }

        return is_array($area) ? Plugin::getInstance()->zones->polygons($area) : [];
    }

    /**
     * Condition matching elements whose polygons contain a point (an address, `{ lat, lng }` or GeoValue).
     *
     * Candidates are narrowed by bounding box in SQL, then checked exactly (holes respected).
     *
     * @param int[] $fieldIds
     */
    private function containsCondition(array $fieldIds, mixed $location, ?string $country): array|false
    {
        if ($location instanceof GeoValue) {
            $location = ['lat' => $location->lat, 'lng' => $location->lng];
        }

        $point = GeoService::resolveLocation($location, $country);

        if (!$point) {
            return false;
        }

        $lat = (float)$point['lat'];
        $lng = (float)$point['lng'];
        $zones = Plugin::getInstance()->zones;
        $matches = [];

        $candidates = (new Query())
            ->select(['ownerId', 'ownerSiteId', 'shape'])
            ->from(GeoRecord::TABLE_NAME)
            ->where(['fieldId' => $fieldIds])
            ->andWhere(['<=', 'minLat', $lat])
            ->andWhere(['>=', 'maxLat', $lat])
            ->andWhere(['<=', 'minLng', $lng])
            ->andWhere(['>=', 'maxLng', $lng]);

        foreach ($candidates->each() as $row) {
            $polygons = Json::decodeIfJson((string)$row['shape']);
            if (is_array($polygons) && $zones->polygonsContain($polygons, $lat, $lng)) {
                $matches[] = ['elements.id' => (int)$row['ownerId'], 'elements_sites.siteId' => (int)$row['ownerSiteId']];
            }
        }

        if (!$matches) {
            return false;
        }

        return ['in', ['elements.id', 'elements_sites.siteId'], $matches];
    }

    /**
     * Great-circle distance using the same formula as the SQL search.
     */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2, float $perDegree = self::KM_PER_DEGREE): float
    {
        $cos = cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lng1) - deg2rad($lng2))
            + sin(deg2rad($lat1)) * sin(deg2rad($lat2));

        return $perDegree * rad2deg(acos(max(-1.0, min(1.0, $cos))));
    }

    private function usesDefaultOrder(ElementQuery $query): bool
    {
        $orderBy = $query->orderBy;

        if (empty($orderBy)) {
            return true;
        }

        return count($orderBy) === 1
            && ($orderBy[0] ?? null) instanceof OrderByPlaceholderExpression;
    }

    /**
     * Sets `distance` on each result’s field value.
     */
    private function attachDistancePopulator(ElementQuery $query, string $handle, float $lat, float $lng, float $perDegree): void
    {
        if (isset($this->_populators[$query])) {
            $query->off(ElementQuery::EVENT_AFTER_POPULATE_ELEMENT, $this->_populators[$query]);
        }

        $handler = function(PopulateElementEvent $event) use ($handle, $lat, $lng, $perDegree) {
            $value = $event->element->getFieldValue($handle);

            if ($value instanceof GeoValue && $value->hasCoordinates()) {
                $value->distance = round(self::distance($lat, $lng, $value->lat, $value->lng, $perDegree), 3);
            }
        };

        $query->on(ElementQuery::EVENT_AFTER_POPULATE_ELEMENT, $handler);
        $this->_populators[$query] = $handler;
    }
}
