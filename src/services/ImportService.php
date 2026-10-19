<?php
/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprimgolay.com.np/craft-plugins
 * @author suprim golay <suprimtech@gmail.com> <https://suprimgolay.com.np>
 * @copyright Copyright (c) 2026 Suprim Golay
 */

namespace sup\craftgeo\services;

use Craft;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\db\Table;
use craft\fields\MissingField;
use craft\helpers\Db;
use craft\helpers\Json;
use sup\craftgeo\fields\GeoField;
use sup\craftgeo\Geo;
use sup\craftgeo\models\GeoValue;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**
 * Imports fields and content from Maps by Ether (ether/simplemap).
 */
class ImportService extends Component
{
    public const SIMPLEMAP_FIELD_TYPE = 'ether\\simplemap\\fields\\MapField';
    public const SIMPLEMAP_TABLE = '{{%maps}}';

    /**
     * Returns the Maps fields that can be imported, with how many values each holds.
     *
     * @return array<int, array{field: FieldInterface, values: int}>
     */
    public function getSimpleMapFields(): array
    {
        $ids = (new Query())
            ->select(['id'])
            ->from(Table::FIELDS)
            ->where(['type' => self::SIMPLEMAP_FIELD_TYPE])
            ->column();

        $fields = [];

        foreach ($ids as $id) {
            $field = Craft::$app->getFields()->getFieldById((int)$id);

            if ($field) {
                $fields[] = [
                    'field' => $field,
                    'values' => $this->countValues($field),
                ];
            }
        }

        return $fields;
    }

    /**
     * Imports every Maps field. Returns the number of values converted per field handle.
     *
     * @return array<string, int>
     */
    public function importAll(): array
    {
        $results = [];

        foreach ($this->getSimpleMapFields() as $item) {
            $results[$item['field']->handle] = $this->importField($item['field']);
        }

        return $results;
    }

    /**
     * Converts a Maps field to a Geo Maps field in place (same handle, layouts and content),
     * then rewrites its stored values and indexes them for location search.
     *
     * Returns the number of values converted.
     */
    public function importField(FieldInterface $old): int
    {
        if (!$this->isSimpleMapField($old)) {
            throw new Exception("Field “{$old->handle}” isn’t a Maps field.");
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $converted = $this->convertContent($old);

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
                'settings' => $this->convertSettings($this->oldSettings($old)),
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
        Geo::getInstance()->maps->rebuildLookup($new);
        Craft::$app->getElements()->invalidateAllCaches();

        Craft::info("Imported Maps field “{$old->handle}” ({$converted} values).", __METHOD__);

        return $converted;
    }

    public function isSimpleMapField(FieldInterface $field): bool
    {
        if ($field instanceof MissingField) {
            return $field->expectedType === self::SIMPLEMAP_FIELD_TYPE;
        }

        return get_class($field) === self::SIMPLEMAP_FIELD_TYPE;
    }

    private function countValues(FieldInterface $field): int
    {
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

    private function convertSettings(array $s): array
    {
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
    private function convertContent(FieldInterface $field): int
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
        if (Craft::$app->getDb()->tableExists(self::SIMPLEMAP_TABLE)) {
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
