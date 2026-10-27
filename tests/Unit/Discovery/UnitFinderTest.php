<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;
use AbeTwoThree\LaravelLangExport\Discovery\UnitFinder;
use Illuminate\Filesystem\Filesystem;

use function Orchestra\Testbench\workbench_path;

$finder = fn () => new UnitFinder(LangSources::fromTranslator(app('translator'), ExportOptions::fromConfig()), new Filesystem);
$ids = fn (array $units) => array_map(fn (?TranslationUnit $u) => $u === null ? null : $u->locale.':'.$u->key(), $units);

it('finds every locale and group across lang paths', function () use ($finder, $ids) {
    expect($ids($finder()->all()))->toBe([
        'de:*', 'de:pagination',
        'en:*', 'en:admin/users', 'en:auth', 'en:blog::posts', 'en:courier::mail',
        'en:mixed', 'en:pagination', 'en:passwords', 'en:validation',
        'es:*', 'es:auth', 'es:courier::mail',
    ]);
});

it('maps an app group file', function () use ($finder, $ids) {
    expect($ids([$finder()->forFile(workbench_path('lang-app/en/admin/users.php'))]))->toBe(['en:admin/users']);
});

it('maps a package override to its namespace', function () use ($finder, $ids) {
    expect($ids([$finder()->forFile(workbench_path('lang-app/vendor/courier/en/mail.php'))]))->toBe(['en:courier::mail']);
});

it('maps a package file to its namespace', function () use ($finder, $ids) {
    expect($ids([$finder()->forFile(workbench_path('packages/courier/lang/es/mail.php'))]))->toBe(['es:courier::mail']);
});

it('maps a JSON file in a module JSON path', function () use ($finder, $ids) {
    expect($ids([$finder()->forFile(workbench_path('app-modules/blog/resources/lang/en.json'))]))->toBe(['en:*']);
});

it('maps a JSON file in a group path', function () use ($finder, $ids) {
    expect($ids([$finder()->forFile(workbench_path('lang-app/es.json'))]))->toBe(['es:*']);
});

it('returns null for a group file with no name', function () use ($finder) {
    expect($finder()->forFile(workbench_path('lang-app/en/.php')))->toBeNull();
});

it('maps a deleted file by its path', function () use ($finder, $ids) {
    expect($ids([$finder()->forFile(workbench_path('lang-app/en/gone.php'))]))->toBe(['en:gone']);
});

it('accepts backslash separators', function () use ($finder, $ids) {
    $path = str_replace('/', '\\', workbench_path('lang-app/en/admin/users.php'));
    expect($ids([$finder()->forFile($path)]))->toBe(['en:admin/users']);
});

it('resolves a relative path against the base path', function () use ($finder, $ids) {
    config()->set('lang-export.paths', [base_path('lang-relative')]);
    expect($ids([$finder()->forFile('lang-relative/en/auth.php')]))->toBe(['en:auth']);
});

it('returns null for a path that climbs with ..', function (string $path) use ($finder) {
    expect($finder()->forFile(workbench_path($path)))->toBeNull();
})->with(['lang-app/en/../../evil.php', 'lang-app/../evil/en/x.php', 'lang-app/en/../en/auth.php']);

it('ignores . segments', function () use ($finder, $ids) {
    expect($ids([$finder()->forFile(workbench_path('./lang-app/./en/./auth.php'))]))->toBe(['en:auth']);
});

it('returns null outside every lang directory', function () use ($finder) {
    expect($finder()->forFile(__FILE__))->toBeNull();
});

it('returns null for an unregistered vendor namespace', function () use ($finder) {
    expect($finder()->forFile(workbench_path('lang-app/vendor/ghost/en/boo.php')))->toBeNull();
});

it('returns null for a non-PHP file in a locale directory', function () use ($finder) {
    expect($finder()->forFile(workbench_path('lang-app/en/readme.txt')))->toBeNull();
});

it('returns null for a JSON file nested below a lang path', function () use ($finder) {
    expect($finder()->forFile(workbench_path('lang-app/en/x.json')))->toBeNull();
});

it('returns null for a group file directly in a lang path', function () use ($finder) {
    expect($finder()->forFile(workbench_path('lang-app/auth.php')))->toBeNull();
});
