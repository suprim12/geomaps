<?php


/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace suprim\geomaps\integrations\feedme\fields;

use Craft;
use craft\feedme\base\Field;
use craft\feedme\base\FieldInterface;
use craft\feedme\helpers\DataHelper;
use craft\helpers\Json;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\services\GeoService;

class GeoFeedMeField extends Field implements FieldInterface
{
    public static $name = 'Maps';
    public static $class = GeoField::class;


    public function getMappingTemplate(): string
    {
        return 'geomaps/feedme-field';
    }

//     // Define the sub-fields Feed Me will map to
    public function getMappingFields(): array
    {
        return [
            'lat' => Craft::t('geomaps', 'Latitude'),
            'lng' => Craft::t('geomaps', 'Longitude'),
            'zoom' => Craft::t('geomaps', 'Zoom'),
            'full_address' => Craft::t('geomaps', 'Full Address'),
            'address1' => Craft::t('geomaps', 'Address Line 1'),
            'suburb' => Craft::t('geomaps', 'Suburb'),
            'postcode' => Craft::t('geomaps', 'Postcode'),
            'country' => Craft::t('geomaps', 'Country'),
            'geoJson' => Craft::t('geomaps', 'GeoJSON'),
        ];
    }

    public function parseField(): mixed
    {
        $preppedData = [];

        // Sub-fields are mapped as fields[<key>] = { node, default } by feedme-field.twig.
        foreach ($this->fieldInfo['fields'] ?? [] as $key => $subFieldInfo) {
            if (!array_key_exists($key, $this->getMappingFields()) || !is_array($subFieldInfo)) {
                continue;
            }

            $value = DataHelper::fetchValue($this->feedData, $subFieldInfo, $this->feed);

            if ($value === null || $value === '') {
                continue;
            }

            $preppedData[$key] = match ($key) {
                'lat', 'lng' => (float) $value,
                'zoom' => (int)   $value,
                default => $this->parseStringValue($key, $value),
            };
        }

        // A two-letter country is a code; anything longer is a name.
        $country = $preppedData['country'] ?? null;
        if (is_string($country) && strlen($country) === 2) {
            $preppedData['countryCode'] = strtoupper($country);
            unset($preppedData['country']);
        } elseif (is_string($country)) {
            $preppedData['countryCode'] = GeoService::countryCodeFromName($country);
        }

        return empty($preppedData) ? null : Json::encode(array_filter($preppedData, fn($v) => $v !== null));
    }

    private function parseStringValue(string $key, mixed $value): mixed
    {
        // Validate and normalise GeoJSON
        if ($key === 'geoJson') {
            $decoded = Json::decodeIfJson($value);
            return is_array($decoded) ? Json::encode($decoded) : null;
        }

        return trim((string) $value);
    }
}
