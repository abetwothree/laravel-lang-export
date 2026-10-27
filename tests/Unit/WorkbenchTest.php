<?php

declare(strict_types=1);

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
