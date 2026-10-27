<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Discovery;

use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Support\BasePath;
use Illuminate\Filesystem\Filesystem;

/**
 * Scans the lang sources into translation units, and maps a single file path back to its unit.
 */
final readonly class UnitFinder
{
    private const string VENDOR = 'vendor';

    /**
     * Creates a finder over a snapshot of lang sources.
     */
    public function __construct(
        private LangSources $sources,
        private Filesystem $files,
    ) {}

    /**
     * Returns every unit on disk, de-duplicated and sorted by locale then key.
     *
     * @return list<TranslationUnit>
     */
    public function all(): array
    {
        $units = [];

        foreach ($this->sources->groupPaths() as $path) {
            foreach ($this->localeDirectories($path) as $locale) {
                if ($locale !== self::VENDOR) {
                    $this->collectGroups($units, "{$path}/{$locale}", $locale, TranslationUnit::WILDCARD);
                }
            }

            foreach ($this->sources->namespaces() as $namespace => $hint) {
                $this->collectNamespace($units, "{$path}/".self::VENDOR."/{$namespace}", $namespace);
            }
        }

        foreach ($this->sources->namespaces() as $namespace => $hint) {
            $this->collectNamespace($units, $hint, $namespace);
        }

        foreach ([...$this->sources->groupPaths(), ...$this->sources->jsonPaths()] as $directory) {
            foreach ($this->files->glob("{$directory}/*.json") as $file) {
                if (! is_string($file)) {
                    continue;
                }

                $unit = TranslationUnit::json(basename($file, '.json'));
                $units[$unit->locale."\0".$unit->key()] = $unit;
            }
        }

        $units = array_values($units);
        usort($units, static fn (TranslationUnit $a, TranslationUnit $b): int => strcmp($a->locale, $b->locale) ?: strcmp($a->key(), $b->key()));

        return $units;
    }

    /**
     * Maps a file path to its unit, even when the file no longer exists, or returns null when no unit owns it.
     */
    public function forFile(string $path): ?TranslationUnit
    {
        $path = rtrim(str_replace('\\', '/', BasePath::resolve($path)), '/');
        $segments = explode('/', $path);

        // The loader would follow a `..` out of the lang directory this prefix match vouches for.
        if (in_array('..', $segments, true)) {
            return null;
        }

        $path = implode('/', array_filter($segments, static fn (string $segment): bool => $segment !== '.'));

        foreach ($this->sources->groupPaths() as $groupPath) {
            foreach ($this->sources->namespaces() as $namespace => $hint) {
                $unit = $this->groupUnit($path, "{$groupPath}/".self::VENDOR."/{$namespace}", $namespace);

                if ($unit !== null) {
                    return $unit;
                }
            }
        }

        if (str_ends_with($path, '.json')
            && in_array(dirname($path), [...$this->sources->jsonPaths(), ...$this->sources->groupPaths()], true)) {
            return TranslationUnit::json(basename($path, '.json'));
        }

        foreach ($this->sources->namespaces() as $namespace => $hint) {
            $unit = $this->groupUnit($path, $hint, $namespace);

            if ($unit !== null) {
                return $unit;
            }
        }

        foreach ($this->sources->groupPaths() as $groupPath) {
            $unit = $this->groupUnit($path, $groupPath, TranslationUnit::WILDCARD);

            if ($unit !== null && $unit->locale !== self::VENDOR) {
                return $unit;
            }
        }

        return null;
    }

    /**
     * Reads `$base/{locale}/{group}.php` from a path, or returns null when the path does not have that shape.
     */
    private function groupUnit(string $path, string $base, string $namespace): ?TranslationUnit
    {
        if (! str_starts_with($path, "{$base}/") || ! str_ends_with($path, '.php')) {
            return null;
        }

        $parts = explode('/', substr($path, strlen($base) + 1, -4), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return new TranslationUnit($parts[0], $namespace, $parts[1]);
    }

    /**
     * Adds the units of every locale directory below a namespace's lang directory.
     *
     * @param  array<string, TranslationUnit>  $units
     */
    private function collectNamespace(array &$units, string $directory, string $namespace): void
    {
        foreach ($this->localeDirectories($directory) as $locale) {
            $this->collectGroups($units, "{$directory}/{$locale}", $locale, $namespace);
        }
    }

    /**
     * Adds a unit for each PHP file below a locale directory.
     *
     * @param  array<string, TranslationUnit>  $units
     */
    private function collectGroups(array &$units, string $localeDirectory, string $locale, string $namespace): void
    {
        foreach ($this->files->allFiles($localeDirectory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $group = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -4));
            $unit = new TranslationUnit($locale, $namespace, $group);
            $units[$locale."\0".$unit->key()] = $unit;
        }
    }

    /**
     * Lists the names of the sub-directories of a directory, or none when it does not exist.
     *
     * @return list<string>
     */
    private function localeDirectories(string $directory): array
    {
        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $path): string => basename(is_string($path) ? $path : ''),
            $this->files->directories($directory),
        ));
    }
}
