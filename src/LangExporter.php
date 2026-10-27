<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport;

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\ExportResult;
use AbeTwoThree\LaravelLangExport\Data\Manifest;
use AbeTwoThree\LaravelLangExport\Data\ParsedUnit;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;
use AbeTwoThree\LaravelLangExport\Discovery\UnitFilter;
use AbeTwoThree\LaravelLangExport\Discovery\UnitFinder;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use AbeTwoThree\LaravelLangExport\Output\LocaleDocument;
use AbeTwoThree\LaravelLangExport\Output\OutputDirectory;
use AbeTwoThree\LaravelLangExport\Parsing\TranslationParser;

use function array_diff;
use function array_keys;
use function array_push;
use function array_values;
use function basename;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\LockableFile;
use Illuminate\Translation\Translator;

use function in_array;
use function sort;

use const SORT_STRING;

use function storage_path;

use Throwable;

/**
 * Runs the export pipeline from the translator's lang sources to the locale files and manifest on disk.
 *
 * @phpstan-import-type StringList from ExportOptions
 *
 * @phpstan-type ParsedLocales = array<string, list<ParsedUnit>>
 */
final readonly class LangExporter
{
    private const string LOCK_FILE = 'framework/lang-export.lock';

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
     * Every unit is parsed and every document encoded before the first write, so a parse or encode failure leaves
     * the output untouched. The output directory mirrors the latest full export, so narrowing the locales deletes
     * the others' files.
     *
     * @throws LangExportException
     */
    public function exportAll(ExportOptions $options): ExportResult
    {
        $sources = LangSources::fromTranslator($this->translator, $options);

        return $this->locked(fn (): ExportResult => $this->exportSources($sources, $options));
    }

    /**
     * Re-exports the unit one changed, added or deleted lang file belongs to, writing what a full export would.
     *
     * Used by a file watcher on every change. The unit is patched into its locale's existing file; a missing manifest
     * falls back to a full export, and a missing, unlisted or unreadable locale file to a whole-locale export.
     *
     * @throws LangExportException
     */
    public function exportFile(string $path, ExportOptions $options): ExportResult
    {
        $sources = LangSources::fromTranslator($this->translator, $options);
        $unit = (new UnitFinder($sources, $this->files))->forFile($path);

        if ($unit === null) {
            throw LangExportException::notTranslationFile($path);
        }

        if (! (new UnitFilter($options))->allows($unit)) {
            return new ExportResult(skipped: true);
        }

        return $this->locked(fn (): ExportResult => $this->exportUnit($sources, $options, $unit));
    }

    /**
     * Parses every unit and writes the locale files and manifest; the caller holds the export lock.
     *
     * @throws LangExportException
     */
    private function exportSources(LangSources $sources, ExportOptions $options): ExportResult
    {
        $parsedLocales = $this->parseLocales($sources, $options);
        $output = new OutputDirectory($this->files, $options->outputPath);
        $result = $this->writeLocales($output, $parsedLocales, $options->prettyPrint);
        $locales = array_keys($parsedLocales);
        $removed = $this->removeDropped($output, $locales);
        $output->writeManifest(new Manifest($locales, $sources->watchDirectories()));

        return new ExportResult($result->written, $result->unchanged, $removed, $result->warnings);
    }

    /**
     * Patches one unit into its locale file, falling back to a wider export; the caller holds the export lock.
     *
     * @throws LangExportException
     */
    private function exportUnit(LangSources $sources, ExportOptions $options, TranslationUnit $unit): ExportResult
    {
        $output = new OutputDirectory($this->files, $options->outputPath);
        $manifest = $output->readManifest();

        if ($manifest === null) {
            return $this->exportSources($sources, $options);
        }

        $document = in_array($unit->locale, $manifest->locales, true) ? $output->read($unit->locale) : null;

        if ($document === null) {
            return $this->exportLocale($sources, $options, $output, $manifest, $unit->locale);
        }

        $parsed = (new TranslationParser($sources))->parse($unit);

        // Dropping a key can uncover a JSON line it displaced or leave the locale empty; only a full re-read knows.
        if ($parsed->lines === []) {
            return $this->exportLocale($sources, $options, $output, $manifest, $unit->locale);
        }

        $document->put($parsed);
        $json = $document->toJson($options->prettyPrint);
        $warnings = [...$parsed->warnings, ...$document->warnings()];

        return $output->writeJson($unit->locale, $json)
            ? new ExportResult(written: [$unit->locale], warnings: $warnings)
            : new ExportResult(unchanged: [$unit->locale], warnings: $warnings);
    }

    /**
     * Parses every allowed unit, grouped by locale; a locale whose units were all filtered out keeps an empty list.
     *
     * Units arrive sorted by locale, so the locales come out sorted too. A given locale limits the parse to its units.
     *
     * @return ParsedLocales
     *
     * @throws LangExportException
     */
    private function parseLocales(LangSources $sources, ExportOptions $options, ?string $locale = null): array
    {
        $filter = new UnitFilter($options);
        $parser = new TranslationParser($sources);
        $locales = [];

        foreach ((new UnitFinder($sources, $this->files))->all() as $unit) {
            if (! $filter->allowsLocale($unit->locale) || ($locale !== null && $unit->locale !== $locale)) {
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
     * Assembles and encodes every locale's document before writing any, so an unencodable line writes nothing.
     *
     * @param  ParsedLocales  $parsedLocales
     *
     * @throws LangExportException
     */
    private function writeLocales(OutputDirectory $output, array $parsedLocales, bool $pretty): ExportResult
    {
        $encoded = [];
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
            $encoded[$locale] = $document->toJson($pretty);
        }

        foreach ($encoded as $locale => $json) {
            if ($output->writeJson($locale, $json)) {
                $written[] = $locale;
            } else {
                $unchanged[] = $locale;
            }
        }

        return new ExportResult($written, $unchanged, warnings: $warnings);
    }

    /**
     * Rewrites one locale's file from every unit on disk and lists it in the manifest, or drops it when none is left.
     *
     * Only a file the manifest lists is deleted; the exporter never removes a file it did not write.
     *
     * @throws LangExportException
     */
    private function exportLocale(
        LangSources $sources,
        ExportOptions $options,
        OutputDirectory $output,
        Manifest $manifest,
        string $locale,
    ): ExportResult {
        $parsedLocales = $this->parseLocales($sources, $options, $locale);
        $result = $this->writeLocales($output, $parsedLocales, $options->prettyPrint);
        $locales = array_values(array_diff($manifest->locales, [$locale]));
        $removed = [];

        if ($parsedLocales !== []) {
            $locales[] = $locale;
            sort($locales, SORT_STRING);
        } elseif (in_array($locale, $manifest->locales, true) && $output->delete($locale)) {
            $removed[] = $locale;
        }

        $output->writeManifest(new Manifest($locales, $manifest->watch));

        return new ExportResult($result->written, $result->unchanged, $removed, $result->warnings);
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

    /**
     * Runs an export step under an exclusive lock, so concurrent runs cannot interleave their reads and writes.
     *
     * The lock file lives in storage, never in the output directory the frontend ships.
     *
     * @param  Closure(): ExportResult  $export
     *
     * @throws LangExportException
     */
    private function locked(Closure $export): ExportResult
    {
        $path = storage_path(self::LOCK_FILE);

        try {
            $lock = (new LockableFile($path, 'c'))->getExclusiveLock(true);
        } catch (Throwable $exception) {
            throw LangExportException::unwritableFile($path, $exception);
        }

        try {
            return $export();
        } finally {
            $lock->close();
        }
    }
}
