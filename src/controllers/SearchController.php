<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\controllers;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\web\Controller;
use DateTimeInterface;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\GeoService;
use suprim\geomaps\services\MapService;
use yii\web\Response;

/**
 * JSON search endpoints for headless front ends (Pro), set up in `config/geomaps.php` under `searchEndpoints`.
 *
 * GET /actions/geomaps/search?endpoint=stores&location=Adelaide&radius=25
 *
 * Visitors only choose the place, distance, area and page. Which elements and values are returned is set
 * in the config file, so an endpoint can't be used to read anything else.
 */
class SearchController extends Controller
{
    /** Results per page when the endpoint doesn't say. */
    private const DEFAULT_LIMIT = 20;
    /** The most results one request can ask for. */
    private const MAX_LIMIT = 100;

    protected array|bool|int $allowAnonymous = true;

    public function beforeAction($action): bool
    {
        // Read-only GET requests: no CSRF token is needed.
        $this->enableCsrfValidation = false;

        return parent::beforeAction($action);
    }

    public function actionIndex(?string $endpoint = null): Response
    {
        $config = $endpoint !== null ? (Plugin::getInstance()->getSettings()->searchEndpoints[$endpoint] ?? null) : null;
        $response = Craft::$app->getResponse();

        if (is_array($config)) {
            $this->applyCors($config);
        }

        $request = Craft::$app->getRequest();
        if ($request->getIsOptions()) {
            $response->setStatusCode(204);
            return $response;
        }

        if (!Plugin::getInstance()->isPro()) {
            return $this->error(403, 'Search endpoints need Geo Maps Pro.');
        }

        if (!is_array($config) || empty($config['field']) || !is_string($config['field'])) {
            return $this->error(404, 'There’s no search endpoint with that name.');
        }

        $handle = $config['field'];
        $unit = GeoService::normalizeUnit($request->getQueryParam('unit', $config['unit'] ?? 'km'));
        $limit = (int)$request->getQueryParam('limit', $config['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $offset = max(0, (int)$request->getQueryParam('offset', 0));
        $maxRadius = (float)($config['maxRadius'] ?? 500);
        $radius = $request->getQueryParam('radius');
        $radius = is_numeric($radius) ? max(0.0, min($maxRadius, (float)$radius)) : null;

        // Where to search from: coordinates, or an address looked up on the server.
        $origin = null;
        $lat = $request->getQueryParam('lat');
        $lng = $request->getQueryParam('lng');
        $location = $request->getQueryParam('location');
        if (is_numeric($lat) && is_numeric($lng) && abs((float)$lat) <= 90 && abs((float)$lng) <= 180) {
            $origin = ['lat' => (float)$lat, 'lng' => (float)$lng];
        } elseif (is_string($location) && trim($location) !== '') {
            if (($config['allowAddress'] ?? true) === false) {
                return $this->error(400, 'This endpoint needs lat and lng rather than an address.');
            }
            $country = $request->getQueryParam('country', $config['country'] ?? null);
            $origin = GeoService::resolveLocation(mb_substr(trim($location), 0, 200), is_string($country) ? $country : null);
            if (!$origin) {
                return $this->asJson(['results' => [], 'total' => 0, 'origin' => null, 'message' => 'The address wasn’t found.']);
            }
            $origin = ['lat' => (float)$origin['lat'], 'lng' => (float)$origin['lng']];
        }

        $within = $request->getQueryParam('within');
        if ($within !== null && (!is_string($within) || !MapService::parseBounds($within))) {
            return $this->error(400, 'within must be south,west,north,east.');
        }

        $param = [];
        if ($within !== null) {
            $param['within'] = $within;
        }
        if ($origin) {
            $param['location'] = $origin;
            $param['unit'] = $unit;
            // Without an area, a location searches within a radius (the endpoint's, or 50).
            if ($radius !== null || $within === null) {
                $param['radius'] = $radius ?? min($maxRadius, (float)($config['radius'] ?? 50));
            }
        }

        $query = $this->baseQuery($config);
        $query->$handle($param ?: ':notempty:');

        $total = (int)(clone $query)->count();
        $results = [];
        foreach ($query->limit($limit)->offset($offset)->all() as $element) {
            $results[] = $this->result($element, $handle, $config, $unit);
        }

        return $this->asJson([
            'results' => $results,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'unit' => $unit,
            'origin' => $origin,
        ]);
    }

    /**
     * The endpoint's element query: its element type and criteria, on the current site.
     */
    private function baseQuery(array $config): ElementQueryInterface
    {
        /** @var class-string<ElementInterface> $type */
        $type = is_string($config['elementType'] ?? null) && is_subclass_of($config['elementType'], ElementInterface::class)
            ? $config['elementType']
            : Entry::class;

        $query = $type::find();
        Craft::configure($query, is_array($config['criteria'] ?? null) ? $config['criteria'] : []);

        return $query;
    }

    private function result(ElementInterface $element, string $handle, array $config, string $unit): array
    {
        $value = $element->getFieldValue($handle);
        $location = $value instanceof GeoValue ? $value : null;

        if (isset($config['transform']) && is_callable($config['transform'])) {
            return (array)($config['transform'])($element, $location);
        }

        $result = [
            'id' => $element->id,
            'title' => (string)$element,
            'url' => $element->getUrl(),
            'distance' => $location?->distance,
            'unit' => $location?->distance !== null ? $unit : null,
            'location' => $location ? [
                'lat' => $location->lat,
                'lng' => $location->lng,
                'address' => $location->getAddress(),
                'address1' => $location->address1,
                'suburb' => $location->suburb,
                'postcode' => $location->postcode,
                'country' => $location->country,
                'countryCode' => $location->countryCode,
            ] : null,
        ];

        foreach ((array)($config['fields'] ?? []) as $name) {
            if (is_string($name) && !array_key_exists($name, $result)) {
                $result[$name] = $this->plainValue($this->read($element, $name));
            }
        }

        return $result;
    }

    private function read(ElementInterface $element, string $name): mixed
    {
        // Attributes and custom fields; anything the element doesn't have is left out as null.
        return $element->canGetProperty($name) ? $element->$name : null;
    }

    /**
     * A value as JSON: dates as ISO 8601, related elements as `{ id, title, url }`, objects as text.
     */
    private function plainValue(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof ElementQueryInterface => array_map(
                fn(ElementInterface $e) => ['id' => $e->id, 'title' => (string)$e, 'url' => $e->getUrl()],
                $value->all()
            ),
            $value instanceof ElementInterface => ['id' => $value->id, 'title' => (string)$value, 'url' => $value->getUrl()],
            $value instanceof \JsonSerializable => $value->jsonSerialize(),
            is_array($value) => array_map(fn($v) => $this->plainValue($v), $value),
            $value instanceof \Stringable => (string)$value,
            default => null,
        };
    }

    /**
     * Lets the endpoint's `cors` origins call it from a browser.
     */
    private function applyCors(array $config): void
    {
        $allowed = (array)($config['cors'] ?? []);
        $origin = Craft::$app->getRequest()->getOrigin();

        if (!$allowed || !$origin) {
            return;
        }

        $headers = Craft::$app->getResponse()->getHeaders();
        if (in_array('*', $allowed, true)) {
            $headers->set('Access-Control-Allow-Origin', '*');
        } elseif (in_array($origin, $allowed, true)) {
            $headers->set('Access-Control-Allow-Origin', $origin);
            $headers->add('Vary', 'Origin');
        } else {
            return;
        }
        $headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $headers->set('Access-Control-Allow-Headers', 'Content-Type');
        $headers->set('Access-Control-Max-Age', '600');
    }

    private function error(int $status, string $message): Response
    {
        $response = $this->asJson(['error' => $message]);
        $response->setStatusCode($status);

        return $response;
    }
}
