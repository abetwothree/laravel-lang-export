<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport\Output;

use AbeTwoThree\LangExport\Data\Manifest;
use AbeTwoThree\LangExport\Exceptions\LangExportException;
use ErrorException;
use Illuminate\Filesystem\Filesystem;
use JsonException;

/**
 * The export directory: reads and atomically writes locale files and the manifest, skipping unchanged content.
 */
final readonly class OutputDirectory
{
    private const string MANIFEST = 'manifest.json';

    /** The name writeIfChanged() gives a temp file: the target, 8 random bytes in hex, then `.tmp`. */
    private const string TEMP_FILE = '/\.json\.[0-9a-f]{16}\.tmp$/';

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
     * Writes a locale's already encoded JSON; returns false when the file already holds the same bytes.
     */
    public function writeJson(string $locale, string $json): bool
    {
        return $this->writeIfChanged($this->localePath($locale), $json."\n");
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
     * Deletes the temp files a run that died between write and rename left behind; the caller holds the export lock.
     */
    public function removeTempFiles(): void
    {
        if (! $this->files->isDirectory($this->path)) {
            return;
        }

        foreach ($this->files->files($this->path) as $file) {
            if (preg_match(self::TEMP_FILE, $file->getFilename()) === 1) {
                $this->files->delete($file->getPathname());
            }
        }
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
        // A temp name per write keeps two runs writing the same file from renaming each other's half-written temp.
        $temp = "{$file}.".bin2hex(random_bytes(8)).'.tmp';
        $error = null;

        try {
            $written = $this->files->put($temp, $contents) !== false && $this->files->move($temp, $file);
        } catch (ErrorException $exception) {
            $error = $exception;
            $written = false;
        }

        if (! $written) {
            $this->files->delete($temp);

            throw LangExportException::unwritableFile($file, $error);
        }

        return true;
    }
}
