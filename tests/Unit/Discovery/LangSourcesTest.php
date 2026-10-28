<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\TranslationServiceProvider;

use function Orchestra\Testbench\workbench_path;

$sources = fn () => LangSources::fromTranslator(app('translator'), ExportOptions::fromConfig());

it('includes the framework and app lang paths', function () use ($sources) {
    $framework = dirname((new ReflectionClass(TranslationServiceProvider::class))->getFileName()).'/lang';
    expect($sources()->groupPaths())->toContain(normalizePath($framework))
        ->toContain(normalizePath(workbench_path('lang-app')));
});

it('sees namespaces registered when the translator resolves', function () use ($sources) {
    expect($sources()->namespaces())->toMatchArray([
        'courier' => normalizePath(workbench_path('packages/courier/lang')),
        'blog' => normalizePath(workbench_path('app-modules/blog/resources/lang')),
    ]);
});

it('sees JSON paths registered by modules', function () use ($sources) {
    expect($sources()->jsonPaths())->toContain(normalizePath(workbench_path('app-modules/blog/resources/lang')));
});

it('registers configured paths, JSON paths and namespaces with the loader', function () use ($sources) {
    config()->set('lang-export.paths', [workbench_path('lang-extra')]);
    config()->set('lang-export.json_paths', [workbench_path('lang-extra')]);
    config()->set('lang-export.namespaces', ['shop' => workbench_path('lang-extra')]);
    expect($sources()->groupPaths())->toContain(normalizePath(workbench_path('lang-extra')))
        ->and($sources()->jsonPaths())->toContain(normalizePath(workbench_path('lang-extra')))
        ->and(app('translator')->getLoader()->namespaces())->toHaveKey('shop');
});

it('keeps a namespace the loader already has', function () use ($sources) {
    config()->set('lang-export.namespaces', ['courier' => workbench_path('lang-extra')]);
    expect($sources()->namespaces()['courier'])->toBe(normalizePath(workbench_path('packages/courier/lang')));
});

it('exposes the translator loader', function () use ($sources) {
    expect($sources()->loader())->toBe(app('translator')->getLoader());
});

it('registers a configured path once across repeated builds', function () use ($sources) {
    config()->set('lang-export.paths', [workbench_path('lang-extra')]);
    config()->set('lang-export.json_paths', [workbench_path('lang-extra')]);
    $sources();
    $sources();
    $loader = app('translator')->getLoader();
    $loaderPaths = array_map(normalizePath(...), $loader->paths());
    $loaderJsonPaths = array_map(normalizePath(...), $loader->jsonPaths());
    expect(array_count_values($loaderPaths)[normalizePath(workbench_path('lang-extra'))])->toBe(1)
        ->and(array_count_values($loaderJsonPaths)[normalizePath(workbench_path('lang-extra'))])->toBe(1);
});

it('matches configured paths whatever their slashes', function () use ($sources) {
    $extra = workbench_path('lang-extra');
    config()->set('lang-export.paths', [$extra.'/', str_replace('/', '\\', $extra)]);
    $groupPaths = $sources()->groupPaths();
    $loaderPaths = array_map(normalizePath(...), app('translator')->getLoader()->paths());
    expect($groupPaths)->toContain(normalizePath($extra))
        ->and(array_count_values($loaderPaths)[normalizePath($extra)])->toBe(1);
});

it('lists existing watch directories, relative inside the base path', function () use ($sources) {
    $inside = base_path('lang-watch-'.Str::random(8));
    File::ensureDirectoryExists($inside);
    config()->set('lang-export.paths', [$inside, '/does/not/exist']);

    try {
        $watch = $sources()->watchDirectories();
    } finally {
        File::deleteDirectory($inside);
    }

    expect($watch)->toContain(basename($inside))
        ->toContain(normalizePath(workbench_path('lang-app')))
        ->not->toContain('/does/not/exist')
        ->and($watch)->toBe(array_values(array_unique($watch)));
    $sorted = $watch;
    sort($sorted);
    expect($watch)->toBe($sorted);
});

it('canonicalizes a directory registered through ..', function () use ($sources) {
    app('translator')->addNamespace('hop', workbench_path('packages/courier/../../lang-extra/'));
    $canonical = normalizePath(workbench_path('lang-extra'));
    expect($sources()->namespaces()['hop'])->toBe($canonical)
        ->and($sources()->watchDirectories())->toContain($canonical)
        ->and(implode("\n", $sources()->watchDirectories()))->not->toContain('..');
});

it('collapses . and .. lexically', function (string $path, string $canonical) use ($sources) {
    app('translator')->getLoader()->addPath($path);
    expect($sources()->groupPaths())->toContain($canonical);
})->with([
    ['/a/b/../c', '/a/c'],
    ['/a/./b/', '/a/b'],
    ['/a//b', '/a/b'],
    ['/../a', '/a'],
    ['C:\\x\\..\\y', 'C:/y'],
    ['C:/../y', 'C:/y'],
    ['//server/share/../z', '//server/z'],
    ['rel/../../w', '../w'],
]);

it('rejects a loader that does not extend FileLoader', function () use ($sources) {
    app()->forgetInstance('translator');
    app()->instance('translation.loader', new ArrayLoader);
    $sources();
})->throws(
    LangExportException::class,
    'The translation loader ['.ArrayLoader::class.'] must extend Illuminate\\Translation\\FileLoader.',
);
