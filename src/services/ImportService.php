<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace suprim\geomaps\services;

use Craft;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\db\Table;
use craft\fields\MissingField;
use craft\helpers\Db;
use craft\helpers\Json;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\Plugin;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**
 * Imports fields and content from Maps by Ether (ether/simplemap) and Craft Geoman (handplant/craft-geoman).
 */
class ImportService extends Component
{
    public const SIMPLEMAP_FIELD_TYPE = 'ether\\simplemap\\fields\\MapField';
    public const SIMPLEMAP_TABLE = '{{%maps}}';
    public const GEOMAN_FIELD_TYPE = 'handplant\\craftgeoman\\fields\\Geoman';

    public const SOURCE_SIMPLEMAP = 'simplemap';
    public const SOURCE_GEOMAN = 'geoman';

    /** Field types that can be imported, keyed by source. */
    public const SOURCES = [
        self::SOURCE_SIMPLEMAP => self::SIMPLEMAP_FIELD_TYPE,
        self::SOURCE_GEOMAN => self::GEOMAN_FIELD_TYPE,
    ];

    /**
     * Returns the fields that can be imported, with their source plugin and how many values each holds.
     *
     * @return array<int, array{field: FieldInterface, source: string, sourceName: string, values: int}>
     */
    public function getImportableFields(): array
    {
        $ids = (new Query())
            ->select(['id'])
            ->from(Table::FIELDS)
            ->where(['type' => array_values(self::SOURCES)])
            ->orderBy(['name' => SORT_ASC])
            ->column();

        $fields = [];

        foreach ($ids as $id) {
            $field = Craft::$app->getFields()->getFieldById((int)$id);
            $source = $field ? $this->sourceOf($field) : null;

            if ($field && $source) {
                $fields[] = [
                    'field' => $field,
                    'source' => $source,
                    'sourceName' => self::sourceName($source),
                    'values' => $this->countValues($field, $source),
                ];
            }
        }

        return $fields;
    }

    public static function sourceName(string $source): string
    {
        return match ($source) {
            self::SOURCE_SIMPLEMAP => 'Maps (Ether)',
            self::SOURCE_GEOMAN => 'Craft Geoman',
            default => $source,
        };
    }

    /**
     * Imports every importable field. Returns the number of values converted per field handle.
     *
     * @return array<string, int>
     */
    public function importAll(): array
    {
        $results = [];

        foreach ($this->getImportableFields() as $item) {
            $results[$item['field']->handle] = $this->importField($item['field']);
        }

        return $results;
    }

    /**
     * Which plugin a field comes from (`simplemap`, `geoman`), or null if it can't be imported.
     */
    public function sourceOf(FieldInterface $field): ?string
    {
        $type = $field instanceof MissingField ? $field->expectedType : get_class($field);
        foreach (self::SOURCES as $source => $sourceType) {
            if ($type === $sourceType) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Converts a Maps field to a Geo Maps field in place (same handle, layouts and content),
     * then rewrites its stored values and indexes them for location search.
     *
     * Returns the number of values converted.
     */
    public function importField(FieldInterface $old): int
    {
        $source = $this->sourceOf($old);

        if (!$source) {
            throw new Exception("Field “{$old->handle}” can’t be imported.");
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $converted = $this->convertContent($old, $source);

            $new = Craft::$app->getFields()->createField([
                'type' => GeoField::class,
                'id' => $old->id,
                'uid' => $old->uid,
                'name' => $old->name,
                'handle' => $old->handle,
                'context' => $old->context,
                'instructions' => $old->instructions,
                'searchable' => $old->searchable,
                'translationMethod' => $old->translationMethod,
                'translationKeyFormat' => $old->translationKeyFormat,
                'settings' => $this->convertSettings($this->oldSettings($old), $source),
            ]);

            if (!Craft::$app->getFields()->saveField($new)) {
                throw new Exception("Couldn’t save field “{$old->handle}”: " . implode(', ', $new->getFirstErrors()));
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        /** @var GeoField $new */
        Plugin::getInstance()->maps->rebuildLookup($new);
        Craft::$app->getElements()->invalidateAllCaches();

        Craft::info(sprintf('Imported %s field “%s” (%d values).', self::sourceName($source), $old->handle, $converted), __METHOD__);

        return $converted;
    }

    public function isSimpleMapField(FieldInterface $field): bool
    {
        return $this->sourceOf($field) === self::SOURCE_SIMPLEMAP;
    }

    private function countValues(FieldInterface $field, string $source): int
    {
        if ($source === self::SOURCE_GEOMAN) {
            // Geoman only stores values in the content column.
            $count = 0;
            foreach ($this->contentRows($field) as [$row, $key, $content]) {
                $count += empty($content[$key]) ? 0 : 1;
            }
            return $count;
        }

        if (Craft::$app->getDb()->tableExists(self::SIMPLEMAP_TABLE)) {
            return (int)(new Query())
                ->from(self::SIMPLEMAP_TABLE)
                ->where(['fieldId' => $field->id])
                ->andWhere(['not', ['lat' => null]])
                ->count();
        }

        return 0;
    }

    private function oldSettings(FieldInterface $field): array
    {
        if ($field instanceof MissingField) {
            return is_array($field->settings) ? $field->settings : (Json::decodeIfJson((string)$field->settings) ?: []);
        }

        return $field->getSettings();
    }

    private function convertSettings(array $s, string $source): array
    {
        if ($source === self::SOURCE_GEOMAN) {
            // Geoman fields only hold shapes, so no location pin.
            $settings = ['enableGeoJson' => true, 'showMarker' => false];
            foreach (['geomanMapLatitude' => 'lat', 'geomanMapLongitude' => 'lng'] as $from => $to) {
                if (isset($s[$from]) && is_numeric($s[$from])) {
                    $settings[$to] = (float)$s[$from];
                }
            }
            if (isset($s['geomanMapZoom']) && is_numeric($s['geomanMapZoom'])) {
                $settings['zoom'] = (int)$s['geomanMapZoom'];
            }
            if (isset($s['geomanMapHeight']) && is_numeric($s['geomanMapHeight'])) {
                $settings['mapHeigth'] = max(240, min(1200, (int)$s['geomanMapHeight']));
            }
            return $settings;
        }

        $settings = [];

        foreach (['lat' => 'float', 'lng' => 'float', 'zoom' => 'int', 'minZoom' => 'float', 'maxZoom' => 'float'] as $key => $type) {
            if (isset($s[$key]) && is_numeric($s[$key])) {
                $settings[$key] = $type === 'int' ? (int)$s[$key] : (float)$s[$key];
            }
        }

        foreach (['hideSearch', 'hideMap', 'hideAddress', 'showLatLng'] as $key) {
            if (isset($s[$key])) {
                $settings[$key] = (bool)$s[$key];
            }
        }

        $country = $s['country'] ?? null;
        if (is_string($country) && $country !== '' && GeoService::isCountryCode($country)) {
            $settings['countries'] = [strtoupper($country)];
        }

        return $settings;
    }

    /**
     * Rewrites every stored value of the field (including drafts and revisions) in the Geo Maps format.
     * Values missing from the content column are recovered from the Maps lookup table.
     */
    /**
     * Yields `[row, contentKey, decodedContent]` for every element (including drafts and revisions)
     * whose field layout contains the field.
     *
     * @return \Generator<int, array{0: array, 1: string, 2: array}>
     */
    private function contentRows(FieldInterface $field): \Generator
    {
        $keyByLayout = $this->contentKeysByLayout($field);

        if (empty($keyByLayout)) {
            return;
        }

        $query = (new Query())
            ->select(['es.id', 'es.elementId', 'es.siteId', 'es.content', 'e.fieldLayoutId'])
            ->from(['es' => Table::ELEMENTS_SITES])
            ->innerJoin(['e' => Table::ELEMENTS], '[[e.id]] = [[es.elementId]]')
            ->where(['e.fieldLayoutId' => array_keys($keyByLayout)]);

        foreach ($query->each(500) as $row) {
            $content = Json::decodeIfJson((string)$row['content']);
            yield [$row, $keyByLayout[$row['fieldLayoutId']], is_array($content) ? $content : []];
        }
    }

    /**
     * Layout element UID (the content JSON key) for each field layout that uses the field.
     *
     * @return array<int, string>
     */
    private function contentKeysByLayout(FieldInterface $field): array
    {
        $keyByLayout = [];
        foreach (Craft::$app->getFields()->getAllLayouts() as $layout) {
            foreach ($layout->getCustomFieldElements() as $layoutElement) {
                if ($layoutElement->fieldUid === $field->uid && $layout->id) {
                    $keyByLayout[$layout->id] = $layoutElement->uid;
                }
            }
        }

        return $keyByLayout;
    }

    private function convertContent(FieldInterface $field, string $source): int
    {
        // Layout element UID (content key) for each field layout using this field.
        $keyByLayout = [];
        foreach (Craft::$app->getFields()->getAllLayouts() as $layout) {
            foreach ($layout->getCustomFieldElements() as $layoutElement) {
                if ($layoutElement->fieldUid === $field->uid && $layout->id) {
                    $keyByLayout[$layout->id] = $layoutElement->uid;
                }
            }
        }

        if (empty($keyByLayout)) {
            return 0;
        }

        $legacy = [];
        if ($source === self::SOURCE_SIMPLEMAP && Craft::$app->getDb()->tableExists(self::SIMPLEMAP_TABLE)) {
            // Older Maps versions only stored lat/lng in this table.
            $columns = Craft::$app->getDb()->getTableSchema(self::SIMPLEMAP_TABLE)->getColumnNames();
            $rows = (new Query())
                ->select(array_values(array_intersect(['ownerId', 'ownerSiteId', 'lat', 'lng', 'zoom', 'address', 'parts'], $columns)))
                ->from(self::SIMPLEMAP_TABLE)
                ->where(['fieldId' => $field->id]);

            foreach ($rows->each() as $row) {
                $legacy[$row['ownerId'] . '-' . $row['ownerSiteId']] = $row;
            }
        }

        $query = (new Query())
            ->select(['es.id', 'es.elementId', 'es.siteId', 'es.content', 'e.fieldLayoutId'])
            ->from(['es' => Table::ELEMENTS_SITES])
            ->innerJoin(['e' => Table::ELEMENTS], '[[e.id]] = [[es.elementId]]')
            ->where(['e.fieldLayoutId' => array_keys($keyByLayout)]);

        $converted = 0;

        foreach ($query->each(500) as $row) {
            $key = $keyByLayout[$row['fieldLayoutId']];
            $content = Json::decodeIfJson((string)$row['content']);
            $content = is_array($content) ? $content : [];

            $raw = $content[$key] ?? null;
            if (is_string($raw)) {
                $raw = Json::decodeIfJson($raw);
            }

            $legacyRow = $legacy[$row['elementId'] . '-' . $row['siteId']] ?? null;
            if ((!is_array($raw) || !isset($raw['lat'])) && $legacyRow) {
                $raw = $legacyRow;
            }

            $value = GeoValue::fromArray($raw);
            $new = $value?->toArray();

            if (($content[$key] ?? null) === $new) {
                continue;
            }

            if ($new === null) {
                unset($content[$key]);
            } else {
                $content[$key] = $new;
                $converted++;
            }

            Db::update(Table::ELEMENTS_SITES, [
                'content' => $content ?: null,
            ], ['id' => $row['id']], updateTimestamp: false);
        }

        return $converted;
    }
}
