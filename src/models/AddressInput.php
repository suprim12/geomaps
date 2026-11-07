<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\models;

use Craft;
use craft\base\Model;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\GeoService;
use suprim\geomaps\web\AddressAsset;
use Twig\Markup;

/**
 * An address search box with suggestions for front-end forms.
 *
 * Picking a suggestion fills hidden inputs named like the control panel field
 * (`full_address`, `lat`, `lng`, `address1`, …), so the form can save straight into
 * a Geo Maps field (`fields[handle]`) or send coordinates to a search page.
 */
class AddressInput extends Model
{
    /** Handle of the Geo Maps field to save into; sets `name` and the default countries. */
    public ?string $field = null;
    /** Input name prefix, e.g. `fields[storeLocation]` or `near`. */
    public ?string $name = null;
    /** Starting value: a field value (`entry.storeLocation`) or an array of the same keys. */
    public mixed   $value = null;
    public ?string $id = null;
    public ?string $placeholder = null;
    /** Accessible label, for forms without a visible `<label for>`. */
    public ?string $label = null;
    public bool    $required = false;
    /** Two-letter country codes to limit suggestions to. Defaults to the field's countries. */
    public ?array  $countries = null;
    /** Suggest places near here first: `{ lat, lng }`. Defaults to the field's initial location. */
    public mixed   $near = null;
    public int     $minChars = 3;
    /** Extra classes for the wrapper and the text input. */
    public ?string $class = null;
    public ?string $inputClass = null;
    /** Text shown when the form is sent without choosing a suggestion. */
    public ?string $errorMessage = null;
    /** Override screen-reader and error text: `pick`, `none`, `results` (`{count}` is replaced). */
    public array   $messages = [];

    private const VALUE_KEYS = ['lat', 'lng', 'address1', 'suburb', 'postcode', 'country', 'countryCode'];

    public function __toString(): string
    {
        return (string)$this->render();
    }

    public function render(array $options = []): Markup
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $field = $this->field ? Craft::$app->getFields()->getFieldByHandle($this->field) : null;
        $field = $field instanceof GeoField ? $field : null;

        $name = $this->name ?? ($this->field ? "fields[{$this->field}]" : 'location');
        $id = $this->id ?? 'geomaps-address-' . StringHelper::randomString(6);
        $values = $this->startingValues();
        $countries = $this->countries ?? ($field?->getSearchCountries() ?? []);
        $countries = array_values(array_filter(
            array_map(fn($code) => strtoupper(trim((string)$code)), $countries),
            fn($code) => GeoService::isCountryCode($code),
        ));
        $near = $this->resolveNear($field);
        $messages = $this->messages + [
            'pick' => $this->errorMessage ?? Craft::t('geomaps', 'Choose an address from the list.'),
        ];

        Craft::$app->getView()->registerAssetBundle(AddressAsset::class);

        // Mapbox suggestions: always for search boxes, but for boxes that save into a field only with
        // permanent geocoding, because Mapbox only allows storing results from permanent requests.
        $settings = Plugin::getInstance()->getSettings();
        $useMapbox = $settings->getGeocoder() === 'mapbox' && ($this->field === null || $settings->mapboxPermanent);

        $hidden = '';
        foreach (self::VALUE_KEYS as $key) {
            $hidden .= Html::hiddenInput("{$name}[{$key}]", $values[$key] ?? '', [
                'data-geomaps-value' => $key,
            ]);
        }

        $input = Html::input('text', "{$name}[full_address]", $values['full_address'] ?? '', [
            'id' => $id,
            'class' => trim('geomaps-address__input ' . ($this->inputClass ?? '')),
            'placeholder' => $this->placeholder,
            'aria-label' => $this->label,
            'required' => $this->required,
            'autocomplete' => 'off',
            'role' => 'combobox',
            'aria-autocomplete' => 'list',
            'aria-expanded' => 'false',
            'aria-controls' => "{$id}-list",
            'aria-describedby' => "{$id}-error",
            'data-geomaps-query' => true,
        ]);

        $list = Html::tag('ul', '', [
            'id' => "{$id}-list",
            'class' => 'geomaps-address__list',
            'role' => 'listbox',
            'hidden' => true,
            'data-geomaps-list' => true,
        ]);
        $status = Html::tag('div', '', [
            'class' => 'geomaps-address__status',
            'role' => 'status',
            'aria-live' => 'polite',
            'data-geomaps-status' => true,
        ]);
        $error = Html::tag('p', Html::encode($messages['pick']), [
            'id' => "{$id}-error",
            'class' => 'geomaps-address__error',
            'hidden' => true,
            'data-geomaps-error' => true,
        ]);

        $html = Html::tag('div', $input . $list . $status . $error . $hidden, [
            'class' => trim('geomaps-address' . (!empty($values['lat']) ? ' geomaps-address--chosen' : '') . ' ' . ($this->class ?? '')),
            'data-geomaps-address' => true,
            'data-countries' => implode(',', $countries),
            'data-min-chars' => max(1, $this->minChars),
            'data-lang' => Craft::$app->language,
            'data-lat' => $near['lat'] ?? null,
            'data-lng' => $near['lng'] ?? null,
            'data-msg-pick' => $messages['pick'],
            'data-msg-none' => $messages['none'] ?? null,
            'data-msg-results' => $messages['results'] ?? null,
            'data-provider' => $useMapbox ? 'mapbox' : null,
            'data-mapbox-token' => $useMapbox ? $settings->getMapboxToken() : null,
            'data-permanent' => $useMapbox && $settings->mapboxPermanent ? '1' : null,
        ]);

        return new Markup($html, 'UTF-8');
    }

    /**
     * @return array<string, string>
     */
    private function startingValues(): array
    {
        $value = $this->value;

        if (is_array($value)) {
            $value = GeoValue::fromArray($value);
        }

        if (!$value instanceof GeoValue || !$value->hasCoordinates()) {
            return [];
        }

        $data = $value->toArray();
        $values = ['full_address' => (string)($data['full_address'] ?? '')];
        foreach (self::VALUE_KEYS as $key) {
            $values[$key] = isset($data[$key]) ? (string)$data[$key] : '';
        }

        return $values;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function resolveNear(?GeoField $field): ?array
    {
        $near = $this->near;

        if ($near instanceof GeoValue) {
            $near = ['lat' => $near->lat, 'lng' => $near->lng];
        }

        if (is_array($near) && is_numeric($near['lat'] ?? null) && is_numeric($near['lng'] ?? null)) {
            return ['lat' => round((float)$near['lat'], 4), 'lng' => round((float)$near['lng'], 4)];
        }

        if ($field) {
            return ['lat' => round($field->lat, 4), 'lng' => round($field->lng, 4)];
        }

        return null;
    }
}
