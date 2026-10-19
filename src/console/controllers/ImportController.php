<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace suprim\geomaps\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use suprim\geomaps\Plugin;
use yii\console\ExitCode;

/**
 * Imports fields and content from Maps (Ether) and Craft Geoman.
 */
class ImportController extends Controller
{
    /**
     * @var string|null Handle of a single field to import. Imports every importable field when omitted.
     */
    public ?string $field = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['field']);
    }

    /**
     * Converts Maps (Ether) and Craft Geoman fields to Geo Maps fields, keeping handles, layouts and content.
     */
    public function actionIndex(): int
    {
        $service = Plugin::getInstance()->import;
        $items = $service->getImportableFields();

        if ($this->field !== null) {
            $items = array_values(array_filter($items, fn($item) => $item['field']->handle === $this->field));
            if (empty($items)) {
                $this->stderr("No importable field with the handle “{$this->field}”.\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }
        }

        if (empty($items)) {
            $this->stdout("No Maps (Ether) or Craft Geoman fields found.\n");
            return ExitCode::OK;
        }

        foreach ($items as $item) {
            $this->stdout("Importing {$item['field']->handle} ({$item['sourceName']}) … ");
            $count = $service->importField($item['field']);
            $this->stdout("$count location(s)\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * Same as geomaps/import (kept for compatibility).
     */
    public function actionSimpleMap(): int
    {
        return $this->actionIndex();
    }
}
