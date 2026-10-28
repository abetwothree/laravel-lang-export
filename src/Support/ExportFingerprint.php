<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Support;

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;

/**
 * Hashes what shapes an export besides the lang files themselves, so a `--source` patch can tell the last export
 * no longer matches.
 *
 * @phpstan-import-type StringList from ExportOptions
 */
final class ExportFingerprint
{
    /**
     * Hashes the package version, the options that shape the locale files and the registered lang directories.
     *
     * The output path is left out: the manifest holding the fingerprint lives there.
     */
    public static function compute(ExportOptions $options, LangSources $sources, string $version): string
    {
        $namespaces = $sources->namespaces();
        ksort($namespaces);

        return hash('xxh128', serialize([
            'version' => $version,
            'locales' => $options->locales === null ? null : self::sorted($options->locales),
            'only' => self::sorted($options->only),
            'except' => self::sorted($options->except),
            'json' => $options->json,
            'pretty_print' => $options->prettyPrint,
            // Path order decides which file overrides which, so it stays as the loader has it.
            'paths' => $sources->groupPaths(),
            'json_paths' => $sources->jsonPaths(),
            'namespaces' => $namespaces,
        ]));
    }

    /**
     * Returns the values de-duplicated and sorted, for options whose order does not change the output.
     *
     * @param  StringList  $values
     * @return StringList
     */
    private static function sorted(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }
}
