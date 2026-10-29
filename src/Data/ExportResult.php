<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport\Data;

/**
 * What an export wrote, left unchanged and removed, each listed by locale code, plus its warning messages.
 *
 * @phpstan-import-type StringList from ExportOptions
 */
final readonly class ExportResult
{
    /**
     * Creates a result; `skipped` tells why a source re-export wrote nothing.
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
        public ?SkipReason $skipped = null,
    ) {}

    /**
     * Returns a copy with one more warning.
     */
    public function withWarning(string $warning): self
    {
        return new self($this->written, $this->unchanged, $this->removed, [...$this->warnings, $warning], $this->skipped);
    }
}
