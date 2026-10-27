<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport;

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\ExportResult;
use AbeTwoThree\LaravelLangExport\Data\Manifest;
use AbeTwoThree\LaravelLangExport\Data\ParsedUnit;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;
use AbeTwoThree\LaravelLangExport\Discovery\UnitFilter;
use AbeTwoThree\LaravelLangExport\Discovery\UnitFinder;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use AbeTwoThree\LaravelLangExport\Output\LocaleDocument;
use AbeTwoThree\LaravelLangExport\Output\OutputDirectory;
use AbeTwoThree\LaravelLangExport\Parsing\TranslationParser;

use function array_push;
use function basename;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\Translator;

use function in_array;

/**
 * Runs the export pipeline from the translator's lang sources to the locale files and manifest on disk.
 *
 * @phpstan-import-type StringList from ExportOptions
 *
 * @phpstan-type ParsedLocales = array<string, list<ParsedUnit>>
 */
final readonly class LangExporter
{
    /**
     * Creates an exporter reading through the app's translator.
     */
    public function __construct(
        private Translator $translator,
        private Filesystem $files,
    ) {}

    /**
     * Writes one file per allowed locale plus the manifest, removing the files of locales the last export had.
     *
     * The output directory mirrors the latest full export, so narrowing the locales deletes the others' files.
     *
     * @throws LangExportException
     */
    public function exportAll(ExportOptions $options): ExportResult
    {
        $sources = LangSources::fromTranslator($this->translator, $options);
        $parsedLocales = $this->parseLocales($sources, $options);
        $output = new OutputDirectory($this->files, $options->outputPath);
        $locales = [];
        $written = [];
        $unchanged = [];
        $warnings = [];

        foreach ($parsedLocales as $locale => $units) {
            $document = new LocaleDocument($locale);

            foreach ($units as $parsed) {
                $document->put($parsed);
                array_push($warnings, ...$parsed->warnings);
            }

            array_push($warnings, ...$document->warnings());
            $locales[] = $locale;

            if ($output->write($document, $options->prettyPrint)) {
                $written[] = $locale;
            } else {
                $unchanged[] = $locale;
            }
        }

        $removed = $this->removeDropped($output, $locales);
        $output->writeManifest(new Manifest($locales, $sources->watchDirectories()));

        return new ExportResult($written, $unchanged, $removed, $warnings);
    }

    /**
     * Parses every allowed unit, grouped by locale; a locale whose units were all filtered out keeps an empty list.
     *
     * Parsing everything before the first write means a broken lang file aborts with the output untouched.
     * Units arrive sorted by locale, so the locales come out sorted too.
     *
     * @return ParsedLocales
     *
     * @throws LangExportException
     */
    private function parseLocales(LangSources $sources, ExportOptions $options): array
    {
        $filter = new UnitFilter($options);
        $parser = new TranslationParser($sources);
        $locales = [];

        foreach ((new UnitFinder($sources, $this->files))->all() as $unit) {
            if (! $filter->allowsLocale($unit->locale)) {
                continue;
            }

            $locales[$unit->locale] ??= [];

            if ($filter->allows($unit)) {
                $locales[$unit->locale][] = $parser->parse($unit);
            }
        }

        return $locales;
    }

    /**
     * Deletes the file of each locale the previous manifest listed that this export no longer writes.
     *
     * @param  StringList  $locales
     * @return StringList
     */
    private function removeDropped(OutputDirectory $output, array $locales): array
    {
        $removed = [];

        foreach ($output->readManifest()->locales ?? [] as $locale) {
            // A hand-edited manifest must never reach a file outside the output directory.
            if (basename($locale) !== $locale || in_array($locale, $locales, true)) {
                continue;
            }

            if ($output->delete($locale)) {
                $removed[] = $locale;
            }
        }

        return $removed;
    }
}
