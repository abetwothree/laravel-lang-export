<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Parsing;

use AbeTwoThree\LaravelLangExport\Data\ParsedUnit;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use Throwable;

/**
 * Loads one translation unit through Laravel's own loader and keeps only what `__()` can resolve.
 *
 * @phpstan-import-type LangGroup from ParsedUnit
 */
final readonly class TranslationParser
{
    /**
     * Creates a parser reading from the given lang sources.
     */
    public function __construct(private LangSources $sources) {}

    /**
     * Loads a unit, keeping strings and arrays and warning about every other value.
     *
     * @throws LangExportException
     */
    public function parse(TranslationUnit $unit): ParsedUnit
    {
        try {
            $loaded = $this->sources->loader()->load($unit->locale, $unit->group, $unit->namespace);
        } catch (Throwable $previous) {
            throw LangExportException::unreadableUnit($unit, $previous);
        }

        $warnings = [];
        $prefix = "{$unit->locale}: ";
        $lines = [];

        foreach ($loaded as $key => $value) {
            if (is_string($value)) {
                $lines[$key] = $value;
            } elseif (is_array($value) && ! $unit->isJson()) {
                $lines[$key] = self::normalize($value, $prefix.$unit->key().'.'.$key, $warnings);
            } else {
                $name = $unit->isJson() ? (string) $key : $unit->key().'.'.$key;
                $warnings[] = "{$prefix}{$name} skipped (".get_debug_type($value).')';
            }
        }

        return new ParsedUnit($unit, $lines, $warnings);
    }

    /**
     * Keeps the strings and nested arrays of a group, recording a warning for each dropped leaf.
     *
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $warnings
     * @return array<array-key, mixed>
     */
    private static function normalize(array $values, string $path, array &$warnings): array
    {
        $kept = [];

        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $kept[$key] = $value;
            } elseif (is_array($value)) {
                $kept[$key] = self::normalize($value, "{$path}.{$key}", $warnings);
            } else {
                $warnings[] = "{$path}.{$key} skipped (".get_debug_type($value).')';
            }
        }

        return $kept;
    }
}
