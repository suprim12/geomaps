<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\jobs;

use Craft;
use craft\queue\BaseJob;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\MissingCoordinates;

/**
 * Fills in coordinates for Geo Maps values that only have an address (Pro).
 */
class GeocodeMissing extends BaseJob
{
    /** Only this field handle; all Geo Maps fields when null. */
    public ?string $field = null;

    public function execute($queue): void
    {
        $service = Plugin::getInstance()->missingCoordinates;

        try {
            if (!Plugin::getInstance()->isPro()) {
                return;
            }

            $items = iterator_to_array($service->find($this->field), false);
            $total = count($items);
            $saved = 0;

            foreach ($items as $i => $item) {
                $this->setProgress($queue, $i / max(1, $total), Craft::t('geomaps', '{step, number} of {total, number}', [
                    'step' => $i + 1,
                    'total' => $total,
                ]));

                $status = $service->fill($item);

                if ($status === MissingCoordinates::UNAVAILABLE) {
                    // OpenStreetMap paused us or is unreachable; the next save or command run picks up the rest.
                    Craft::warning("Geo Maps: stopped finding coordinates after $saved of $total because OpenStreetMap is unavailable.", __METHOD__);
                    break;
                }

                if ($status === MissingCoordinates::SAVED) {
                    $saved++;
                }
            }
        } finally {
            $service->clearQueued();
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('geomaps', 'Finding missing coordinates');
    }
}
