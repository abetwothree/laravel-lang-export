<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Discovery\UnitFilter;

$filterWith = fn (array $locales = [], array $only = [], array $except = []): UnitFilter => new UnitFilter(ExportOptions::fromConfig()->with(locales: $locales, only: $only, except: $except));

it('allows every unit with default options', function () use ($filterWith) {
    $filter = $filterWith();

    expect($filter->allows(new TranslationUnit('en', '*', 'auth')))->toBeTrue()
        ->and($filter->allows(new TranslationUnit('en', 'courier', 'mail')))->toBeTrue()
        ->and($filter->allows(TranslationUnit::json('en')))->toBeTrue()
        ->and($filter->allowsLocale('en'))->toBeTrue();
});

it('keeps only groups matching an only pattern', function () use ($filterWith) {
    $filter = $filterWith(only: ['auth', 'courier::*']);

    expect($filter->allows(new TranslationUnit('en', '*', 'auth')))->toBeTrue()
        ->and($filter->allows(new TranslationUnit('en', 'courier', 'mail')))->toBeTrue()
        ->and($filter->allows(new TranslationUnit('en', '*', 'validation')))->toBeFalse();
});

it('drops groups matching an except pattern even when only matches', function () use ($filterWith) {
    $filter = $filterWith(only: ['*'], except: ['admin/*']);

    expect($filter->allows(new TranslationUnit('en', '*', 'admin/users')))->toBeFalse()
        ->and($filter->allows(new TranslationUnit('en', '*', 'auth')))->toBeTrue();
});

it('governs JSON units by the json flag alone', function () use ($filterWith) {
    $json = TranslationUnit::json('en');

    expect($filterWith(only: ['auth'])->allows($json))->toBeTrue()
        ->and($filterWith(except: ['*'])->allows($json))->toBeTrue();

    config()->set('lang-export.json', false);

    expect($filterWith()->allows($json))->toBeFalse();
});

it('drops units of locales outside the locales list', function () use ($filterWith) {
    $filter = $filterWith(locales: ['es']);

    expect($filter->allows(new TranslationUnit('en', '*', 'auth')))->toBeFalse()
        ->and($filter->allows(new TranslationUnit('es', '*', 'auth')))->toBeTrue()
        ->and($filter->allows(TranslationUnit::json('en')))->toBeFalse()
        ->and($filter->allowsLocale('en'))->toBeFalse()
        ->and($filter->allowsLocale('es'))->toBeTrue();
});
