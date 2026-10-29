<?php

declare(strict_types=1);

use AbeTwoThree\LangExport\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Normalizes a path to forward slashes with no trailing slash, so paths compare equal on every OS.
 */
function normalizePath(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}
