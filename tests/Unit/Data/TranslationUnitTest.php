<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;

it('keys an app group by its name', function () {
    expect((new TranslationUnit('en', '*', 'admin/users'))->key())->toBe('admin/users');
});

it('keys a package group as namespace::group', function () {
    expect((new TranslationUnit('en', 'courier', 'mail'))->key())->toBe('courier::mail');
});

it('keys JSON lines as a wildcard', function () {
    $unit = TranslationUnit::json('en');

    expect($unit->key())->toBe('*')
        ->and($unit->isJson())->toBeTrue()
        ->and($unit->locale)->toBe('en');
});

it('does not treat a group as JSON', function () {
    expect((new TranslationUnit('en', '*', 'auth'))->isJson())->toBeFalse()
        ->and((new TranslationUnit('en', 'courier', '*'))->isJson())->toBeFalse();
});
