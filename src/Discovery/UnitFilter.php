<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport\Discovery;

use AbeTwoThree\LangExport\Data\ExportOptions;
use AbeTwoThree\LangExport\Data\TranslationUnit;
use Illuminate\Support\Str;

/**
 * Decides which locales and translation units the export includes.
 */
final readonly class UnitFilter
{
    /**
     * Creates a filter over the given export options.
     */
    public function __construct(private ExportOptions $options) {}

    /**
     * Tells whether a locale is part of the export.
     */
    public function allowsLocale(string $locale): bool
    {
        return $this->options->locales === null || in_array($locale, $this->options->locales, true);
    }

    /**
     * Tells whether a unit passes the locale, json, only and except rules.
     */
    public function allows(TranslationUnit $unit): bool
    {
        if (! $this->allowsLocale($unit->locale)) {
            return false;
        }

        if ($unit->isJson()) {
            return $this->options->json;
        }

        $key = $unit->key();

        if ($this->options->only !== [] && ! Str::is($this->options->only, $key)) {
            return false;
        }

        return ! Str::is($this->options->except, $key);
    }
}
