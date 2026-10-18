<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\jobs;

use Craft;
use craft\queue\BaseJob;
use sup\craftgeo\fields\GeoField;
use sup\craftgeo\Geo;

/**
 * Rebuilds the location search index, for one field or all of them.
 */
class RebuildLookup extends BaseJob
{
    public ?int $fieldId = null;

    public function execute($queue): void
    {
        $field = $this->fieldId ? Craft::$app->getFields()->getFieldById($this->fieldId) : null;

        if ($this->fieldId && !$field instanceof GeoField) {
            return;
        }

        Geo::getInstance()->maps->rebuildLookup($field);
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('geo', 'Indexing locations');
    }
}
