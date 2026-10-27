<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Support\BasePath;

it('joins a relative path to the base path', function (string $path) {
    expect(BasePath::resolve($path))->toBe(base_path($path));
})->with(['lang', 'resources/js/lang', './lang', 'lang\\en']);

it('keeps an absolute path on either OS as given', function (string $path) {
    expect(BasePath::resolve($path))->toBe($path);
})->with(['/var/www/lang', '\\var\\lang', 'C:\\app\\lang', 'C:/app/lang', 'd:/app/lang']);
