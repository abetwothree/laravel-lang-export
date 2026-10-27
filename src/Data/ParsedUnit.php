<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Data;

/**
 * A translation unit's merged lines, plus a warning for every value dropped on the way.
 *
 * @phpstan-type LangGroup = array<array-key, string|array<array-key, mixed>>
 */
final readonly class ParsedUnit
{
    /**
     * @param  LangGroup  $lines
     * @param  list<string>  $warnings
     */
    public function __construct(
        public TranslationUnit $unit,
        public array $lines,
        public array $warnings,
    ) {}
}
