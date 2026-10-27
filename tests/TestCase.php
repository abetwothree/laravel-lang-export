<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Tests;

use AbeTwoThree\LaravelLangExport\LaravelLangExportServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use WithWorkbench;

    private ?string $outputPath = null;

    /**
     * Removes this test's output directory.
     */
    protected function tearDown(): void
    {
        if ($this->outputPath !== null) {
            (new Filesystem)->deleteDirectory($this->outputPath);
            $this->outputPath = null;
        }

        parent::tearDown();
    }

    /**
     * Returns this test's export directory, creating it on first use.
     */
    public function outputPath(): string
    {
        if ($this->outputPath === null) {
            $this->outputPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lang-export-'.Str::random(16);
            (new Filesystem)->ensureDirectoryExists($this->outputPath);
        }

        return $this->outputPath;
    }

    /**
     * Writes exports to this test's output directory.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('lang-export.output_path', $this->outputPath());
    }

    /**
     * Registers the testbench.yaml providers and the package provider under test.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LaravelLangExportServiceProvider::class,
        ];
    }
}
