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
use craft\db\Query;
use craft\db\Table;
use craft\elements\Address;
use craft\elements\Entry;
use craft\helpers\Db;
use Generator;
use suprim\geomaps\models\Settings;
use suprim\geomaps\Plugin;
use yii\base\Component;

/**
 * Coordinates for Craft’s own addresses: those in Addresses fields, on users and on Commerce orders.
 *
 * Craft stores a latitude and longitude with each address but never fills them in. With Geo Maps Pro they’re
 * looked up in the background, like missing coordinates, so addresses can be searched by distance and shown on maps.
 */
class Addresses extends Component
{
    /** @var array<string, array{lat: float, lng: float}|null> Lookups made during this request. */
    private array $_memo = [];

    /**
     * The address’s latitude and longitude, or the address looked up with the map service (cached).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function point(?Address $address): ?array
    {
        if (!$address) {
            return null;
        }

        if (is_numeric($address->latitude) && is_numeric($address->longitude)) {
            return ['lat' => (float)$address->latitude, 'lng' => (float)$address->longitude];
        }

        $text = $this->text($address);
        if ($text === null) {
            return null;
        }

        $country = $address->countryCode ?: null;
        $key = $text . '|' . $country;
        if (!array_key_exists($key, $this->_memo)) {
            $found = GeoService::geocode($text, $country);
            $this->_memo[$key] = $found ? ['lat' => (float)$found['lat'], 'lng' => (float)$found['lng']] : null;
        }

        return $this->_memo[$key];
    }

    /**
     * The address as one line of text to look up, or null when it has none.
     */
    public function text(Address $address): ?string
    {
        $text = implode(', ', array_filter([
            $address->addressLine1,
            $address->addressLine2,
            $address->locality,
            $address->administrativeArea,
            $address->postalCode,
        ], fn($part) => is_string($part) && trim($part) !== ''));

        return $text !== '' ? $text : null;
    }

    /**
     * Whether a saved address should start the background lookup.
     */
    public function shouldQueue(Address $address): bool
    {
        $plugin = Plugin::getInstance();
        if (!$plugin->isPro() || !$plugin->getSettings()->geocodeMissing || $this->scope() === null) {
            return false;
        }

        if (is_numeric($address->latitude) && is_numeric($address->longitude)) {
            return false;
        }

        if ($address->getIsDraft() || $address->getIsRevision() || $this->text($address) === null) {
            return false;
        }

        return $this->ownerQuery()->andWhere(['id' => $address->primaryOwnerId])->exists()
            || ($address->primaryOwnerId === null && $this->scope() === 'all');
    }

    /**
     * Addresses without coordinates, as items for MissingCoordinates::fill().
     *
     * @return Generator<array{type: class-string<Address>, id: int, siteId: int, handle: null, fieldId: int, address: string, country: ?string}>
     */
    public function find(callable $isKnownNotFound): Generator
    {
        if ($this->scope() === null) {
            return;
        }

        $query = Address::find()
            ->status(null)
            ->andWhere(['or', ['addresses.latitude' => null], ['addresses.latitude' => ''], ['addresses.longitude' => null], ['addresses.longitude' => '']]);

        $owners = ['addresses.primaryOwnerId' => $this->ownerQuery()];
        $query->andWhere($this->scope() === 'all' ? ['or', $owners, ['addresses.primaryOwnerId' => null]] : $owners);

        foreach ($query->each(50) as $address) {
            /** @var Address $address */
            $text = $this->text($address);
            $country = $address->countryCode ?: null;
            if ($text === null || $isKnownNotFound($text, $country)) {
                continue;
            }

            yield [
                'type' => Address::class,
                'id' => (int)$address->id,
                'siteId' => (int)$address->siteId,
                'handle' => null,
                'fieldId' => (int)$address->fieldId,
                'address' => $text,
                'country' => $country,
            ];
        }
    }

    /**
     * Saves found coordinates on an address, unless it was given some or changed since it was found.
     *
     * @param array{lat: float, lng: float} $point
     */
    public function save(int $id, string $text, array $point): bool
    {
        $address = Address::find()->id($id)->status(null)->one();
        if (!$address || (is_numeric($address->latitude) && is_numeric($address->longitude)) || $this->text($address) !== $text) {
            return false;
        }

        // Only Craft’s own columns: saving the address again would make a new revision of its owner.
        Db::update(Table::ADDRESSES, [
            'latitude' => (string)round($point['lat'], 6),
            'longitude' => (string)round($point['lng'], 6),
        ], ['id' => $id]);
        Craft::$app->getElements()->invalidateCachesForElement($address);

        return true;
    }

    /**
     * Which addresses get coordinates: `entries` (those in Addresses fields on entries), `all` (users’ and orders’
     * too), or null for none.
     */
    private function scope(): ?string
    {
        $setting = Plugin::getInstance()->getSettings()->geocodeAddresses;

        return match ($setting) {
            true, Settings::ADDRESSES_ALL => Settings::ADDRESSES_ALL,
            Settings::ADDRESSES_ENTRIES => Settings::ADDRESSES_ENTRIES,
            default => null,
        };
    }

    /**
     * Owners whose addresses get coordinates: live elements, and only entries unless every address does.
     */
    private function ownerQuery(): Query
    {
        $query = (new Query())
            ->select('id')
            ->from(Table::ELEMENTS)
            ->where(['draftId' => null, 'revisionId' => null, 'dateDeleted' => null]);

        if ($this->scope() !== Settings::ADDRESSES_ALL) {
            $query->andWhere(['type' => Entry::class]);
        }

        return $query;
    }
}
