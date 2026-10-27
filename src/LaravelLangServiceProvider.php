<?php

namespace AbeTwoThree\LaravelLang;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use AbeTwoThree\LaravelLang\Commands\LaravelLangCommand;

class LaravelLangServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-lang')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_lang_table')
            ->hasCommand(LaravelLangCommand::class);
    }
}
