<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Support;

/**
 * Normalizes paths so the lang directories, `--output` and `--source` compare equal whatever their spelling.
 */
final class Path
{
    /**
     * Converts a path to forward slashes with `.`, `..` and doubled slashes collapsed, no trailing slash, and an
     * uppercase drive letter.
     *
     * Packages register `__DIR__.'/../lang'`. Collapsing lexically, unlike realpath(), still maps deleted files.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $root = preg_match('#^(?:[A-Za-z]:)?/*#', $path, $match) === 1 ? ucfirst($match[0]) : '';
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
}
