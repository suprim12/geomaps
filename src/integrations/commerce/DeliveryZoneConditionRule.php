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
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\db\Query;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\Plugin;
use suprim\geomaps\records\GeoRecord;
use yii\base\NotSupportedException;

/**
 * Pro, for Craft Commerce: matches orders whose shipping address is inside chosen drawn zones.
 *
 * The zones are entries with areas drawn in a Geo Maps field, such as a Delivery Zones section.
 */
class DeliveryZoneConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('geomaps', 'Delivery address is in zone') . (Plugin::getInstance()->isPro() ? '' : ' (Pro)');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * Entries with drawn areas, grouped by section.
     */
    protected function options(): array
    {
        $ids = (new Query())
            ->select('ownerId')
            ->distinct()
            ->from(GeoRecord::TABLE_NAME)
            ->where(['not', ['shape' => null]])
            ->column();

        $options = [];
        foreach (Entry::find()->id($ids ?: [0])->status(null)->orderBy(['title' => SORT_ASC])->all() as $entry) {
            $options[$entry->id] = $entry->getSection()?->name . ': ' . $entry->title;
        }
        asort($options);

        return $options;
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('The delivery zone condition rule can’t be used in element queries.');
    }

    public function matchElement(ElementInterface $element): bool
    {
        if (!AddressLocation::isAvailable()) {
            return false;
        }
        /** @var Order $element */
        $point = AddressLocation::point($element->getShippingAddress() ?? $element->getEstimatedShippingAddress());
        // An order that can't be placed on the map matches neither “is one of” nor “is not one of”.
        if ($point === null) {
            return false;
        }
        $inside = $this->inAnyZone($point);

        return $this->operator === self::OPERATOR_IN ? $inside : !$inside;
    }

    /**
     * @param array{lat: float, lng: float} $point
     */
    private function inAnyZone(array $point): bool
    {
        $ids = array_filter(array_map('intval', $this->getValues()));
        if (!$ids) {
            return false;
        }

        foreach (Entry::find()->id($ids)->status(null)->site('*')->unique()->all() as $zone) {
            foreach ($zone->getFieldLayout()?->getCustomFields() ?? [] as $field) {
                $value = $zone->getFieldValue($field->handle);
                if ($value instanceof GeoValue && $value->contains($point)) {
                    return true;
                }
            }
        }

        return false;
    }
}
