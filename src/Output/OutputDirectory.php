<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Output;

use AbeTwoThree\LaravelLangExport\Data\Manifest;
use Illuminate\Filesystem\Filesystem;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use JsonException;

/**
 * The export directory: reads and atomically writes locale files and the manifest, skipping unchanged content.
 */
final readonly class OutputDirectory
{
    private const string MANIFEST = 'manifest.json';

    /**
     * Creates the directory handle; nothing is touched until a read or write.
     */
    public function __construct(
        private Filesystem $files,
        private string $path,
    ) {}

    /**
     * Reads a locale's document, or returns null when the file is missing or invalid.
     */
    public function read(string $locale): ?LocaleDocument
    {
        $contents = $this->contents($this->localePath($locale));

        return $contents === null ? null : LocaleDocument::fromJson($locale, $contents);
    }

    /**
     * Writes a locale's document; returns false when the file already holds the same bytes.
     */
    public function write(LocaleDocument $document, bool $pretty): bool
    {
        return $this->writeIfChanged($this->localePath($document->locale), $document->toJson($pretty)."\n");
    }

    /**
     * Deletes a locale's file; returns false when there was none.
     */
    public function delete(string $locale): bool
    {
        $file = $this->localePath($locale);

        return $this->files->delete($file);
    }

    /**
     * Reads the manifest, or returns null when it is missing or invalid.
     */
    public function readManifest(): ?Manifest
    {
        $contents = $this->contents($this->path.'/'.self::MANIFEST);

        if ($contents === null) {
            return null;
        }

        try {
            return Manifest::fromArray(json_decode($contents, true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Writes the manifest; returns false when the file already holds the same bytes.
     */
    public function writeManifest(Manifest $manifest): bool
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        return $this->writeIfChanged($this->path.'/'.self::MANIFEST, json_encode($manifest->toArray(), $flags)."\n");
    }

    /**
     * Returns a locale file's path.
     */
    private function localePath(string $locale): string
    {
        return "{$this->path}/{$locale}.json";
    }

    /**
     * Returns a file's contents, or null when it does not exist.
     */
    private function contents(string $file): ?string
    {
        return $this->files->isFile($file) ? $this->files->get($file) : null;
    }

    /**
     * Writes through a temp sibling and rename so readers never see a partial file.
     */
    private function writeIfChanged(string $file, string $contents): bool
    {
        if ($this->contents($file) === $contents) {
            return false;
        }

        $this->files->ensureDirectoryExists($this->path);
        $temp = "{$file}.tmp";
        $this->files->put($temp, $contents);
        $this->files->move($temp, $file);

        return true;
    }
}
