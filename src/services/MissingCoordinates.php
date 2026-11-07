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
use craft\base\Component;
use craft\base\ElementInterface;
use Generator;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\jobs\GeocodeMissing;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\Plugin;

/**
 * Finds Geo Maps values that have an address but no coordinates, and fills them in (Pro).
 *
 * Addresses are looked up with OpenStreetMap at most once a second. Addresses that can't be
 * found are remembered for 30 days so imports don't look them up again on every save.
 */
class MissingCoordinates extends Component
{
    public const SAVED = 'saved';
    public const NOT_FOUND = 'notFound';
    public const UNAVAILABLE = 'unavailable';

    private const NOT_FOUND_CACHE = 'geomaps:geocode-not-found:';
    private const QUEUED_KEY = 'geomaps:geocode-queued';

    /**
     * Values with an address but no coordinates.
     *
     * @return Generator<array{element: ElementInterface, handle: string, field: GeoField, address: string, country: ?string}>
     */
    public function find(?string $fieldHandle = null, bool $retry = false): Generator
    {
        $fieldsService = Craft::$app->getFields();
        $geoFields = [];
        /** @var array<class-string<ElementInterface>, true> $types */
        $types = [];

        foreach ($fieldsService->getAllFields() as $field) {
            if (!$field instanceof GeoField || ($fieldHandle && $field->handle !== $fieldHandle)) {
                continue;
            }

            $geoFields[$field->id] = $field;
            foreach ($fieldsService->findFieldUsages($field) as $layout) {
                if ($layout->type && class_exists($layout->type)) {
                    $types[$layout->type] = true;
                }
            }
        }

        foreach (array_keys($types) as $type) {
            foreach ($type::find()->site('*')->status(null)->each(50) as $element) {
                // Only the Geo Maps fields in this element's own layout (handles can differ per layout).
                foreach ($element->getFieldLayout()?->getCustomFields() ?? [] as $layoutField) {
                    $field = $geoFields[$layoutField->id] ?? null;
                    if (!$field) {
                        continue;
                    }

                    $value = $element->getFieldValue($layoutField->handle);
                    $address = $value instanceof GeoValue ? $this->addressText($value) : null;

                    if ($address === null || $value->hasCoordinates()) {
                        continue;
                    }

                    $country = $this->countryFor($value, $field);

                    if (!$retry && $this->isKnownNotFound($address, $country)) {
                        continue;
                    }

                    yield [
                        'element' => $element,
                        'handle' => $layoutField->handle,
                        'field' => $field,
                        'address' => $address,
                        'country' => $country,
                    ];
                }
            }
        }
    }

    /**
     * Looks up one value's address and saves its coordinates.
     *
     * @param array{element: ElementInterface, handle: string, field: GeoField, address: string, country: ?string} $item
     * @return string One of the class constants.
     */
    public function fill(array $item): string
    {
        $result = GeoService::geocodeForSaving($item['address'], $item['country']);

        // The field's search country is only a hint; the address may be elsewhere.
        $value = $item['element']->getFieldValue($item['handle']);
        if ($result === null && $item['country'] && $value instanceof GeoValue && !$value->countryCode) {
            $result = GeoService::geocodeForSaving($item['address']);
        }

        if ($result === false) {
            return self::UNAVAILABLE;
        }

        if ($result === null) {
            Craft::$app->getCache()->set($this->notFoundKey($item['address'], $item['country']), true, GeoService::CACHE_DURATION);
            return self::NOT_FOUND;
        }

        $element = $item['element'];
        if (!$value instanceof GeoValue) {
            return self::NOT_FOUND;
        }

        $value->lat = round($result['lat'], 6);
        $value->lng = round($result['lng'], 6);
        $element->setFieldValue($item['handle'], $value);

        return Craft::$app->getElements()->saveElement($element, false) ? self::SAVED : self::NOT_FOUND;
    }

    /**
     * Queues a background job to fill missing coordinates, unless one is already waiting.
     */
    public function queue(): void
    {
        $cache = Craft::$app->getCache();
        if ($cache->get(self::QUEUED_KEY)) {
            return;
        }

        $cache->set(self::QUEUED_KEY, true, 3600);
        Craft::$app->getQueue()->push(new GeocodeMissing());
    }

    /**
     * Lets the next save queue a job again.
     */
    public function clearQueued(): void
    {
        Craft::$app->getCache()->delete(self::QUEUED_KEY);
    }

    /**
     * Whether a saved value should trigger the background job.
     */
    public function shouldQueue(?GeoValue $value, ?GeoField $field = null): bool
    {
        if (!$value || $value->hasCoordinates()) {
            return false;
        }

        $plugin = Plugin::getInstance();
        if (!$plugin->isPro() || !$plugin->getSettings()->geocodeMissing) {
            return false;
        }

        $address = $this->addressText($value);
        if ($address === null) {
            return false;
        }

        return !$this->isKnownNotFound($address, $this->countryFor($value, $field));
    }

    /**
     * The text to look up: the full address, or the address parts joined together.
     */
    public function addressText(GeoValue $value): ?string
    {
        $text = trim((string)$value->full_address);

        if ($text === '') {
            $text = implode(', ', array_filter(array_map(
                fn($part) => trim((string)$part),
                [$value->address1, $value->suburb, $value->postcode, $value->country],
            )));
        }

        return $text !== '' ? $text : null;
    }

    /**
     * The value's own country, or the field's only search country, to narrow the lookup.
     */
    private function countryFor(GeoValue $value, ?GeoField $field): ?string
    {
        $countries = $field?->getSearchCountries() ?? [];

        return $value->countryCode ?: (count($countries) === 1 ? $countries[0] : null);
    }

    private function isKnownNotFound(string $address, ?string $country): bool
    {
        return (bool)Craft::$app->getCache()->get($this->notFoundKey($address, $country));
    }

    private function notFoundKey(string $address, ?string $country): string
    {
        return self::NOT_FOUND_CACHE . md5(mb_strtolower($address) . '|' . $country);
    }
}
