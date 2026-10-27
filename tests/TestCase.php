<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Tests;

use AbeTwoThree\LaravelLangExport\LaravelLangExportServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Registers the package provider under test.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelLangExportServiceProvider::class,
        ];
    }
}
