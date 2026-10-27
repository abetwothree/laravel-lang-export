<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Exceptions;

use Illuminate\Translation\FileLoader;
use RuntimeException;

class LangExportException extends RuntimeException
{
    /**
     * Reports a translation loader whose directories cannot be read.
     */
    public static function unsupportedLoader(string $class): self
    {
        return new self(sprintf('The translation loader [%s] must extend %s.', $class, FileLoader::class));
    }
}
