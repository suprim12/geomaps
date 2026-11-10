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
use craft\base\ElementInterface;
use craft\base\Model;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\web\View;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\Plugin;
use suprim\geomaps\web\LocatorAsset;
use Twig\Markup;

/**
 * The locator component: address search with suggestions, "use my location",
 * a distance filter, a numbered results list and a map, from one Twig tag.
 *
 * The search is a plain GET form, so results are server-rendered, linkable and work without
 * JavaScript; the script adds "use my location" and opens a result's pin when it's clicked.
 */
class Locator extends Model
{
    /** Handle of the Geo Maps field to search. Required. */
    public ?string $field = null;
    /** Section handle(s) to search, when no `query` is given. */
    public string|array|null $section = null;
    /** A prepared element query (e.g. `craft.entries.section('stores').relatedTo(…)`), instead of `section`. */
    public ?ElementQueryInterface $query = null;
    /** Default search distance. */
    public int $radius = 25;
    /** Distances visitors can choose from. */
    public array $radii = [5, 10, 25, 50, 100];
    /** `km` or `mi`. */
    public string $unit = 'km';
    /** Most results to list. */
    public int $limit = 20;
    /** Name prefix for the search inputs. */
    public string $name = 'near';
    /** Two-letter country codes to limit address suggestions to. Defaults to the field's countries. */
    public ?array $countries = null;
    public ?string $placeholder = null;
    /** Pin and accent colour (hex). */
    public string $color = '#4f46e5';
    /** Map height: pixels or a CSS length. */
    public int|string $mapHeight = 520;
    /** Group nearby pins on the map (Geo Maps Pro; ignored on Lite). */
    public bool $cluster = true;
    /** Directions link with `{lat}` and `{lng}` placeholders, or false (or null) to hide it. */
    public string|false|null $directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination={lat},{lng}';
    /** Site template for each result's content. Gets `entry`, `location`, `distance`, `unit`, `number`. */
    public ?string $resultTemplate = null;
    /** Site template for each pin's info window. Gets the same variables. */
    public ?string $popupTemplate = null;
    /** Override any interface text; see the docs for keys. */
    public array $messages = [];
    public ?string $id = null;
    public ?string $class = null;

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
        if (!$field instanceof GeoField) {
            Craft::warning("craft.geomaps.locator() needs the handle of a Geo Maps field in `field`.", __METHOD__);
            return new Markup('', 'UTF-8');
        }

        $unit = $this->unit === 'mi' ? 'mi' : 'km';
        $id = $this->id ?? 'geomaps-locator-' . StringHelper::randomString(6);
        $color = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $this->color) ? $this->color : '#4f46e5';
        $messages = $this->messages + [
            'search' => Craft::t('geomaps', 'Search'),
            'address' => Craft::t('geomaps', 'Address, suburb or postcode'),
            'distance' => Craft::t('geomaps', 'Distance'),
            'useMyLocation' => Craft::t('geomaps', 'Use my location'),
            'locating' => Craft::t('geomaps', 'Finding your location…'),
            'locationFailed' => Craft::t('geomaps', 'We couldn’t get your location. Type an address instead.'),
            'yourLocation' => Craft::t('geomaps', 'Your location'),
            'prompt' => Craft::t('geomaps', 'Enter an address to see the nearest locations.'),
            'results' => Craft::t('geomaps', '{count, plural, =1{1 location} other{# locations}} within {radius} {unit}, nearest first'),
            'none' => Craft::t('geomaps', 'No locations within {radius} {unit}. Try a larger distance.'),
            'directions' => Craft::t('geomaps', 'Directions'),
            'details' => Craft::t('geomaps', 'Details'),
        ];

        // The search, from the query string.
        $request = Craft::$app->getRequest();
        $params = $request->getIsConsoleRequest() ? [] : (array)$request->getQueryParam($this->name, []);
        $lat = self::coordinate($params['lat'] ?? null, 90);
        $lng = self::coordinate($params['lng'] ?? null, 180);
        $radius = (int)($params['radius'] ?? $this->radius);
        $radii = array_values(array_unique(array_map('intval', $this->radii ?: [$this->radius])));
        if (!in_array($radius, $radii, true)) {
            $radius = in_array($this->radius, $radii, true) ? $this->radius : $radii[0];
        }
        $searched = $lat !== null && $lng !== null;
        // Only plain text: `?near[full_address][]=x` mustn't break the page.
        $searchText = is_string($params['full_address'] ?? null) ? trim($params['full_address']) : '';

        $query = $this->query ? clone $this->query : Entry::find()->section($this->section ?? '*');
        $handle = $field->handle;
        if ($searched) {
            $query->$handle(['location' => ['lat' => $lat, 'lng' => $lng], 'radius' => $radius, 'unit' => $unit]);
        } else {
            $query->$handle(':notempty:');
        }
        $elements = $query->limit($this->limit)->all();

        $view = Craft::$app->getView();
        $results = [];
        $markers = [];

        foreach ($elements as $i => $element) {
            $location = $element->getFieldValue($handle);
            if (!$location instanceof GeoValue || !$location->hasCoordinates()) {
                continue;
            }

            $number = count($results) + 1;
            $vars = [
                'entry' => $element,
                'location' => $location,
                'distance' => $searched ? $location->distance : null,
                'unit' => $unit,
                'number' => $number,
            ];
            $directions = $this->directionsUrl
                ? strtr($this->directionsUrl, ['{lat}' => (string)$location->lat, '{lng}' => (string)$location->lng])
                : null;

            $results[] = $vars + [
                'address' => $this->addressText($location),
                'url' => $element->getUrl(),
                'directionsUrl' => $directions,
                'html' => $this->resultTemplate ? $view->renderTemplate($this->resultTemplate, $vars, View::TEMPLATE_MODE_SITE) : null,
            ];

            $markers[] = [
                'location' => ['lat' => $location->lat, 'lng' => $location->lng],
                'label' => (string)$element,
                'icon' => ['url' => self::pinUrl($number, $color), 'width' => 34, 'height' => 44],
                'html' => $this->popupTemplate
                    ? $view->renderTemplate($this->popupTemplate, $vars, View::TEMPLATE_MODE_SITE)
                    : $this->defaultPopup($element, $location, $vars['distance'], $unit, $directions, $messages),
            ];
        }

        if ($searched) {
            $markers[] = [
                'location' => ['lat' => $lat, 'lng' => $lng],
                'label' => $searchText ?: $messages['yourLocation'],
                'color' => '#111827',
                'cluster' => false,
            ];
        }

        $countries = $this->countries ?? $field->getSearchCountries();

        $map = (new EmbedMap())->embed([
            'id' => "$id-map",
            'center' => $searched ? ['lat' => $lat, 'lng' => $lng] : ['lat' => $field->lat, 'lng' => $field->lng],
            'zoom' => $searched ? 12 : (int)$field->zoom,
            'width' => '100%',
            'height' => '100%',
            'marker' => false,
            'markers' => $markers,
            'fitMarkers' => count($markers) > 1,
            // Clustering is Pro; checked here so free sites don't log a warning on every page.
            'cluster' => $this->cluster && Plugin::getInstance()->isPro() ? ['color' => $color] : false,
        ]);

        $search = (new AddressInput())->render([
            'name' => $this->name,
            'id' => "$id-address",
            'value' => $searched ? [
                'lat' => $lat,
                'lng' => $lng,
                'full_address' => $searchText ?: $messages['yourLocation'],
            ] : null,
            'placeholder' => $this->placeholder ?? $messages['address'],
            'countries' => $countries,
            'near' => ['lat' => $field->lat, 'lng' => $field->lng],
            'required' => true,
        ]);

        $view->registerAssetBundle(LocatorAsset::class);

        $placeholders = ['radius' => $radius, 'unit' => $unit, 'count' => count($results)];
        $summary = $searched && $results
            ? Craft::$app->getI18n()->format($messages['results'], $placeholders, Craft::$app->language)
            : null;
        $none = Craft::$app->getI18n()->format($messages['none'], $placeholders, Craft::$app->language);
        $height = is_numeric($this->mapHeight) ? max(200, (int)$this->mapHeight) . 'px'
            : (preg_match('/^\d+(\.\d+)?(px|r?em|vh|svh|dvh)$/', trim($this->mapHeight)) ? trim($this->mapHeight) : '520px');

        $html = $view->renderTemplate('geomaps/locator/_locator', [
            'id' => $id,
            'class' => $this->class,
            'name' => $this->name,
            'color' => $color,
            'search' => $search,
            'map' => $map,
            'results' => $results,
            'searched' => $searched,
            'radius' => $radius,
            'radii' => $radii,
            'unit' => $unit,
            'messages' => $messages,
            'summary' => $summary,
            'none' => $none,
            'height' => $height,
            'keep' => $request->getIsConsoleRequest() ? [] : self::hiddenParams($request->getQueryParams(), $this->name, self::pathInQuery($request)),
        ], View::TEMPLATE_MODE_CP);

        return new Markup($html, 'UTF-8');
    }

    private function addressText(GeoValue $location): string
    {
        return $location->full_address
            ?: implode(', ', array_filter([$location->address1, $location->suburb, $location->postcode]));
    }

    private function defaultPopup(ElementInterface $element, GeoValue $location, ?float $distance, string $unit, ?string $directions, array $messages): string
    {
        $html = Html::tag('strong', Html::encode((string)$element));
        $html .= Html::tag('span', Html::encode($this->addressText($location)), ['class' => 'geomaps-locator__popup-address']);

        if ($distance !== null) {
            $html .= Html::tag('span', Html::encode(Craft::$app->getFormatter()->asDecimal($distance, 1) . " $unit"), ['class' => 'geomaps-locator__popup-distance']);
        }

        $links = '';
        if ($directions) {
            $links .= Html::a(Html::encode($messages['directions']), $directions, ['target' => '_blank', 'rel' => 'noopener']);
        }
        if ($url = $element->getUrl()) {
            $links .= Html::a(Html::encode($messages['details']), $url);
        }
        if ($links) {
            $html .= Html::tag('span', $links, ['class' => 'geomaps-locator__popup-links']);
        }

        return Html::tag('div', $html, ['class' => 'geomaps-locator__popup']);
    }

    /**
     * A numbered map pin as an SVG data URI.
     */
    private static function pinUrl(int $number, string $color): string
    {
        $size = $number > 99 ? 11 : 14;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="34" height="44" viewBox="0 0 34 44">'
            . '<path d="M17 43s15-14.7 15-26.3A15 15 0 0 0 2 16.7C2 28.3 17 43 17 43z" fill="' . $color . '" stroke="#fff" stroke-width="2"/>'
            . '<text x="17" y="21.5" text-anchor="middle" font-family="system-ui,-apple-system,sans-serif" font-size="' . $size . '" font-weight="700" fill="#fff">' . $number . '</text>'
            . '</svg>';

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }

    /**
     * The page's other query string parameters, such as filters, as `name => value` pairs for hidden
     * inputs, so searching again keeps them.
     *
     * @return array<string, string>
     */
    private static function hiddenParams(array $params, string $exclude, bool $keepPath, string $prefix = ''): array
    {
        $pathParam = Craft::$app->getConfig()->getGeneral()->pathParam;
        $pairs = [];
        foreach ($params as $key => $value) {
            $name = $prefix === '' ? (string)$key : "{$prefix}[{$key}]";
            if ($prefix === '' && ($name === $exclude || ($name === $pathParam && !$keepPath))) {
                continue;
            }
            if (is_array($value)) {
                $pairs += self::hiddenParams($value, $exclude, $keepPath, $name);
            } elseif (is_scalar($value)) {
                $pairs[$name] = (string)$value;
            }
        }

        return $pairs;
    }

    /**
     * Whether the page's path is in its query string (`index.php?p=stores`, without pretty URLs), so the
     * search form has to send it. With pretty URLs, the server adds it and the form mustn't.
     */
    private static function pathInQuery(mixed $request): bool
    {
        if (!$request instanceof \craft\web\Request) {
            return false;
        }

        $pathParam = Craft::$app->getConfig()->getGeneral()->pathParam;
        $query = (string)parse_url($request->getUrl(), PHP_URL_QUERY);
        parse_str($query, $params);

        return $pathParam !== null && array_key_exists($pathParam, $params);
    }

    private static function coordinate(mixed $value, int $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (float)$value;

        return abs($value) <= $max ? $value : null;
    }
}
