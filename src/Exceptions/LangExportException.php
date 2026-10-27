<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Exceptions;

use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use Illuminate\Translation\FileLoader;
use RuntimeException;
use Throwable;

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
}
