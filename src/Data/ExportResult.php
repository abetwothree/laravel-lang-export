<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Data;

/**
 * What an export wrote, left unchanged, removed and warned about, each listed by locale code.
 *
 * @phpstan-import-type StringList from ExportOptions
 */
final readonly class ExportResult
{
    /**
     * Creates a result; `skipped` marks an export the filters excluded entirely.
     *
     * @param  StringList  $written
     * @param  StringList  $unchanged
     * @param  StringList  $removed
     * @param  StringList  $warnings
     */
    public function __construct(
        public array $written = [],
        public array $unchanged = [],
        public array $removed = [],
        public array $warnings = [],
        public bool $skipped = false,
    ) {}
}
