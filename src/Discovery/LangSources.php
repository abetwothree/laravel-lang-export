<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Discovery;

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

/**
 * Snapshot of the lang directories registered with the app's resolved translation loader.
 *
 * @phpstan-import-type StringList from ExportOptions
 * @phpstan-import-type NamespaceMap from ExportOptions
 */
final readonly class LangSources
{
    /**
     * @param  StringList  $groupPaths
     * @param  StringList  $jsonPaths
     * @param  NamespaceMap  $namespaces
     */
    private function __construct(
        private FileLoader $loader,
        private array $groupPaths,
        private array $jsonPaths,
        private array $namespaces,
    ) {}

    /**
     * Registers the configured directories with the translator's loader, then snapshots every lang directory it holds.
     *
     * Packages and modules register namespaces lazily, so this must run against the resolved translator.
     *
     * @throws LangExportException
     */
    public static function fromTranslator(Translator $translator, ExportOptions $options): self
    {
        $loader = $translator->getLoader();

        if (! $loader instanceof FileLoader) {
            throw LangExportException::unsupportedLoader($loader::class);
        }

        foreach ($options->paths as $path) {
            if (! in_array(self::normalize($path), self::normalizeList($loader->paths()), true)) {
                $loader->addPath($path);
            }
        }

        foreach ($options->jsonPaths as $path) {
            if (! in_array(self::normalize($path), self::normalizeList($loader->jsonPaths()), true)) {
                $loader->addJsonPath($path);
            }
        }

        // The loader keeps one hint per namespace, so a configured entry never replaces a package's own.
        foreach ($options->namespaces as $namespace => $path) {
            if (! array_key_exists($namespace, $loader->namespaces())) {
                $loader->addNamespace($namespace, $path);
            }
        }

        return new self(
            loader: $loader,
            groupPaths: self::normalizeList($loader->paths()),
            jsonPaths: self::normalizeList($loader->jsonPaths()),
            namespaces: array_map(self::normalize(...), $loader->namespaces()),
        );
    }

    /**
     * Returns the translation loader the directories were read from.
     */
    public function loader(): FileLoader
    {
        return $this->loader;
    }

    /**
     * Returns the directories holding PHP translation groups, in loader order.
     *
     * @return StringList
     */
    public function groupPaths(): array
    {
        return $this->groupPaths;
    }

    /**
     * Returns the directories holding JSON translation files, in loader order.
     *
     * @return StringList
     */
    public function jsonPaths(): array
    {
        return $this->jsonPaths;
    }

    /**
     * Returns each package namespace mapped to its lang directory.
     *
     * @return NamespaceMap
     */
    public function namespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Returns every registered lang, JSON and namespace directory, in that order.
     *
     * @return StringList
     */
    public function directories(): array
    {
        return [...$this->groupPaths, ...$this->jsonPaths, ...array_values($this->namespaces)];
    }

    /**
     * Returns every existing lang directory, relative to the base path when inside it.
     *
     * Relative paths let a Vite process on the host read a manifest written inside a Sail container.
     *
     * @return StringList
     */
    public function watchDirectories(): array
    {
        $base = self::normalize(base_path()).'/';
        $directories = [];

        foreach ($this->directories() as $path) {
            if (is_dir($path)) {
                $directories[] = str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
            }
        }

        $directories = array_values(array_unique($directories));
        sort($directories);

        return $directories;
    }

    /**
     * Converts a path to forward slashes with `.` and `..` collapsed and no trailing slash.
     *
     * Packages register `__DIR__.'/../lang'`. Collapsing lexically, unlike realpath(), still maps deleted files.
     */
    private static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $root = preg_match('#^(?:[A-Za-z]:)?/*#', $path, $match) === 1 ? $match[0] : '';
        $segments = [];

        foreach (explode('/', substr($path, strlen($root))) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment !== '..' || ($segments === [] && $root === '') || end($segments) === '..') {
                $segments[] = $segment;
            } elseif ($segments !== []) {
                array_pop($segments);
            }
        }

        return rtrim($root.implode('/', $segments), '/');
    }

    /**
     * Normalizes the string entries of a loader path list.
     *
     * @param  array<array-key, mixed>  $paths
     * @return StringList
     */
    private static function normalizeList(array $paths): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            if (is_string($path)) {
                $normalized[] = self::normalize($path);
            }
        }

        return $normalized;
    }
}
