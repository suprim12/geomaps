<?php


/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */


namespace sup\craftgeo\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\elements\db\ElementQuery;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use GraphQL\Type\Definition\Type;
use sup\craftgeo\Geo;
use sup\craftgeo\integrations\graphql\types\GeoQueryArgument;
use sup\craftgeo\integrations\graphql\types\GeoType;
use sup\craftgeo\models\GeoValue;
use sup\craftgeo\services\GeoService;
use sup\craftgeo\web\GeoAsset;
use yii\db\ExpressionInterface;
use yii\db\Schema;

class GeoField extends Field implements PreviewableFieldInterface
{
    public float $lat = -34.925;
    public float $lng = 138.60;
    public int $zoom = 9;
    public float $minZoom = 3;
    public float $maxZoom = 18;
    /** Map height in the control panel, in pixels. (Property name kept for backwards compatibility.) */
    public int $mapHeigth = 420;
    public ?string $country = null;
    public ?string $countryCode = null;
    public ?string $full_address = null;
    public ?string $address1 = null;
    public ?string $suburb = null;
    public ?string $postcode = null;
    public ?string $geoJson = null;
    public bool $hideSearch = false;
    public bool $hideAddress = false;
    public bool $hideMap = false;
    public bool $showLatLng = false;


    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        // Only live content is searchable; drafts and revisions keep their values in the content column.
        if (!$element->getIsDraft() && !$element->getIsRevision()) {
            $value = $element->getFieldValue($this->handle);
            Geo::getInstance()->maps->saveLookup($this, $element, $value instanceof GeoValue ? $value : null);
        }

        parent::afterElementSave($element, $isNew);
    }

    public static function queryCondition(array $instances, mixed $value, array &$params): array|string|ExpressionInterface|false|null
    {
        if (empty($instances) || $value === null || $value === '' || $value === []) {
            return null;
        }

        return Geo::getInstance()->maps->queryCondition(ElementQuery::$activeQuery, $instances, $value);
    }

    public function getElementValidationRules(): array
    {
        return ['validateGeoValue'];
    }

    /**
     * Validates coordinates and GeoJSON on the element.
     */
    public function validateGeoValue(ElementInterface $element): void
    {
        $value = $element->getFieldValue($this->handle);

        if (!$value instanceof GeoValue) {
            return;
        }

        foreach ($value->getInputErrors() as $error) {
            $element->addError("field:$this->handle", Craft::t('geo', $error));
        }

        if ($value->geoJson === null) {
            return;
        }

        $decoded = json_decode($value->geoJson, true);

        if (!is_array($decoded)) {
            $element->addError("field:$this->handle", Craft::t('geo', 'GeoJSON is not valid JSON.'));
            return;
        }

        $error = $this->validateGeoJson($decoded);

        if ($error !== null) {
            $element->addError("field:$this->handle", Craft::t('geo', $error));
        }
    }

    private function validateGeoJson(array $decoded): ?string
    {
        if (empty($decoded['type'])) {
            return 'GeoJSON must have a "type" property.';
        }

        // Must be a valid GeoJSON type
        $validTypes = [
            'Point',
            'MultiPoint',
            'LineString',
            'MultiLineString',
            'Polygon',
            'MultiPolygon',
            'GeometryCollection',
            'Feature',
            'FeatureCollection',
        ];

        if (!in_array($decoded['type'], $validTypes, true)) {
            return sprintf('GeoJSON type "%s" is not valid. Must be one of: %s', $decoded['type'], implode(', ', $validTypes));
        }

        return match ($decoded['type']) {
            'Point' => $this->validateGeoJsonPoint($decoded),
            'MultiPoint',
            'LineString' => $this->validateGeoJsonCoordinateArray($decoded, 2),
            'MultiLineString',
            'Polygon' => $this->validateGeoJsonCoordinateArray($decoded, 3),
            'MultiPolygon' => $this->validateGeoJsonCoordinateArray($decoded, 4),
            'GeometryCollection' => $this->validateGeoJsonGeometryCollection($decoded),
            'Feature' => $this->validateGeoJsonFeature($decoded),
            'FeatureCollection' => $this->validateGeoJsonFeatureCollection($decoded),
        };
    }

    private function validateGeoJsonPoint(array $data): ?string
    {
        if (!isset($data['coordinates']) || !is_array($data['coordinates'])) {
            return 'GeoJSON Point must have a "coordinates" array.';
        }

        if (count($data['coordinates']) < 2) {
            return 'GeoJSON Point coordinates must have at least [longitude, latitude].';
        }

        [$lng, $lat] = $data['coordinates'];

        if (!is_numeric($lng) || $lng < -180 || $lng > 180) {
            return 'GeoJSON Point longitude must be between -180 and 180.';
        }

        if (!is_numeric($lat) || $lat < -90 || $lat > 90) {
            return 'GeoJSON Point latitude must be between -90 and 90.';
        }

        return null;
    }

    private function validateGeoJsonCoordinateArray(array $data, int $depth): ?string
    {
        if (!isset($data['coordinates']) || !is_array($data['coordinates'])) {
            return 'GeoJSON ' . $data['type'] . ' must have a "coordinates" array.';
        }

        if (empty($data['coordinates'])) {
            return 'GeoJSON ' . $data['type'] . ' coordinates must not be empty.';
        }

        // Recursively check we reach numeric pairs at the correct depth
        $error = $this->validateCoordinateDepth($data['coordinates'], $depth, $data['type']);

        return $error;
    }

    private function validateCoordinateDepth(array $coords, int $depth, string $type): ?string
    {
        if ($depth === 1) {
            // Should be a [lng, lat] pair
            if (count($coords) < 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
                return "GeoJSON {$type} contains an invalid coordinate pair.";
            }
            return null;
        }

        foreach ($coords as $item) {
            if (!is_array($item)) {
                return "GeoJSON {$type} has malformed coordinate nesting.";
            }
            $error = $this->validateCoordinateDepth($item, $depth - 1, $type);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private function validateGeoJsonGeometryCollection(array $data): ?string
    {
        if (!isset($data['geometries']) || !is_array($data['geometries'])) {
            return 'GeoJSON GeometryCollection must have a "geometries" array.';
        }

        foreach ($data['geometries'] as $geometry) {
            if (!isset($geometry['type'])) {
                return 'Each geometry in a GeometryCollection must have a "type".';
            }
        }

        return null;
    }

    private function validateGeoJsonFeature(array $data): ?string
    {
        if (!array_key_exists('geometry', $data)) {
            return 'GeoJSON Feature must have a "geometry" property.';
        }


        // geometry can be null (valid per spec) or an object with a type
        if ($data['geometry'] !== null && empty($data['geometry']['type'])) {
            return 'GeoJSON Feature geometry must have a "type".';
        }

        return null;
    }

    private function validateGeoJsonFeatureCollection(array $data): ?string
    {
        if (!isset($data['features']) || !is_array($data['features'])) {
            return 'GeoJSON FeatureCollection must have a "features" array.';
        }

        foreach ($data['features'] as $i => $feature) {
            if (!isset($feature['type']) || $feature['type'] !== 'Feature') {
                return "GeoJSON FeatureCollection item #{$i} must be a Feature type.";
            }

            $error = $this->validateGeoJsonFeature($feature);
            if ($error !== null) {
                return "GeoJSON FeatureCollection item #{$i}: {$error}";
            }
        }

        return null;
    }

    public static function displayName(): string
    {
        return Craft::t('geo', 'Geo Maps');
    }

    public static function icon(): string
    {
        return 'location-dot';
    }

    public static function phpType(): string
    {
        return sprintf('\\%s|null', GeoValue::class);
    }

    public static function dbType(): array|string|null
    {
        return Schema::TYPE_JSON;
    }


    public function getContentGqlType(): array|Type
    {
        return GeoType::getType();
    }

    public function getContentGqlMutationArgumentType(): array|\GraphQL\Type\Definition\Type
    {
        return [
            'name' => $this->handle,
            'type' => Type::string(),
            'description' => 'JSON string or object with lat, lng, zoom, address fields',
        ];
    }

    public function getContentGqlQueryArgumentType(): array|\GraphQL\Type\Definition\Type
    {
        return [
            'name' => $this->handle,
            'type' => GeoQueryArgument::getType(),
        ];
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [
                'lat',
                'double',
                'min' => -90,
                'max' => 90,
            ],
            [
                'lng',
                'double',
                'min' => -180,
                'max' => 180,
            ],
            [
                'mapHeigth',
                'integer',
                'min' => 240,
                'max' => 1200,
            ],
            [
                'zoom',
                'integer',
                'min' => 0,
                'max' => 22,
            ],
        ]);
    }

    public function renderMapField(bool $isInput = false): string
    {
        $view = Craft::$app->getView();
        $handle = $this->handle ?? 'geo-preview';
        $namespacedId = $view->namespaceInputId($view->formatInputId($handle));

        $this->registerAssets();

        return $view->renderTemplate('geo/components/map', [
            'name' => $handle,
            'namespacedId' => $namespacedId,
            'mapdata' => [
                'lat' => $this->lat,
                'lng' => $this->lng,
                'zoom' => $this->zoom,
                'isInput' => $isInput,
                'provider' => Geo::getInstance()->getSettings()->getMapProvider(),
                'tileUrl' => Geo::getInstance()->getSettings()->getTileUrl(),
                'tileAttribution' => Geo::getInstance()->getSettings()->tileAttribution,
            ],
        ]);
    }

    private function registerAssets(): void
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(GeoAsset::class);

        $mapToken = Geo::getInstance()->getSettings()->getMapToken();

        if ($mapToken) {
            $view->registerJsFile(
                'https://maps.googleapis.com/maps/api/js?' . http_build_query([
                    'key' => $mapToken,
                    'loading' => 'async',
                    'libraries' => 'places',
                ])
            );
        }
    }

    public function beforeSave(bool $isNew): bool
    {
        $this->lat = (float) $this->lat;
        $this->lng = (float) $this->lng;
        $this->zoom = (int) $this->zoom;

        if ($this->country === '*') {
            $this->country = null;
        }

        return parent::beforeSave($isNew);
    }

    public function getSettingsHtml(): ?string
    {
        $mapField = $this->renderMapField();
        $countries = ['*' => Craft::t('geo', 'All Countries')] + GeoService::countryOptions();
        $handle = $this->handle ?? 'geo-preview';
        $id = Craft::$app->getView()->formatInputId($handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('geo/fields/GeoSettings', [
            'field' => $this,
            'map' => $mapField,
            'id' => $id,
            'namespacedId' => $namespacedId,
            'countries' => $countries,
        ]);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if ($value instanceof GeoValue) {
            return $value;
        }

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        return GeoValue::fromArray($value);
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if (!$value instanceof GeoValue || ($value->isEmpty() && empty($value->getInputErrors()))) {
            return null;
        }

        return $value->toArray();
    }

    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        return !$value instanceof GeoValue || ($value->isEmpty() && empty($value->getInputErrors()));
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $view = Craft::$app->getView();
        $id = $view->formatInputId($this->handle);
        $settings = Geo::getInstance()->getSettings();

        $this->registerAssets();

        return $view->renderTemplate('geo/_input', [
            'name' => $this->handle,
            'namespacedId' => $view->namespaceInputId($id),
            'value' => $value instanceof GeoValue ? $value->toArray() : null,
            'id' => $id,
            'elementId' => $element?->id,
            'mapdata' => [
                'centerLat' => $this->lat,
                'centerLng' => $this->lng,
                'zoom' => $this->zoom,
                'provider' => $settings->getMapProvider(),
                'tileUrl' => $settings->getTileUrl(),
                'tileAttribution' => $settings->tileAttribution,
                'restrictCountry' => $this->country,
                'language' => Craft::$app->language,
            ],
            'global_settings' => $settings,
            'settings' => $this->getSettings(),
            'settingsUrl' => UrlHelper::cpUrl('settings/plugins/geo'),
        ]);
    }

    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        if (!$value instanceof GeoValue) {
            return '';
        }

        return implode(' ', array_filter([
            $value->full_address,
            $value->address1,
            $value->suburb,
            $value->postcode,
            $value->country,
        ]));
    }

    public function getElementConditionRuleType(): array|string|null
    {
        return null;
    }
}
