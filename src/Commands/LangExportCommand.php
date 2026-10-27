<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Commands;

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\ExportResult;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use AbeTwoThree\LaravelLangExport\LangExporter;
use AbeTwoThree\LaravelLangExport\Support\BasePath;
use Illuminate\Console\Command;

use function is_array;
use function is_string;

/**
 * Exports the app's translations as one JSON file per locale for `@tolki/lang`.
 *
 * @phpstan-import-type StringList from ExportOptions
 */
final class LangExportCommand extends Command
{
    /** @var string */
    protected $signature = 'lang:export
        {--locale=* : Only export these locales}
        {--only=* : Only export groups matching these patterns}
        {--except=* : Skip groups matching these patterns}
        {--output= : Directory to write to, absolute or relative to the base path}
        {--source= : Path of one changed translation file to re-export, absolute or relative to the base path}';

    /** @var string */
    protected $description = 'Export translations as one JSON file per locale';

    /** @var string */
    protected $help = <<<'HELP'
        Examples:
          php artisan lang:export
          php artisan lang:export --locale=en --locale=es
          php artisan lang:export --only=auth --only='courier::*' --except='admin/*'
          php artisan lang:export --output=resources/js/lang
          php artisan lang:export --source=lang/en/auth.php
        HELP;

    /**
     * Runs a full export, or re-exports the one `--source` file, and reports what it wrote, left unchanged or removed.
     */
    public function handle(LangExporter $exporter): int
    {
        $source = $this->stringOption('source');

        try {
            $options = $this->exportOptions();
            $result = $source === null ? $exporter->exportAll($options) : $exporter->exportFile($source, $options);
        } catch (LangExportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result->skipped) {
            $this->line("Skipped {$source}: excluded by filters");
        }

        $this->report($result);

        return self::SUCCESS;
    }

    /**
     * Builds the export options from config, with any given flag replacing its config value.
     */
    protected function exportOptions(): ExportOptions
    {
        $output = $this->stringOption('output');

        return ExportOptions::fromConfig()->with(
            outputPath: $output === null ? null : BasePath::resolve($output),
            locales: $this->listOption('locale'),
            only: $this->listOption('only'),
            except: $this->listOption('except'),
        );
    }

    /**
     * Prints the written, unchanged and removed locale files, then every warning.
     */
    protected function report(ExportResult $result): void
    {
        foreach ($result->written as $locale) {
            $this->line("Wrote {$locale}.json");
        }

        foreach ($result->unchanged as $locale) {
            $this->line("Unchanged {$locale}.json");
        }

        foreach ($result->removed as $locale) {
            $this->line("Removed {$locale}.json");
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }
    }

    /**
     * Reads a repeatable option as a list of its string values.
     *
     * @return StringList
     */
    private function listOption(string $name): array
    {
        $values = $this->option($name);
        $strings = [];

        foreach (is_array($values) ? $values : [] as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    /**
     * Reads an option as a string, or null when it is missing or empty.
     */
    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
