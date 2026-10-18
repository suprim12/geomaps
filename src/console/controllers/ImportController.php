<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use sup\craftgeo\Geo;
use yii\console\ExitCode;

/**
 * Imports fields and content from other map plugins.
 */
class ImportController extends Controller
{
    /**
     * Converts every Maps (ether/simplemap) field to a Geo Maps field, keeping handles, layouts and content.
     */
    public function actionSimpleMap(): int
    {
        $service = Geo::getInstance()->import;
        $fields = $service->getSimpleMapFields();

        if (empty($fields)) {
            $this->stdout("No Maps fields found.\n");
            return ExitCode::OK;
        }

        foreach ($fields as $item) {
            $field = $item['field'];
            $this->stdout("Importing {$field->handle} … ");
            $count = $service->importField($field);
            $this->stdout("$count location(s)\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
