<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport;

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\ExportResult;
use AbeTwoThree\LaravelLangExport\Data\Manifest;
use AbeTwoThree\LaravelLangExport\Data\ParsedUnit;
use AbeTwoThree\LaravelLangExport\Data\SkipReason;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;
use AbeTwoThree\LaravelLangExport\Discovery\UnitFilter;
use AbeTwoThree\LaravelLangExport\Discovery\UnitFinder;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use AbeTwoThree\LaravelLangExport\Output\LocaleDocument;
use AbeTwoThree\LaravelLangExport\Output\OutputDirectory;
use AbeTwoThree\LaravelLangExport\Parsing\TranslationParser;
use AbeTwoThree\LaravelLangExport\Support\Path;
use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\LockableFile;
use Illuminate\Translation\Translator;
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
     * the others' files; a locale filter that matches no lang files fails instead of emptying it.
     *
     * @throws LangExportException
     */
    public function exportAll(ExportOptions $options): ExportResult
    {
        $sources = $this->sources($options);

        return $this->locked(fn (): ExportResult => $this->exportSources($sources, $options));
    }

    /**
     * Re-exports the unit one changed, added or deleted lang file belongs to, writing what a full export would.
     *
     * Used by a file watcher on every change. The unit is patched into its locale's existing file; a missing manifest
     * falls back to a full export, and a missing, unlisted or unreadable locale file to a whole-locale export. A file
     * inside a lang directory that is not a unit is skipped; one outside every lang directory fails.
     *
     * @throws LangExportException
     */
    public function exportFile(string $path, ExportOptions $options): ExportResult
    {
        $sources = $this->sources($options);
        $finder = new UnitFinder($sources, $this->files);
        $unit = $finder->forFile($path);

        // A watcher reports every file in a lang directory, so a readme or editor swap file there is not an error.
        if ($unit === null && $finder->isInsideLangDirectory($path)) {
            return new ExportResult(skipped: SkipReason::NotTranslationFile);
        }

        if ($unit === null) {
            throw LangExportException::notTranslationFile($path);
        }

        if (! (new UnitFilter($options))->allows($unit)) {
            return new ExportResult(skipped: SkipReason::Filtered);
        }

        return $this->locked(fn (): ExportResult => $this->exportUnit($sources, $options, $unit));
    }

    /**
     * Snapshots the lang sources, refusing an output path that is the base path or one of their directories.
     *
     * @throws LangExportException
     */
    private function sources(ExportOptions $options): LangSources
    {
        if (Path::normalize($options->outputPath) === Path::normalize(base_path())) {
            throw LangExportException::outputIsBasePath($options->outputPath);
        }

        $sources = LangSources::fromTranslator($this->translator, $options);

        if ($sources->hasDirectory($options->outputPath)) {
            throw LangExportException::outputIsLangDirectory($options->outputPath);
        }

        return $sources;
    }

    /**
     * Parses every unit and writes the locale files and manifest; the caller holds the export lock.
     *
     * @throws LangExportException
     */
    private function exportSources(LangSources $sources, ExportOptions $options): ExportResult
    {
        $parsedLocales = $this->parseLocales($sources, $options);

        // An export of no locales would delete every exported file over what is most likely a typo.
        if ($options->locales !== null && $parsedLocales === []) {
            throw LangExportException::noLangFiles($options->locales);
        }

        $output = new OutputDirectory($this->files, $options->outputPath);
        $result = $this->writeLocales($output, $parsedLocales, $options->prettyPrint);
        $locales = array_keys($parsedLocales);
        $removed = $this->removeDropped($output, $locales);
        $output->writeManifest(new Manifest($locales, $sources->watchDirectories()));
        $warnings = [];

        foreach (array_unique(array_diff($options->locales ?? [], $locales)) as $locale) {
            $warnings[] = "{$locale}: no lang files found";
        }

        return new ExportResult($result->written, $result->unchanged, $removed, [...$warnings, ...$result->warnings]);
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

        $parsed = (new TranslationParser($sources, $this->files))->parse($unit);

        // Dropping a key can uncover a JSON line it displaced or leave the locale empty; only a full re-read knows.
        if ($parsed->lines === []) {
            return $this->exportLocale($sources, $options, $output, $manifest, $unit->locale);
        }

        $document->put($parsed);

        return $this->writeDocuments($output, [$document], $options->prettyPrint);
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
        $parser = new TranslationParser($sources, $this->files);
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
     * Assembles every locale's document from its parsed units and writes them.
     *
     * @param  ParsedLocales  $parsedLocales
     *
     * @throws LangExportException
     */
    private function writeLocales(OutputDirectory $output, array $parsedLocales, bool $pretty): ExportResult
    {
        $documents = [];

        foreach ($parsedLocales as $locale => $units) {
            $document = new LocaleDocument($locale);

            foreach ($units as $parsed) {
                $document->put($parsed);
            }

            $documents[] = $document;
        }

        return $this->writeDocuments($output, $documents, $pretty);
    }

    /**
     * Encodes every document before writing any, so an unencodable line writes nothing.
     *
     * @param  list<LocaleDocument>  $documents
     *
     * @throws LangExportException
     */
    private function writeDocuments(OutputDirectory $output, array $documents, bool $pretty): ExportResult
    {
        $encoded = array_map(static fn (LocaleDocument $document): string => $document->toJson($pretty), $documents);
        $written = [];
        $unchanged = [];
        $warnings = [];

        foreach ($documents as $index => $document) {
            if ($output->writeJson($document->locale, $encoded[$index])) {
                $written[] = $document->locale;
            } else {
                $unchanged[] = $document->locale;
            }

            array_push($warnings, ...$document->warnings());
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
