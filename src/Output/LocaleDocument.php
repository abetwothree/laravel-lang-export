<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Output;

use AbeTwoThree\LaravelLangExport\Data\ParsedUnit;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use JsonException;

use function ksort;

use const SORT_STRING;

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
     * @param  LocaleLines  $lines
     */
    public function __construct(
        public readonly string $locale,
        private array $lines = [],
    ) {}

    /**
     * Reads a document back from its JSON, or returns null when the JSON is invalid or not an object.
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

        /** @var LocaleLines $lines */
        return new self($locale, $lines);
    }

    /**
     * Replaces this document's share of a unit: a group under its key, or all JSON lines.
     */
    public function put(ParsedUnit $parsed): void
    {
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
            if (is_array($this->lines[$line] ?? null) || ! is_string($value)) {
                $this->warnings[] = $this->collisionWarning((string) $line);

                continue;
            }

            $this->lines[$line] = $value;
        }
    }

    /**
     * Returns the collision warnings gathered by put().
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Encodes the document with sorted top-level keys; the top level is always an object.
     */
    public function toJson(bool $pretty): string
    {
        $lines = $this->lines;
        ksort($lines, SORT_STRING);

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        return json_encode((object) $lines, $pretty ? $flags | JSON_PRETTY_PRINT : $flags);
    }

    /**
     * Sets or removes a group, replacing a same-keyed JSON line.
     */
    private function putGroup(string $key, ParsedUnit $parsed): void
    {
        if (is_string($this->lines[$key] ?? null)) {
            $this->warnings[] = $this->collisionWarning($key);
        }

        if ($parsed->lines === []) {
            unset($this->lines[$key]);

            return;
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
