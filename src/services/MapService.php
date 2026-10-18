<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\services;

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
use sup\craftgeo\fields\GeoField;
use sup\craftgeo\models\GeoValue;
use sup\craftgeo\records\GeoRecord;
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

        if (!$value || !$value->hasCoordinates()) {
            Db::delete(GeoRecord::TABLE_NAME, $key);
            return;
        }

        Db::upsert(GeoRecord::TABLE_NAME, $key + [
            'lat' => $value->lat,
            'lng' => $value->lng,
        ], [
            'lat' => $value->lat,
            'lng' => $value->lng,
        ]);
    }

    /**
     * Rebuilds the lookup table from stored field content. Returns the number of rows written.
     */
    public function rebuildLookup(): int
    {
        $count = 0;

        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if (!$field instanceof GeoField) {
                continue;
            }

            Db::delete(GeoRecord::TABLE_NAME, ['fieldId' => $field->id]);

            $rows = (new Query())
                ->select(['es.elementId', 'es.siteId', 'es.content'])
                ->from(['es' => Table::ELEMENTS_SITES])
                ->innerJoin(['e' => Table::ELEMENTS], '[[e.id]] = [[es.elementId]]')
                ->where(['e.draftId' => null, 'e.revisionId' => null])
                ->andWhere(['not', ['es.content' => null]]);

            foreach ($rows->each() as $row) {
                $content = Json::decodeIfJson($row['content']);

                if (!is_array($content)) {
                    continue;
                }

                // Content is keyed by field layout element UID, so check every instance of this field.
                foreach ($this->layoutElementUids($field) as $uid) {
                    $value = GeoValue::fromArray($content[$uid] ?? null);

                    if ($value && $value->hasCoordinates()) {
                        Db::upsert(GeoRecord::TABLE_NAME, [
                            'ownerId' => $row['elementId'],
                            'ownerSiteId' => $row['siteId'],
                            'fieldId' => $field->id,
                            'lat' => $value->lat,
                            'lng' => $value->lng,
                        ], ['lat' => $value->lat, 'lng' => $value->lng]);
                        $count++;
                        break;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * @return string[]
     */
    private function layoutElementUids(GeoField $field): array
    {
        $uids = [];

        foreach (Craft::$app->getFields()->getAllLayouts() as $layout) {
            foreach ($layout->getCustomFieldElements() as $layoutElement) {
                if ($layoutElement->getField()->id === $field->id) {
                    $uids[] = $layoutElement->uid;
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
                ->andWhere(['not', ['g.lat' => null]]),
            ];

            return $value === ':empty:' ? ['not', $exists] : $exists;
        }

        if (!is_array($value) || !$query?->subQuery) {
            return null;
        }

        $location = $value['coordinate'] ?? $value['location'] ?? null;

        if ($location instanceof GeoValue) {
            $location = ['lat' => $location->lat, 'lng' => $location->lng];
        }

        if ($location === null) {
            return null;
        }

        $origin = GeoService::resolveLocation($location, $value['country'] ?? null);

        if (!$origin) {
            // Location couldn’t be resolved, so nothing can match.
            return false;
        }

        $unit = GeoService::normalizeUnit($value['unit'] ?? null);
        $radius = max(0.0, (float)($value['radius'] ?? 50));
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

        // Bounding box first so the lat/lng index can narrow rows before the trig runs.
        $latDelta = $radius / $perDegree;
        $lngDelta = $radius / max(0.000001, $perDegree * abs(cos(deg2rad($lat))));

        return [
            'and',
            ['between', "$alias.lat", $lat - $latDelta, $lat + $latDelta],
            ['between', "$alias.lng", $lng - $lngDelta, $lng + $lngDelta],
            new Expression(sprintf('%s <= %F', $distanceSql, $radius)),
        ];
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

        return is_array($orderBy)
            && count($orderBy) === 1
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
            $value = $event->element?->getFieldValue($handle);

            if ($value instanceof GeoValue && $value->hasCoordinates()) {
                $value->distance = round(self::distance($lat, $lng, $value->lat, $value->lng, $perDegree), 3);
            }
        };

        $query->on(ElementQuery::EVENT_AFTER_POPULATE_ELEMENT, $handler);
        $this->_populators[$query] = $handler;
    }
}
