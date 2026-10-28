<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Discovery;

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use AbeTwoThree\LaravelLangExport\Support\Path;
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
     * Holds the loader and its normalized directory lists; use fromTranslator() to build one.
     *
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
            if (! in_array(Path::normalize($path), self::normalizeList($loader->paths()), true)) {
                $loader->addPath($path);
            }
        }

        foreach ($options->jsonPaths as $path) {
            if (! in_array(Path::normalize($path), self::normalizeList($loader->jsonPaths()), true)) {
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
            namespaces: array_map(Path::normalize(...), $loader->namespaces()),
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
     * Tells whether a path names a registered lang, JSON or namespace directory, whatever its slashes.
     */
    public function hasDirectory(string $path): bool
    {
        return in_array(Path::normalize($path), $this->directories(), true);
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
        $base = Path::normalize(base_path()).'/';
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
     * Returns the PHP files the loader merges for a group unit, in load order, whether or not they exist.
     *
     * Mirrors FileLoader::load(): an app group reads every lang path, a package group its hint and then each lang
     * path's `vendor/{namespace}` override. A JSON unit or an unregistered namespace has none.
     *
     * @return StringList
     */
    public function groupFiles(TranslationUnit $unit): array
    {
        $file = "{$unit->locale}/{$unit->group}.php";

        if ($unit->isJson()) {
            return [];
        }

        if ($unit->namespace === TranslationUnit::WILDCARD) {
            return array_map(static fn (string $path): string => "{$path}/{$file}", $this->groupPaths);
        }

        if (! array_key_exists($unit->namespace, $this->namespaces)) {
            return [];
        }

        return [
            "{$this->namespaces[$unit->namespace]}/{$file}",
            ...array_map(
                static fn (string $path): string => "{$path}/vendor/{$unit->namespace}/{$file}",
                $this->groupPaths,
            ),
        ];
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
                $normalized[] = Path::normalize($path);
            }
        }

        return $normalized;
    }
}
