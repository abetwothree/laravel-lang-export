<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\workbench_path;

it('loads the workbench lang path', function () {
    expect(normalizePath(lang_path()))->toBe(normalizePath(workbench_path('lang-app')));
});

it('keeps Testbench from registering a workbench lang namespace', function () {
    expect(app('translator')->getLoader()->namespaces())->not->toHaveKey('workbench');
});

it('points the output path at one temp directory per test', function () {
    $path = $this->outputPath();

    expect(config('lang-export.output_path'))->toBe($path)
        ->and($this->outputPath())->toBe($path)
        ->and($path)->toStartWith(sys_get_temp_dir())
        ->and(is_dir($path))->toBeTrue();
});

it('leaves output path siblings for tearDown to remove', function () {
    File::ensureDirectoryExists($this->outputPath().'-leak/en');
    File::put($this->outputPath().'-leak.json', '{}');
    expect(File::isDirectory($this->outputPath().'-leak/en'))->toBeTrue();

    return $this->outputPath();
});

it('removes output path siblings after each test', function (string $previous) {
    expect(File::exists("{$previous}-leak"))->toBeFalse()
        ->and(File::exists("{$previous}-leak.json"))->toBeFalse();
})->depends('it leaves output path siblings for tearDown to remove');
