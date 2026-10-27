<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport;

use AbeTwoThree\LaravelLangExport\Commands\LangExportCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelLangExportServiceProvider extends PackageServiceProvider
{
    /**
     * Registers the package name, its config file and the export command.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-lang-export')
            ->hasConfigFile('lang-export')
            ->hasCommand(LangExportCommand::class);
    }
}
