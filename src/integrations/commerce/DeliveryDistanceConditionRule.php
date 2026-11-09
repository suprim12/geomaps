<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\integrations\commerce;

use Craft;
use craft\base\conditions\BaseNumberConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\MapService;
use yii\base\NotSupportedException;

/**
 * Pro, for Craft Commerce: matches orders by the straight-line distance in kilometres from the store's
 * location (Commerce → Store Management → Store Location) to the shipping address.
 */
class DeliveryDistanceConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc Any value, so distances such as 2.5 km can be entered.
     */
    public int|float|null $step = null;

    public function getLabel(): string
    {
        return Craft::t('geomaps', 'Delivery distance (km)') . (Plugin::getInstance()->isPro() ? '' : ' (Pro)');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    protected function operators(): array
    {
        // Distances are compared, never listed or left empty.
        return [self::OPERATOR_LTE, self::OPERATOR_LT, self::OPERATOR_GTE, self::OPERATOR_GT, self::OPERATOR_BETWEEN];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('The delivery distance condition rule can’t be used in element queries.');
    }

    public function matchElement(ElementInterface $element): bool
    {
        if (!AddressLocation::isAvailable()) {
            return false;
        }
        /** @var Order $element */
        $distance = self::distance($element);

        return $distance !== null && $this->matchValue($distance);
    }

    /**
     * Kilometres from the order's store to its shipping address, or null if either can't be placed.
     */
    public static function distance(Order $order): ?float
    {
        $to = AddressLocation::point($order->getShippingAddress() ?? $order->getEstimatedShippingAddress());
        $from = AddressLocation::point(Commerce::getInstance()->getStoreSettings()->getStoreSettingsById($order->getStore()->id)->getLocationAddress());

        return $to && $from ? round(MapService::distance($from['lat'], $from['lng'], $to['lat'], $to['lng']), 2) : null;
    }
}
