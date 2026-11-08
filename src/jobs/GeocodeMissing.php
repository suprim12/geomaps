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
use craft\helpers\Queue as QueueHelper;
use craft\queue\BaseJob;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\MissingCoordinates;

/**
 * Fills in coordinates for Geo Maps values that only have an address (Pro).
 */
class GeocodeMissing extends BaseJob
{
    /** Addresses looked up per job. Nominatim allows one a second, so a batch stays well within the TTR. */
    public const BATCH = 200;
    /** Seconds a job may run. */
    public const TTR = 900;

    /** Only this field handle; all Geo Maps fields when null. */
    public ?string $field = null;

    public function execute($queue): void
    {
        $service = Plugin::getInstance()->missingCoordinates;
        $more = false;

        try {
            if (!Plugin::getInstance()->isPro()) {
                return;
            }

            $done = 0;
            foreach ($service->find($this->field) as $item) {
                if ($done >= self::BATCH) {
                    // Hand the rest to a new job rather than running past the time limit.
                    $more = true;
                    break;
                }

                $service->keepQueued();
                $this->setProgress($queue, $done / self::BATCH, Craft::t('geomaps', '{count, number} looked up', ['count' => $done]));

                if ($service->fill($item) === MissingCoordinates::UNAVAILABLE) {
                    // OpenStreetMap or Mapbox paused us or is unreachable; the next save or command run picks up the rest.
                    Craft::warning("Geo Maps: stopped finding coordinates after $done because the geocoding service is unavailable.", __METHOD__);
                    break;
                }

                $done++;
            }
        } finally {
            if ($more) {
                QueueHelper::push(new self(['field' => $this->field]), null, null, self::TTR);
            } else {
                $service->clearQueued();
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('geomaps', 'Finding missing coordinates');
    }
}
