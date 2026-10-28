<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;
use AbeTwoThree\LaravelLangExport\Support\ExportFingerprint;
use AbeTwoThree\LaravelLangExport\Support\PackageVersion;
use Composer\InstalledVersions;

use function Orchestra\Testbench\workbench_path;

$fingerprint = fn (ExportOptions $options, string $version = '1.0.0'): string => ExportFingerprint::compute(
    $options, LangSources::fromTranslator(app('translator'), $options), $version,
);

it('is stable and ignores list order and the output path', function () use ($fingerprint) {
    $base = ExportOptions::fromConfig();
    expect($fingerprint($base->with(locales: ['en', 'es'], only: ['auth', 'admin/*'])))
        ->toBe($fingerprint($base->with(locales: ['es', 'en', 'en'], only: ['admin/*', 'auth'])))
        ->and($fingerprint($base->with(outputPath: '/elsewhere')))->toBe($fingerprint($base))
        ->and($fingerprint($base))->toBe($fingerprint($base));
});

it('changes with each option that shapes the locale files', function (string $key, mixed $value) use ($fingerprint) {
    $before = $fingerprint(ExportOptions::fromConfig());
    config()->set("lang-export.{$key}", $value);
    expect($fingerprint(ExportOptions::fromConfig()))->not->toBe($before);
})->with([
    'locales' => ['locales', ['en']],
    'only' => ['only', ['auth']],
    'except' => ['except', ['auth']],
    'json' => ['json', false],
    'pretty_print' => ['pretty_print', true],
    'paths' => ['paths', ['/extra/lang']],
    'json_paths' => ['json_paths', ['/extra/json']],
    'namespaces' => ['namespaces', ['late' => '/late/lang']],
]);

it('changes when a package registers a lang namespace', function () use ($fingerprint) {
    $before = $fingerprint(ExportOptions::fromConfig());
    app('translator')->addNamespace('late', workbench_path('lang-extra'));
    expect($fingerprint(ExportOptions::fromConfig()))->not->toBe($before);
});

it('changes with the package version', function () use ($fingerprint) {
    $options = ExportOptions::fromConfig();
    expect($fingerprint($options, '1.0.0'))->not->toBe($fingerprint($options, '1.1.0'));
});

it('reads the installed package version', function () {
    expect(PackageVersion::current())->toBe(InstalledVersions::getPrettyVersion(PackageVersion::PACKAGE));
});
