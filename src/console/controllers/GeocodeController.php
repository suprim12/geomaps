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
use suprim\geomaps\services\MissingCoordinates;
use yii\console\ExitCode;

/**
 * Finds coordinates for locations that only have an address (Geo Maps Pro).
 */
class GeocodeController extends Controller
{
    /** @var string|null Only this Geo Maps field (handle). */
    public ?string $field = null;

    /** @var int|null Stop after this many lookups. */
    public ?int $limit = null;

    /** @var bool List what would be looked up, without looking anything up. */
    public bool $dryRun = false;

    /** @var bool Also retry addresses that couldn't be found before. */
    public bool $retry = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['field', 'limit', 'dryRun', 'retry']);
    }

    /**
     * Finds coordinates for locations that have an address but no latitude and longitude.
     */
    public function actionIndex(): int
    {
        $plugin = Plugin::getInstance();

        if (!$plugin->isPro()) {
            $this->stderr("Finding missing coordinates needs Geo Maps Pro. Switch the edition under Settings → Plugins (free on local and staging sites).\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $service = $plugin->missingCoordinates;
        $counts = [MissingCoordinates::SAVED => 0, MissingCoordinates::NOT_FOUND => 0, MissingCoordinates::SKIPPED => 0];
        $done = 0;

        foreach ($service->find($this->field, $this->retry) as $item) {
            if ($this->limit !== null && $done >= $this->limit) {
                break;
            }
            $done++;

            $label = sprintf('%s #%d (site %d): %s', $item['type']::displayName(), $item['id'], $item['siteId'], $item['address']);

            if ($this->dryRun) {
                $this->stdout("Would look up $label\n");
                continue;
            }

            $this->stdout("Looking up $label … ");
            $status = $service->fill($item);

            if ($status === MissingCoordinates::UNAVAILABLE) {
                $this->stdout("unavailable\n", Console::FG_YELLOW);
                $this->stderr("OpenStreetMap is unavailable or has asked us to slow down. Try again in 10 minutes.\n", Console::FG_YELLOW);
                break;
            }

            $counts[$status]++;
            $this->stdout(match ($status) {
                MissingCoordinates::SAVED => "saved\n",
                MissingCoordinates::SKIPPED => "skipped (changed since the scan)\n",
                default => "not found\n",
            }, $status === MissingCoordinates::SAVED ? Console::FG_GREEN : Console::FG_YELLOW);
        }

        if ($this->dryRun) {
            $this->stdout("$done location(s) would be looked up.\n", Console::FG_GREEN);
        } else {
            $this->stdout("Saved {$counts[MissingCoordinates::SAVED]}, not found {$counts[MissingCoordinates::NOT_FOUND]}.\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
