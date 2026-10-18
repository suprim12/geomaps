<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\controllers;

use Craft;
use craft\web\Controller;
use sup\craftgeo\Geo;
use Throwable;
use yii\web\Response;

/**
 * Imports Maps by Ether fields from the Geo Maps Import utility.
 */
class ImportController extends Controller
{
    public function actionSimpleMap(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();
        $this->requireCpRequest();

        try {
            $results = Geo::getInstance()->import->importAll();
        } catch (Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return $this->asFailure(Craft::t('geo', 'Import failed: {error}', ['error' => $e->getMessage()]));
        }

        return $this->asSuccess(Craft::t('geo', 'Imported {fields} field(s) and {values} location(s).', [
            'fields' => count($results),
            'values' => array_sum($results),
        ]));
    }
}
