<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Discovery\LangSources;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use AbeTwoThree\LaravelLangExport\Parsing\TranslationParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

$parse = fn (TranslationUnit $u) => (new TranslationParser(
    LangSources::fromTranslator(app('translator'), ExportOptions::fromConfig()),
    new Filesystem,
))->parse($u);

it('merges framework lines under app overrides', function () use ($parse) {
    expect($parse(new TranslationUnit('en', '*', 'auth'))->lines)->toBe([
        'failed' => 'App failed.',
        'password' => 'The provided password is incorrect.',
        'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    ]);
});

it('applies app vendor overrides to package lines', function () use ($parse) {
    expect($parse(new TranslationUnit('en', 'courier', 'mail'))->lines)
        ->toBe(['sent' => 'App override sent.', 'queued' => 'Package queued.']);
});

it('merges JSON lines with the app lang path winning', function () use ($parse) {
    expect($parse(TranslationUnit::json('en'))->lines)->toBe(['Welcome' => 'Welcome', 'Read more' => 'Read more']);
});

it('keeps strings and arrays, dropping other leaves with a warning', function () use ($parse) {
    $parsed = $parse(new TranslationUnit('en', '*', 'mixed'));

    expect($parsed->lines)->toBe(['label' => 'Label', 'list' => ['One', 'Two'], 'empty' => [], 'deep' => ['text' => 'Deep']])
        ->and($parsed->warnings)->toBe([
            'en: mixed.count skipped (int)',
            'en: mixed.closure skipped (Closure)',
            'en: mixed.missing skipped (null)',
            'en: mixed.deep.flag skipped (bool)',
        ]);
});

it('keeps the unit on the parsed result', function () use ($parse) {
    $unit = new TranslationUnit('en', '*', 'auth');

    expect($parse($unit)->unit)->toBe($unit);
});

it('drops JSON values that are not strings', function () use ($parse) {
    $dir = $this->outputPath().'/json';
    File::ensureDirectoryExists($dir);
    File::put("$dir/zz.json", '{"ok": "Ok", "nested": {"a": "b"}}');
    config()->set('lang-export.json_paths', [$dir]);

    $parsed = $parse(TranslationUnit::json('zz'));

    expect($parsed->lines)->toBe(['ok' => 'Ok'])->and($parsed->warnings)->toBe(['zz: nested skipped (array)']);
});

it('names the file when a lang file cannot be loaded', function () use ($parse) {
    $dir = $this->outputPath().'/broken';
    File::ensureDirectoryExists("$dir/en");
    File::put("$dir/en/broken.php", "<?php return ['a' => ;");
    config()->set('lang-export.paths', [$dir]);

    $parse(new TranslationUnit('en', '*', 'broken'));
})->throws(LangExportException::class, 'broken.php');

it('reports the locale, key and cause when a lang file cannot be loaded', function () use ($parse) {
    $dir = $this->outputPath().'/broken2';
    File::ensureDirectoryExists("$dir/en");
    File::put("$dir/en/broken.php", "<?php return ['a' => ;");
    config()->set('lang-export.paths', [$dir]);

    try {
        $parse(new TranslationUnit('en', '*', 'broken'));
    } catch (LangExportException $e) {
        expect($e->getMessage())->toStartWith('Could not load en broken (')
            ->and($e->getPrevious())->toBeInstanceOf(Throwable::class);

        return;
    }

    $this->fail('Expected LangExportException.');
});

it('names the lang file that returns a non-array', function (string $contents) use ($parse) {
    $dir = $this->outputPath().'/nonarray';
    File::ensureDirectoryExists("$dir/en");
    File::put("$dir/en/scalar.php", $contents);
    config()->set('lang-export.paths', [$dir]);

    expect(fn () => $parse(new TranslationUnit('en', '*', 'scalar')))->toThrow(
        LangExportException::class,
        'Could not load en scalar ('.normalizePath("$dir/en/scalar.php").'): the file must return an array.',
    );
})->with(['empty' => '', 'string' => "<?php return 'x';"]);

it('keeps the load error when an earlier file of the unit returns an array', function () use ($parse) {
    $dir = $this->outputPath().'/override';
    File::ensureDirectoryExists("$dir/en");
    File::put("$dir/en/auth.php", "<?php return ['a' => ;");
    config()->set('lang-export.paths', [$dir]);

    try {
        $parse(new TranslationUnit('en', '*', 'auth'));
    } catch (LangExportException $e) {
        expect(normalizePath($e->getMessage()))->toContain(normalizePath("$dir/en/auth.php").':')
            ->not->toContain('must return an array');

        return;
    }

    $this->fail('Expected LangExportException.');
});

it('names the file when JSON is invalid', function () use ($parse) {
    $dir = $this->outputPath().'/badjson';
    File::ensureDirectoryExists($dir);
    File::put("$dir/yy.json", '{');
    config()->set('lang-export.json_paths', [$dir]);

    $parse(TranslationUnit::json('yy'));
})->throws(LangExportException::class, 'yy.json');

it('keeps empty nested arrays', function () use ($parse) {
    $dir = $this->outputPath().'/nested';
    File::ensureDirectoryExists("$dir/en");
    File::put("$dir/en/nested.php", "<?php return ['a' => ['b' => [], 'c' => 'C']];");
    config()->set('lang-export.paths', [$dir]);

    expect($parse(new TranslationUnit('en', '*', 'nested'))->lines)->toBe(['a' => ['b' => [], 'c' => 'C']]);
});

it('builds the unreadable unit message from the unit key and the cause', function () {
    $previous = new RuntimeException('boom');
    $e = LangExportException::unreadableUnit(new TranslationUnit('es', 'courier', 'mail'), $previous);

    expect($e->getMessage())->toBe(sprintf('Could not load es courier::mail (%s:%d): boom', $previous->getFile(), $previous->getLine()))
        ->and($e->getPrevious())->toBe($previous);
});
