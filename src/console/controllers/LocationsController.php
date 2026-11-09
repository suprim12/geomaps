<?php

/**
 * Geo Maps plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\geomaps\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use craft\models\EntryType;
use craft\models\Section;
use DateTimeInterface;
use suprim\geomaps\fields\GeoField;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\Plugin;
use suprim\geomaps\services\LocationFiles;
use Throwable;
use yii\console\ExitCode;

/**
 * Imports and exports locations as CSV, GeoJSON, KML and GPX (Geo Maps Pro).
 *
 *   php craft geomaps/locations/import stores.csv --section=stores --field=storeLocation
 *   php craft geomaps/locations/export --section=stores --field=storeLocation --output=stores.geojson
 */
class LocationsController extends Controller
{
    /** @var string|null The section handle. */
    public ?string $section = null;

    /** @var string|null The Geo Maps field handle. */
    public ?string $field = null;

    /** @var string|null Import: the entry type handle for new entries (default: the section's first). */
    public ?string $type = null;

    /** @var string Import: how to find existing entries to update: slug, title or id. */
    public string $match = 'slug';

    /** @var bool Import: only update existing entries, never create new ones. */
    public bool $updateOnly = false;

    /** @var bool Import: show what would happen, without saving anything. */
    public bool $dryRun = false;

    /** @var string|null Export: csv, geojson or kml (default: from --output, otherwise csv). */
    public ?string $format = null;

    /** @var string|null Export: the file to write (default: print it). */
    public ?string $output = null;

    /** @var string|null Export: other attributes and fields to include, comma separated. */
    public ?string $fields = null;

    /** @var string|null The site handle (default: the primary site). */
    public ?string $site = null;

    public function options($actionID): array
    {
        $options = ['section', 'field', 'site'];

        return array_merge(parent::options($actionID), match ($actionID) {
            'import' => array_merge($options, ['type', 'match', 'updateOnly', 'dryRun']),
            'export' => array_merge($options, ['format', 'output', 'fields']),
            default => $options,
        });
    }

    /**
     * Imports locations from a CSV, GeoJSON, KML or GPX file into a section, creating or updating entries.
     */
    public function actionImport(string $file): int
    {
        if (($error = $this->check()) !== null) {
            return $error;
        }

        $format = LocationFiles::formatFromPath($file);
        if (!$format) {
            $this->stderr("Use a .csv, .geojson, .json, .kml or .gpx file.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }
        if (!is_file($file) || !is_readable($file)) {
            $this->stderr("Can’t read $file.\n", Console::FG_RED);
            return ExitCode::NOINPUT;
        }
        if (!in_array($this->match, ['slug', 'title', 'id'], true)) {
            $this->stderr("--match must be slug, title or id.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        [$section, $type, $field, $siteId] = $this->resolve(true);
        if (!$section) {
            return ExitCode::USAGE;
        }

        try {
            $records = LocationFiles::read((string)file_get_contents($file), $format);
        } catch (Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        // Other columns are saved into fields of the same handle, when the entry type has them.
        $layoutHandles = [];
        foreach ($type->getFieldLayout()->getCustomFields() as $f) {
            $layoutHandles[$f->handle] = true;
        }

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $ignored = [];
        $addressOnly = 0;

        foreach ($records as $i => $record) {
            $label = $record['title'] ?? $record['slug'] ?? ('row ' . ($i + 1));
            $entry = $this->findExisting($section, $siteId, $record);

            if (!$entry && ($this->updateOnly || $record['title'] === null)) {
                $counts['skipped']++;
                $this->stdout("  skip    $label" . ($record['title'] === null ? ' (no title)' : ' (not found)') . "\n", Console::FG_GREY);
                continue;
            }

            $value = $record['value'] ? GeoValue::fromArray($record['value']) : null;
            if ($value && $value->getInputErrors()) {
                $counts['failed']++;
                $this->stderr("  error   $label: " . implode(' ', $value->getInputErrors()) . "\n", Console::FG_RED);
                continue;
            }
            if ($value && !$value->hasCoordinates() && !$value->getGeoJsonData() && $value->full_address) {
                $addressOnly++;
            }

            if ($this->dryRun) {
                $counts[$entry ? 'updated' : 'created']++;
                $this->stdout('  ' . ($entry ? 'update' : 'create') . "  $label\n");
                continue;
            }

            if (!$entry) {
                $entry = new Entry(['sectionId' => $section->id, 'typeId' => $type->id, 'siteId' => $siteId]);
                $entry->title = $record['title'];
                if ($record['slug']) {
                    $entry->slug = $record['slug'];
                }
            }

            if ($value) {
                $entry->setFieldValue($field->handle, $value->toArray());
            }
            foreach ($record['fields'] as $name => $fieldValue) {
                if (isset($layoutHandles[$name]) && $name !== $field->handle) {
                    $entry->setFieldValue($name, $fieldValue);
                } elseif (!in_array($name, ['description', 'url'], true)) {
                    $ignored[$name] = true;
                }
            }

            $isNew = !$entry->id;
            if (Craft::$app->getElements()->saveElement($entry)) {
                $counts[$isNew ? 'created' : 'updated']++;
                $this->stdout('  ' . ($isNew ? 'created' : 'updated') . " $label\n", Console::FG_GREEN);
            } else {
                $counts['failed']++;
                $this->stderr("  error   $label: " . implode(' ', $entry->getFirstErrors()) . "\n", Console::FG_RED);
            }
        }

        $this->stdout("\n" . ($this->dryRun ? 'Would create' : 'Created') . " {$counts['created']}, " . ($this->dryRun ? 'update' : 'updated') . " {$counts['updated']}, skipped {$counts['skipped']}, failed {$counts['failed']}.\n");
        if ($ignored) {
            $this->stdout('Not imported (no field with that handle): ' . implode(', ', array_keys($ignored)) . "\n", Console::FG_YELLOW);
        }
        if ($addressOnly && !$this->dryRun) {
            $this->stdout("$addressOnly had an address but no coordinates. Run `php craft geomaps/geocode` to look them up" . (Plugin::getInstance()->getSettings()->geocodeMissing ? ', or wait for the background job.' : '.') . "\n");
        }

        return $counts['failed'] ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Exports a section's locations as CSV, GeoJSON or KML.
     */
    public function actionExport(): int
    {
        if (($error = $this->check()) !== null) {
            return $error;
        }

        $format = $this->format ?? ($this->output ? LocationFiles::formatFromPath($this->output) : null) ?? 'csv';
        if (!in_array($format, ['csv', 'geojson', 'kml'], true)) {
            $this->stderr("--format must be csv, geojson or kml.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        [$section, , $field, $siteId] = $this->resolve(false);
        if (!$section) {
            return ExitCode::USAGE;
        }

        $extra = array_values(array_filter(array_map('trim', explode(',', (string)$this->fields))));
        $rows = [];
        $query = Entry::find()->sectionId($section->id)->siteId($siteId)->status(null)->{$field->handle}(':notempty:');

        foreach ($query->each() as $entry) {
            /** @var Entry $entry */
            $value = $entry->getFieldValue($field->handle);
            if (!$value instanceof GeoValue) {
                continue;
            }
            $values = [];
            foreach ($extra as $name) {
                $values[$name] = $this->plain($entry->canGetProperty($name) ? $entry->$name : null);
            }
            $rows[] = ['id' => $entry->id, 'title' => (string)$entry->title, 'slug' => $entry->slug, 'url' => $entry->getUrl(), 'value' => $value, 'fields' => $values];
        }

        $contents = LocationFiles::write($rows, $format);

        if ($this->output) {
            if (file_put_contents($this->output, $contents) === false) {
                $this->stderr("Can’t write {$this->output}.\n", Console::FG_RED);
                return ExitCode::CANTCREAT;
            }
            $this->stdout('Exported ' . count($rows) . " locations to {$this->output}.\n", Console::FG_GREEN);
        } else {
            $this->stdout($contents);
        }

        return ExitCode::OK;
    }

    private function check(): ?int
    {
        if (!Plugin::getInstance()->isPro()) {
            $this->stderr("Importing and exporting locations needs Geo Maps Pro. Switch the edition under Settings → Plugins (free on local and staging sites).\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        if (!$this->section || !$this->field) {
            $this->stderr("Give the section and field, for example --section=stores --field=storeLocation.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        return null;
    }

    /**
     * @return array{0: ?Section, 1: ?EntryType, 2: ?GeoField, 3: ?int}
     */
    private function resolve(bool $needType): array
    {
        $none = [null, null, null, null];
        $section = Craft::$app->getEntries()->getSectionByHandle((string)$this->section);
        if (!$section) {
            $this->stderr("There’s no section with the handle {$this->section}.\n", Console::FG_RED);
            return $none;
        }

        $types = $section->getEntryTypes();
        $type = $this->type ? (array_values(array_filter($types, fn(EntryType $t) => $t->handle === $this->type))[0] ?? null) : ($types[0] ?? null);
        if ($needType && !$type) {
            $this->stderr("The {$section->handle} section has no entry type called {$this->type}.\n", Console::FG_RED);
            return $none;
        }

        $field = Craft::$app->getFields()->getFieldByHandle((string)$this->field);
        if (!$field instanceof GeoField) {
            $this->stderr("{$this->field} isn’t a Geo Maps field.\n", Console::FG_RED);
            return $none;
        }

        $site = $this->site ? Craft::$app->getSites()->getSiteByHandle($this->site) : Craft::$app->getSites()->getPrimarySite();
        if (!$site) {
            $this->stderr("There’s no site with the handle {$this->site}.\n", Console::FG_RED);
            return $none;
        }

        return [$section, $type, $field, $site->id];
    }

    private function findExisting(Section $section, int $siteId, array $record): ?Entry
    {
        $by = $this->match;
        $value = match ($by) {
            'id' => $record['id'],
            'title' => $record['title'],
            default => $record['slug'],
        };
        // Rows without a slug are matched by title, so running an import twice doesn't create duplicates.
        if (($value === null || $value === '') && $by === 'slug' && $record['title'] !== null) {
            [$by, $value] = ['title', $record['title']];
        }
        if ($value === null || $value === '') {
            return null;
        }

        return Entry::find()->sectionId($section->id)->siteId($siteId)->status(null)->$by($value)->one();
    }

    private function plain(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof \craft\elements\db\ElementQueryInterface => implode(', ', array_map(fn($e) => (string)$e, $value->all())),
            $value instanceof \Stringable => (string)$value,
            default => null,
        };
    }
}
