<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport;

use AbeTwoThree\LangExport\Commands\LangExportCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LangExportServiceProvider extends PackageServiceProvider
{
    /**
     * Registers the package name, its config file and the export command.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('lang-export')
            ->hasConfigFile('lang-export')
            ->hasCommand(LangExportCommand::class);
    }
}
