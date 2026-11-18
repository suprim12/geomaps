<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\behaviors;

use Craft;
use craft\elements\Address;
use craft\elements\db\AddressQuery;
use craft\elements\db\ElementQuery;
use craft\events\CancelableEvent;
use craft\events\PopulateElementEvent;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\GeoService;
use suprim\geomaps\services\MapService;
use yii\base\Behavior;
use yii\db\Expression;

/**
 * Pro: `near()` on address queries finds Craft addresses near a place, nearest first, with `address.distance` set.
 *
 *   {% set nearby = craft.addresses.owner(store).near({ location: cart.shippingAddress, radius: 30 }).all() %}
 *
 * Addresses need coordinates, which Geo Maps Pro looks up in the background (see `geocodeAddresses`).
 *
 * @property AddressQuery $owner
 */
class AddressQueryBehavior extends Behavior
{
    /** @var array{location?: mixed, radius?: int|float|null, unit?: string, country?: string}|null */
    public ?array $geomapsNear = null;

    public function events(): array
    {
        return [ElementQuery::EVENT_AFTER_PREPARE => 'applyNear'];
    }

    /**
     * Narrows the query to addresses within `radius` (default 50) of `location`, nearest first.
     * `location` is an address, `{ lat, lng }`, another Craft address or a location field’s value; `unit` is
     * `km` or `mi`. A `radius` of null sorts every address with coordinates by distance.
     */
    public function near(?array $options): AddressQuery
    {
        $this->geomapsNear = $options;

        if ($options !== null && Plugin::getInstance()->isPro() && MapService::usesDefaultOrder($this->owner)) {
            $this->owner->orderBy(['distance' => SORT_ASC]);
        }

        return $this->owner;
    }

    public function applyNear(CancelableEvent $event): void
    {
        $options = $this->geomapsNear;
        $query = $this->owner;

        if ($options === null || !$query->subQuery) {
            return;
        }

        if (!Plugin::getInstance()->isPro()) {
            Craft::warning('Geo Maps: near() on addresses needs Geo Maps Pro, so it was ignored.', __METHOD__);
            // Sorted by distance when Pro was active as near() was called: keep that valid.
            $query->subQuery->addSelect(['distance' => new Expression('NULL')]);
            return;
        }

        $origin = GeoService::resolveLocation($options['location'] ?? null, $options['country'] ?? null);
        if (!$origin) {
            // The place couldn't be found, so nothing is near it. (The column keeps the order by distance valid.)
            $query->subQuery->addSelect(['distance' => new Expression('NULL')])->andWhere('0 = 1');
            return;
        }

        $unit = GeoService::normalizeUnit($options['unit'] ?? null);
        $perDegree = $unit === 'mi' ? MapService::MI_PER_DEGREE : MapService::KM_PER_DEGREE;
        $lat = (float)$origin['lat'];
        $lng = (float)$origin['lng'];

        // Craft stores an address's coordinates as text.
        $latSql = "CAST(NULLIF([[addresses.latitude]], '') AS DECIMAL(10,7))";
        $lngSql = "CAST(NULLIF([[addresses.longitude]], '') AS DECIMAL(10,7))";
        $distanceSql = MapService::distanceSql($latSql, $lngSql, $lat, $lng, $perDegree);

        $query->subQuery
            ->addSelect(['distance' => new Expression($distanceSql)])
            ->andWhere(['not', ['addresses.latitude' => null]])
            ->andWhere(['not', ['addresses.latitude' => '']])
            ->andWhere(['not', ['addresses.longitude' => null]])
            ->andWhere(['not', ['addresses.longitude' => '']]);

        $radius = array_key_exists('radius', $options) && $options['radius'] === null ? null : max(0.0, (float)($options['radius'] ?? 50));
        if ($radius !== null) {
            $query->subQuery->andWhere(MapService::radiusCondition(new Expression($latSql), new Expression($lngSql), $distanceSql, $lat, $lng, $radius, $perDegree));
        }

        $query->on(ElementQuery::EVENT_AFTER_POPULATE_ELEMENT, function(PopulateElementEvent $e) use ($lat, $lng, $perDegree) {
            $address = $e->element;
            if ($address instanceof Address && is_numeric($address->latitude) && is_numeric($address->longitude)) {
                $address->attachBehavior('geomapsDistance', new DistanceBehavior([
                    'distance' => round(MapService::distance($lat, $lng, (float)$address->latitude, (float)$address->longitude, $perDegree), 3),
                ]));
            }
        });
    }
}
