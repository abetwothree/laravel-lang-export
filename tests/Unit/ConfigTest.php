<?php

declare(strict_types=1);

it('ships the default config', function () {
    expect(require __DIR__.'/../../config/lang-export.php')->toBe([
        'output_path' => resource_path('js/lang'),
        'locales' => null,
        'only' => [],
        'except' => [],
        'json' => true,
        'paths' => [],
        'json_paths' => [],
        'namespaces' => [],
        'pretty_print' => false,
    ]);
});

it('merges the config under lang-export', function () {
    expect(config('lang-export.json'))->toBeTrue()->and(config('lang-export.pretty_print'))->toBeFalse();
});
