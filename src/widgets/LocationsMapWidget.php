<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\widgets;

use Craft;
use craft\base\Widget;
use suprim\geomaps\Plugin;

/**
 * A Dashboard widget showing a section's entries on a map (Pro).
 */
class LocationsMapWidget extends Widget
{
    /** The section handle. Empty for the first section with a Geo Maps field. */
    public ?string $section = null;

    /** The Geo Maps field handle. Empty for the section's first. */
    public ?string $field = null;

    /** Map height in pixels. */
    public int $height = 360;

    public static function displayName(): string
    {
        return Craft::t('geomaps', 'Locations Map');
    }

    public static function icon(): ?string
    {
        return 'map-location-dot';
    }

    /**
     * Only offered when Geo Maps Pro is active.
     */
    public static function isSelectable(): bool
    {
        return Plugin::getInstance()->isPro() && parent::isSelectable();
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['section', 'field'], 'string'],
            ['height', 'integer', 'min' => 200, 'max' => 1200],
        ]);
    }

    public function getTitle(): ?string
    {
        $source = $this->source();

        return $source ? $source['section']->name : static::displayName();
    }

    public function getSettingsHtml(): ?string
    {
        $sources = Plugin::getInstance()->locationsMap->sources();

        return Craft::$app->getView()->renderTemplate('geomaps/widgets/_settings', [
            'widget' => $this,
            'sources' => $sources,
        ]);
    }

    public function getBodyHtml(): ?string
    {
        $plugin = Plugin::getInstance();
        if (!$plugin->isPro()) {
            return Craft::t('geomaps', 'The map of entries is part of Geo Maps Pro.');
        }

        $source = $this->source();
        $field = null;
        if ($source) {
            foreach ($source['fields'] as $f) {
                if ($f->handle === $this->field) {
                    $field = $f;
                }
            }
            $field ??= $source['fields'][0];
        }
        $result = $source ? $plugin->locationsMap->markers($source['section'], $field) : ['markers' => [], 'total' => 0];

        return Craft::$app->getView()->renderTemplate('geomaps/widgets/_body', [
            'widget' => $this,
            'source' => $source,
            'field' => $field,
            'markers' => $result['markers'],
            'total' => $result['total'],
            'provider' => $plugin->locationsMap->provider(),
        ]);
    }

    /**
     * @return array{section: \craft\models\Section, fields: \suprim\geomaps\fields\GeoField[]}|null
     */
    private function source(): ?array
    {
        $sources = Plugin::getInstance()->locationsMap->sources();

        return $sources[$this->section] ?? (reset($sources) ?: null);
    }
}
