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
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Imports Maps (Ether) and Craft Geoman fields from the Geo Maps Import utility.
 */
class ImportController extends Controller
{
    /**
     * Imports one field (fieldId param) or, without one, every importable field.
     */
    public function actionIndex(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();
        $this->requireCpRequest();

        $service = Geo::getInstance()->import;
        $fieldId = $this->request->getBodyParam('fieldId');

        try {
            if ($fieldId) {
                $field = Craft::$app->getFields()->getFieldById((int)$fieldId);
                if (!$field || !$service->sourceOf($field)) {
                    throw new BadRequestHttpException('That field can’t be imported.');
                }
                $results = [$field->handle => $service->importField($field)];
            } else {
                $results = $service->importAll();
            }
        } catch (Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return $this->asFailure(Craft::t('geo', 'Import failed: {error}', ['error' => $e->getMessage()]));
        }

        return $this->asSuccess(Craft::t('geo', 'Imported {fields} field(s) and {values} location(s).', [
            'fields' => count($results),
            'values' => array_sum($results),
        ]));
    }

    /**
     * @deprecated Use actionIndex().
     */
    public function actionSimpleMap(): ?Response
    {
        return $this->actionIndex();
    }
}
