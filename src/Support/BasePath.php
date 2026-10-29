<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport\Support;

/**
 * Resolves user-given paths against the app's base path, so config, flags and `--source` agree from any working dir.
 */
final class BasePath
{
    /**
     * Returns an absolute Unix or Windows path as given, or joins a relative one to the base path.
     */
    public static function resolve(string $path): string
    {
        return preg_match('#^([/\\\\]|[A-Za-z]:[/\\\\])#', $path) === 1 ? $path : base_path($path);
    }
}
