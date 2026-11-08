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
use craft\base\Field;
use craft\helpers\Queue as QueueHelper;
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
    public const SKIPPED = 'skipped';

    private const NOT_FOUND_CACHE = 'geomaps:geocode-not-found:';
    private const QUEUED_KEY = 'geomaps:geocode-queued';

    /**
     * Values with an address but no coordinates.
     *
     * Only elements whose field layout has a Geo Maps field are loaded, and each element only once
     * when the field isn't translated. Items carry ids rather than elements, so `fill()` works on a
     * fresh copy.
     *
     * @return Generator<array{type: class-string<ElementInterface>, id: int, siteId: int, handle: string, fieldId: int, address: string, country: ?string}>
     */
    public function find(?string $fieldHandle = null, bool $retry = false): Generator
    {
        $fieldsService = Craft::$app->getFields();

        foreach ($fieldsService->getAllFields() as $field) {
            if (!$field instanceof GeoField || ($fieldHandle && $field->handle !== $fieldHandle)) {
                continue;
            }

            // Layout ids per element type, so other sections and entry types aren't loaded.
            $layoutIds = [];
            foreach ($fieldsService->findFieldUsages($field) as $layout) {
                if ($layout->id && $layout->type && class_exists($layout->type)) {
                    $layoutIds[$layout->type][] = $layout->id;
                }
            }

            foreach ($layoutIds as $type => $ids) {
                /** @var class-string<ElementInterface> $type */
                $query = $type::find()
                    ->site('*')
                    ->status(null)
                    ->andWhere(['elements.fieldLayoutId' => $ids]);

                // An untranslated value is the same on every site: look it up once.
                if ($field->translationMethod === Field::TRANSLATION_METHOD_NONE) {
                    $query->unique();
                }

                foreach ($query->each(50) as $element) {
                    foreach ($element->getFieldLayout()?->getCustomFields() ?? [] as $layoutField) {
                        if ($layoutField->id !== $field->id) {
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
                            'type' => $type,
                            'id' => (int)$element->id,
                            'siteId' => (int)$element->siteId,
                            'handle' => $layoutField->handle,
                            'fieldId' => (int)$field->id,
                            'address' => $address,
                            'country' => $country,
                        ];
                    }
                }
            }
        }
    }

    /**
     * Looks up one value's address and saves its coordinates on a freshly loaded copy of the element,
     * so changes made since the scan (titles, a pin placed by hand) aren't overwritten.
     *
     * @param array{type: class-string<ElementInterface>, id: int, siteId: int, handle: string, fieldId: int, address: string, country: ?string} $item
     * @return string One of the class constants.
     */
    public function fill(array $item): string
    {
        $element = $item['type']::find()->id($item['id'])->siteId($item['siteId'])->status(null)->one();
        $value = $element?->getFieldValue($item['handle']);

        // Deleted, given coordinates, or its address edited since the scan: nothing to do.
        if (!$value instanceof GeoValue || $value->hasCoordinates() || $this->addressText($value) !== $item['address']) {
            return self::SKIPPED;
        }

        $result = GeoService::geocodeForSaving($item['address'], $item['country']);

        // The field's search country is only a hint; the address may be elsewhere.
        if ($result === null && $item['country'] && !$value->countryCode) {
            $result = GeoService::geocodeForSaving($item['address']);
        }

        if ($result === false) {
            return self::UNAVAILABLE;
        }

        if ($result === null) {
            Craft::$app->getCache()->set($this->notFoundKey($item['address'], $item['country']), true, GeoService::CACHE_DURATION);
            return self::NOT_FOUND;
        }

        $value->lat = round($result['lat'], 6);
        $value->lng = round($result['lng'], 6);
        $element->setFieldValue($item['handle'], $value);
        // A background update, like `resave/entries`: no new entry revision for each location.
        $element->resaving = true;

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

        $this->keepQueued();
        QueueHelper::push(new GeocodeMissing(), null, null, GeocodeMissing::TTR);
    }

    /**
     * Marks a job as waiting or running, so saves don't queue another one. Refreshed as a job runs.
     */
    public function keepQueued(): void
    {
        Craft::$app->getCache()->set(self::QUEUED_KEY, true, 3600);
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
