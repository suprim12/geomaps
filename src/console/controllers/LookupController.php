<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use suprim\geomaps\Plugin;
use yii\console\ExitCode;

/**
 * Manages the Geo Maps location search index.
 */
class LookupController extends Controller
{
    /**
     * Rebuilds the location search index from saved field values.
     */
    public function actionRebuild(): int
    {
        $count = Plugin::getInstance()->maps->rebuildLookup();
        $this->stdout("Indexed $count location(s).\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
