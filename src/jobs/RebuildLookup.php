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
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\Plugin;

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

        Plugin::getInstance()->maps->rebuildLookup($field);
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('geomaps', 'Indexing locations');
    }
}
