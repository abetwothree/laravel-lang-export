<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Commands;

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\ExportResult;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use AbeTwoThree\LaravelLangExport\LangExporter;
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
        {--output= : Directory to write to}';

    /** @var string */
    protected $description = 'Export translations as one JSON file per locale';

    /** @var string */
    protected $help = <<<'HELP'
        Examples:
          php artisan lang:export
          php artisan lang:export --locale=en --locale=es
          php artisan lang:export --only=auth --only='courier::*' --except='admin/*'
          php artisan lang:export --output=resources/js/lang
        HELP;

    /**
     * Runs a full export and reports each file it wrote, left unchanged or removed.
     */
    public function handle(LangExporter $exporter): int
    {
        try {
            $this->report($exporter->exportAll($this->exportOptions()));
        } catch (LangExportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Builds the export options from config, with any given flag replacing its config value.
     */
    protected function exportOptions(): ExportOptions
    {
        $output = $this->option('output');

        return ExportOptions::fromConfig()->with(
            outputPath: is_string($output) && $output !== '' ? $output : null,
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
}
