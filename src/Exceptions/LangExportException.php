<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Exceptions;

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use Illuminate\Translation\FileLoader;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Every expected export failure; the command prints its message and exits 1.
 *
 * @phpstan-import-type StringList from ExportOptions
 */
class LangExportException extends RuntimeException
{
    /**
     * Reports a translation loader whose directories cannot be read.
     */
    public static function unsupportedLoader(string $class): self
    {
        return new self(sprintf('The translation loader [%s] must extend %s.', $class, FileLoader::class));
    }

    /**
     * Reports a malformed `lang-export` config value, keeping the validator's message.
     */
    public static function invalidConfig(InvalidArgumentException $previous): self
    {
        return new self($previous->getMessage(), previous: $previous);
    }

    /**
     * Reports an output path that is the base path, where the export would write into the project root.
     */
    public static function outputIsBasePath(string $path): self
    {
        return new self(sprintf('The output path [%s] is the base path.', $path));
    }

    /**
     * Reports an output path that is a lang directory, where the export would overwrite `{locale}.json` lang files.
     */
    public static function outputIsLangDirectory(string $path): self
    {
        return new self(sprintf('The output path [%s] is a registered lang directory.', $path));
    }

    /**
     * Reports a source path that no registered lang directory owns.
     */
    public static function notTranslationFile(string $path): self
    {
        return new self(sprintf('[%s] is not inside a registered lang directory.', $path));
    }

    /**
     * Reports a locale filter that matches no lang files.
     *
     * @param  StringList  $locales
     */
    public static function noLangFiles(array $locales): self
    {
        return new self(sprintf('No lang files found for the requested locales [%s].', implode(', ', $locales)));
    }

    /**
     * Reports a translation unit whose lang file cannot be loaded.
     */
    public static function unreadableUnit(TranslationUnit $unit, Throwable $previous): self
    {
        return new self(
            sprintf(
                'Could not load %s %s (%s:%d): %s',
                $unit->locale,
                $unit->key(),
                $previous->getFile(),
                $previous->getLine(),
                $previous->getMessage(),
            ),
            previous: $previous,
        );
    }

    /**
     * Reports a lang file that does not return an array, such as an empty file an editor just created.
     */
    public static function langFileNotArray(TranslationUnit $unit, string $file, Throwable $previous): self
    {
        return new self(
            sprintf('Could not load %s %s (%s): the file must return an array.', $unit->locale, $unit->key(), $file),
            previous: $previous,
        );
    }

    /**
     * Reports a locale file whose lines cannot be encoded as JSON.
     */
    public static function unencodableLocale(string $locale, Throwable $previous): self
    {
        return new self(
            sprintf('Could not encode the %s locale file: %s', $locale, $previous->getMessage()),
            previous: $previous,
        );
    }

    /**
     * Reports an export lock that another run held past the wait timeout.
     */
    public static function lockTimeout(string $path, float $seconds): self
    {
        return new self(sprintf('Timed out after %s seconds waiting for the export lock [%s].', $seconds, $path));
    }

    /**
     * Reports an export file that could not be written, with the OS error behind it when there is one.
     */
    public static function unwritableFile(string $path, ?Throwable $previous = null): self
    {
        return new self(
            $previous === null
                ? sprintf('Could not write [%s].', $path)
                : sprintf('Could not write [%s]: %s', $path, $previous->getMessage()),
            previous: $previous,
        );
    }
}
