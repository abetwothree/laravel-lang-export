<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport\Output;

use AbeTwoThree\LangExport\Data\ParsedUnit;
use AbeTwoThree\LangExport\Exceptions\LangExportException;
use JsonException;
use stdClass;

/**
 * One locale's export file: its JSON lines and groups, assembled unit by unit.
 *
 * @phpstan-import-type LangGroup from ParsedUnit
 *
 * @phpstan-type LocaleLines array<array-key, string|LangGroup>
 */
final class LocaleDocument
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * Creates a locale's document, empty or holding lines read back from its file.
     *
     * @param  LocaleLines  $lines
     */
    public function __construct(
        public readonly string $locale,
        private array $lines = [],
    ) {}

    /**
     * Reads a document back from its JSON, or returns null unless it is an object of strings and nested objects.
     *
     * A hand-edited file can hold any JSON, and a patch would otherwise carry its other values into the export.
     */
    public static function fromJson(string $locale, string $json): ?self
    {
        try {
            if (! json_decode($json, false, 512, JSON_THROW_ON_ERROR) instanceof stdClass) {
                return null;
            }

            $lines = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($lines) || ! self::isLangGroup($lines)) {
            return null;
        }

        return new self($locale, $lines);
    }

    /**
     * Replaces this document's share of a unit, a group under its key or all JSON lines, keeping the unit's warnings.
     */
    public function put(ParsedUnit $parsed): void
    {
        array_push($this->warnings, ...$parsed->warnings);
        $key = $parsed->unit->key();

        if (! $parsed->unit->isJson()) {
            $this->putGroup($key, $parsed);

            return;
        }

        foreach ($this->lines as $existing => $value) {
            if (is_string($value)) {
                unset($this->lines[$existing]);
            }
        }

        foreach ($parsed->lines as $line => $value) {
            if (! is_string($value)) {
                $this->warnings[] = "{$this->locale}: {$line} skipped (".get_debug_type($value).')';
            } elseif (is_array($this->lines[$line] ?? null)) {
                $this->warnings[] = $this->collisionWarning((string) $line);
            } else {
                $this->lines[$line] = $value;
            }
        }
    }

    /**
     * Returns the warnings of every unit put, in put order, with each unit's collisions after its own.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Encodes the document with sorted top-level keys; the top level is always an object.
     *
     * @throws LangExportException
     */
    public function toJson(bool $pretty): string
    {
        $lines = $this->lines;
        ksort($lines, SORT_STRING);

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        try {
            return json_encode((object) $lines, $pretty ? $flags | JSON_PRETTY_PRINT : $flags);
        } catch (JsonException $exception) {
            throw LangExportException::unencodableLocale($this->locale, $exception);
        }
    }

    /**
     * Tells whether every value of an array is a string or such an array, at any depth.
     *
     * @param  array<array-key, mixed>  $values
     *
     * @phpstan-assert-if-true LocaleLines $values
     */
    private static function isLangGroup(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_string($value) && ! (is_array($value) && self::isLangGroup($value))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets a group, replacing a same-keyed JSON line, or removes the group when it is empty.
     */
    private function putGroup(string $key, ParsedUnit $parsed): void
    {
        if ($parsed->lines === []) {
            // `__()` still resolves a same-named JSON line when the group file is empty, so only a group is removed.
            if (is_array($this->lines[$key] ?? null)) {
                unset($this->lines[$key]);
            }

            return;
        }

        if (is_string($this->lines[$key] ?? null)) {
            $this->warnings[] = $this->collisionWarning($key);
        }

        $this->lines[$key] = $parsed->lines;
    }

    /**
     * Builds the warning for a JSON line a group displaced.
     */
    private function collisionWarning(string $key): string
    {
        return "{$this->locale}: JSON line \"{$key}\" skipped, a group has the same key";
    }
}
