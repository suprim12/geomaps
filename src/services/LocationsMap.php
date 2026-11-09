<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\Html;
use craft\models\Section;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\Plugin;
use yii\base\Component;

/**
 * The control panel map of entries (Pro): which sections can be shown, and their locations as map markers.
 */
class LocationsMap extends Component
{
    /** The most entries shown on one map. */
    public const LIMIT = 3000;

    /**
     * Sections with a Geo Maps field that the current user can view, with their Geo Maps fields.
     *
     * @return array<string, array{section: Section, fields: GeoField[]}> keyed by section handle
     */
    public function sources(): array
    {
        $user = Craft::$app->getUser()->getIdentity();
        $sources = [];

        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            if (!$user || !$user->can('viewEntries:' . $section->uid)) {
                continue;
            }
            $fields = [];
            foreach ($section->getEntryTypes() as $type) {
                foreach ($type->getFieldLayout()->getCustomFields() as $field) {
                    if ($field instanceof GeoField) {
                        $fields[$field->handle] = $field;
                    }
                }
            }
            if ($fields) {
                $sources[$section->handle] = ['section' => $section, 'fields' => array_values($fields)];
            }
        }

        return $sources;
    }

    /**
     * Map markers for a section's entries, with a popup linking to each entry.
     *
     * @return array{markers: array<int, array<string, mixed>>, total: int}
     */
    public function markers(Section $section, GeoField $field, bool $liveOnly = false, ?string $search = null): array
    {
        $query = Entry::find()
            ->sectionId($section->id)
            ->status($liveOnly ? 'live' : null)
            ->{$field->handle}(':notempty:');

        if ($search !== null && trim($search) !== '') {
            $query->search(trim($search));
        }

        $total = (int)(clone $query)->count();
        $markers = [];

        foreach ($query->limit(self::LIMIT)->all() as $entry) {
            $value = $entry->getFieldValue($field->handle);
            if (!$value instanceof GeoValue) {
                continue;
            }
            $point = $value->hasCoordinates()
                ? ['lat' => $value->lat, 'lng' => $value->lng]
                : (($shape = $value->getGeoJsonData()) ? GeoValue::geoJsonCentre($shape) : null);
            if (!$point) {
                continue;
            }

            $markers[] = [
                'location' => $point,
                'label' => (string)$entry,
                'html' => $this->popup($entry, $value),
            ];
        }

        return ['markers' => $markers, 'total' => $total];
    }

    /**
     * The map to use in the control panel: Google or the map tiles, from the map service setting.
     */
    public function provider(): string
    {
        return Plugin::getInstance()->getSettings()->getMapProvider('cp') === 'google' ? 'google' : 'tiles';
    }

    private function popup(Entry $entry, GeoValue $value): string
    {
        $status = $entry->getStatus();
        $editUrl = $entry->getCpEditUrl();

        return Html::tag('div',
            Html::tag('strong', $editUrl ? Html::a(Html::encode((string)$entry), $editUrl) : Html::encode((string)$entry))
            . ($value->full_address ? Html::tag('span', Html::encode($value->full_address), ['class' => 'gm-pop__address']) : '')
            . Html::tag('span', Html::tag('span', '', ['class' => "status $status"]) . Html::encode(ucfirst((string)$status)), ['class' => 'gm-pop__status'])
            . ($editUrl ? Html::a(Craft::t('app', 'Edit'), $editUrl, ['class' => 'btn small']) : ''),
            ['class' => 'gm-pop']
        );
    }
}
