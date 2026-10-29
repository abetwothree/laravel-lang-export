<?php

declare(strict_types=1);

use AbeTwoThree\LangExport\Data\ExportOptions;
use AbeTwoThree\LangExport\Exceptions\LangExportException;

it('reads every option from config', function () {
    config()->set('lang-export.locales', ['en']);
    config()->set('lang-export.namespaces', ['blog' => '/tmp/blog']);
    config()->set('lang-export.paths', ['/tmp/a']);
    config()->set('lang-export.json_paths', ['/tmp/b']);
    config()->set('lang-export.pretty_print', true);

    $options = ExportOptions::fromConfig();

    expect($options->locales)->toBe(['en'])
        ->and($options->namespaces)->toBe(['blog' => '/tmp/blog'])
        ->and($options->outputPath)->toBe(config('lang-export.output_path'))
        ->and($options->json)->toBeTrue()
        ->and($options->paths)->toBe(['/tmp/a'])
        ->and($options->jsonPaths)->toBe(['/tmp/b'])
        ->and($options->prettyPrint)->toBeTrue();
});

it('lets command flags replace config lists', function () {
    config()->set('lang-export.except', ['validation']);

    $options = ExportOptions::fromConfig()->with(outputPath: '/tmp/out', locales: ['es'], only: ['auth']);

    expect($options->outputPath)->toBe('/tmp/out')
        ->and($options->locales)->toBe(['es'])
        ->and($options->only)->toBe(['auth'])
        ->and($options->except)->toBe(['validation']);
});

it('keeps config values for null or empty arguments', function () {
    config()->set('lang-export.locales', ['en']);
    config()->set('lang-export.only', ['auth']);
    config()->set('lang-export.except', ['validation']);

    $options = ExportOptions::fromConfig()->with();

    expect($options->outputPath)->toBe(config('lang-export.output_path'))
        ->and($options->locales)->toBe(['en'])
        ->and($options->only)->toBe(['auth'])
        ->and($options->except)->toBe(['validation']);
});

it('keeps the options not passed to with()', function () {
    config()->set('lang-export.json', false);
    config()->set('lang-export.pretty_print', true);
    config()->set('lang-export.namespaces', ['blog' => '/tmp/blog']);

    $options = ExportOptions::fromConfig()->with(locales: ['es']);

    expect($options->json)->toBeFalse()
        ->and($options->prettyPrint)->toBeTrue()
        ->and($options->namespaces)->toBe(['blog' => '/tmp/blog']);
});

it('rejects a locales value that is not a list or null', function () {
    config()->set('lang-export.locales', 'en');

    ExportOptions::fromConfig();
})->throws(LangExportException::class, 'Config lang-export.locales must be a list of strings.');

it('rejects locales that are not strings', function () {
    config()->set('lang-export.locales', ['en', 5]);

    ExportOptions::fromConfig();
})->throws(LangExportException::class, 'Config lang-export.locales must be a list of strings.');

it('rejects namespaces that are not a string map', function (array $namespaces) {
    config()->set('lang-export.namespaces', $namespaces);

    ExportOptions::fromConfig();
})->with([
    'list' => [['/tmp/blog']],
    'non-string value' => [['blog' => 5]],
])->throws(LangExportException::class, 'Config lang-export.namespaces must map string names to string paths.');

it('rejects a keyed locales map', function () {
    config()->set('lang-export.locales', ['primary' => 'en']);

    ExportOptions::fromConfig();
})->throws(LangExportException::class, 'Config lang-export.locales must be a list of strings.');

it('resolves relative config paths against the base path', function () {
    config()->set('lang-export.output_path', 'resources/js/lang');
    config()->set('lang-export.paths', ['lang-a', '/abs/a']);
    config()->set('lang-export.json_paths', ['lang-b', 'C:\\abs\\b']);
    config()->set('lang-export.namespaces', ['blog' => 'modules/blog/lang', 'shop' => 'D:/shop/lang']);

    $options = ExportOptions::fromConfig();

    expect($options->outputPath)->toBe(base_path('resources/js/lang'))
        ->and($options->paths)->toBe([base_path('lang-a'), '/abs/a'])
        ->and($options->jsonPaths)->toBe([base_path('lang-b'), 'C:\\abs\\b'])
        ->and($options->namespaces)->toBe(['blog' => base_path('modules/blog/lang'), 'shop' => 'D:/shop/lang']);
});

it('treats an empty locales list as no locale filter', function () {
    config()->set('lang-export.locales', []);

    expect(ExportOptions::fromConfig()->locales)->toBeNull();
});

it('reports a config value of the wrong type as a LangExportException', function () {
    config()->set('lang-export.json', 'yes');
    ExportOptions::fromConfig();
})->throws(LangExportException::class, 'lang-export.json');
