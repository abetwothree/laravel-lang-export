<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelLangExportServiceProvider extends PackageServiceProvider
{
    /**
     * Registers the package name and its config file.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-lang-export')
            ->hasConfigFile('lang-export');
    }
}
