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
use craft\web\Controller;
use suprim\geomaps\Plugin;
use yii\web\Response;

/**
 * The Map page in the control panel (Pro): every entry with a location on one map.
 */
class MapController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireCpRequest();
        $plugin = Plugin::getInstance();
        $request = Craft::$app->getRequest();

        if (!$plugin->isPro()) {
            return $this->renderTemplate('geomaps/map/_index', ['isPro' => false]);
        }

        $service = $plugin->locationsMap;
        $sources = $service->sources();

        // The chosen section and field, or the first ones the user can see.
        $sectionHandle = $request->getQueryParam('section');
        $source = $sources[$sectionHandle] ?? (reset($sources) ?: null);
        $field = null;
        if ($source) {
            $fieldHandle = $request->getQueryParam('field');
            foreach ($source['fields'] as $f) {
                if ($f->handle === $fieldHandle) {
                    $field = $f;
                }
            }
            $field ??= $source['fields'][0];
        }

        $liveOnly = $request->getQueryParam('status') === 'live';
        $search = $request->getQueryParam('search');
        $result = $source
            ? $service->markers($source['section'], $field, $liveOnly, is_string($search) ? $search : null)
            : ['markers' => [], 'total' => 0];

        return $this->renderTemplate('geomaps/map/_index', [
            'isPro' => true,
            'sources' => $sources,
            'source' => $source,
            'field' => $field,
            'liveOnly' => $liveOnly,
            'search' => is_string($search) ? $search : '',
            'markers' => $result['markers'],
            'total' => $result['total'],
            'limit' => $service::LIMIT,
            'provider' => $service->provider(),
        ]);
    }
}
