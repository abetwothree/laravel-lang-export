<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Parsing;

use AbeTwoThree\LaravelLangExport\Data\ParsedUnit;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use Illuminate\Filesystem\Filesystem;
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
    public function __construct(
        private LangSources $sources,
        private Filesystem $files,
    ) {}

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
            $file = $this->nonArrayFile($unit);

            throw $file === null
                ? LangExportException::unreadableUnit($unit, $previous)
                : LangExportException::langFileNotArray($unit, $file, $previous);
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

    /**
     * Returns the first existing group file of a unit that does not return an array, or null when there is none.
     *
     * FileLoader's array_replace_recursive() raises the TypeError, so its file and line name the framework.
     */
    private function nonArrayFile(TranslationUnit $unit): ?string
    {
        foreach ($this->sources->groupFiles($unit) as $file) {
            try {
                if ($this->files->isFile($file) && ! is_array($this->files->getRequire($file))) {
                    return $file;
                }
            } catch (Throwable) {
                // A file that fails to load is the cause the original error already names.
            }
        }

        return null;
    }
}
