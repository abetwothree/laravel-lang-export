<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Orchestra\Testbench\workbench_path;

$read = fn (string $file) => json_decode(File::get(test()->outputPath()."/$file"), true);
$snapshot = function (): array {
    $files = [];

    foreach (File::files(test()->outputPath()) as $file) {
        $files[$file->getFilename()] = $file->getContents();
    }

    ksort($files);

    return $files;
};

it('exports every locale and a manifest', function () use ($read) {
    $this->artisan('lang:export')->expectsOutputToContain('Wrote en.json')->assertSuccessful();
    expect(array_keys($read('en.json')))->toBe([
        'Read more', 'Welcome', 'admin/users', 'auth', 'blog::posts', 'courier::mail',
        'mixed', 'pagination', 'passwords', 'validation',
    ])
        ->and($read('en.json')['courier::mail'])->toBe(['sent' => 'App override sent.', 'queued' => 'Package queued.'])
        ->and($read('es.json'))->toBe([
            'Welcome' => 'Bienvenido',
            'auth' => ['failed' => 'Credenciales incorrectas.'],
            'courier::mail' => ['sent' => 'Enviado.'],
        ])
        ->and($read('manifest.json')['locales'])->toBe(['de', 'en', 'es'])
        ->and($read('manifest.json')['watch'])->toContain(normalizePath(workbench_path('lang-app')));
});

it('prints warnings for skipped lines', function () {
    $this->artisan('lang:export')
        ->expectsOutputToContain('en: mixed.count skipped (int)')
        ->expectsOutputToContain('de: JSON line "pagination" skipped, a group has the same key')
        ->assertSuccessful();
});

it('limits locales with --locale', function () {
    $this->artisan('lang:export', ['--locale' => ['es']])->assertSuccessful();
    expect(File::files($this->outputPath()))->toHaveCount(2); // es.json, manifest.json
});

it('fails without touching the output when no requested locale has lang files', function (?array $config, array $flags) use ($snapshot) {
    $this->artisan('lang:export')->assertSuccessful();
    $before = $snapshot();
    config()->set('lang-export.locales', $config);

    $this->artisan('lang:export', $flags)
        ->expectsOutputToContain('No lang files found for the requested locales [EN, xx].')
        ->assertFailed();

    expect($snapshot())->toBe($before);
})->with([
    'flag' => [null, ['--locale' => ['EN', 'xx']]],
    'config' => [['EN', 'xx'], []],
]);

it('warns about a requested locale without lang files and exports the others', function () use ($read) {
    $this->artisan('lang:export', ['--locale' => ['es', 'xx']])
        ->expectsOutputToContain('xx: no lang files found')
        ->doesntExpectOutputToContain('es: no lang files found')
        ->assertSuccessful();

    expect($read('manifest.json')['locales'])->toBe(['es']);
});

it('exports every locale when config locales is an empty list', function () use ($read) {
    config()->set('lang-export.locales', []);

    $this->artisan('lang:export')->assertSuccessful();

    expect($read('manifest.json')['locales'])->toBe(['de', 'en', 'es']);
});

it('filters groups with --only and --except', function () use ($read) {
    $this->artisan('lang:export', ['--only' => ['auth', 'courier::*'], '--except' => ['courier::*']])->assertSuccessful();
    expect(array_keys($read('en.json')))->toBe(['Read more', 'Welcome', 'auth']);
});

it('writes an empty object for a locale whose units are all filtered out', function () use ($read) {
    config()->set('lang-export.json', false);
    $this->artisan('lang:export', ['--only' => ['auth']])->assertSuccessful();
    expect(File::get($this->outputPath().'/de.json'))->toBe("{}\n")
        ->and($read('manifest.json')['locales'])->toBe(['de', 'en', 'es']);
});

it('removes locale files a previous export wrote that are no longer exported', function () {
    $this->artisan('lang:export')->assertSuccessful();
    File::put($this->outputPath().'/keep.txt', 'mine');
    $this->artisan('lang:export', ['--locale' => ['es']])->expectsOutputToContain('Removed en.json')->assertSuccessful();
    expect(File::exists($this->outputPath().'/en.json'))->toBeFalse()
        ->and(File::exists($this->outputPath().'/de.json'))->toBeFalse()
        ->and(File::exists($this->outputPath().'/keep.txt'))->toBeTrue();
});

it('only removes locale files that exist inside the output directory', function () {
    $outside = $this->outputPath().'-outside.json';
    File::put($outside, '{}');
    File::put($this->outputPath().'/manifest.json', json_encode([
        'version' => 1,
        'locales' => ['../'.basename($this->outputPath()).'-outside', 'zz'],
        'watch' => [],
    ]));
    $this->artisan('lang:export')->doesntExpectOutputToContain('Removed')->assertSuccessful();
    expect(File::exists($outside))->toBeTrue();
});

it('writes to --output', function () {
    $other = $this->outputPath().'/other';
    $this->artisan('lang:export', ['--output' => $other])->assertSuccessful();
    expect(File::exists("$other/en.json"))->toBeTrue();
});

it('resolves a relative --output against the base path', function () {
    $relative = 'lang-export-'.Str::random(8);

    try {
        $this->artisan('lang:export', ['--output' => $relative])->assertSuccessful();
        expect(File::exists(base_path("$relative/en.json")))->toBeTrue();
    } finally {
        File::deleteDirectory(base_path($relative));
        File::deleteDirectory(getcwd()."/$relative");
    }
});

it('resolves a relative config output_path against the base path', function () {
    $relative = 'lang-export-'.Str::random(8);
    config()->set('lang-export.output_path', $relative);

    try {
        $this->artisan('lang:export')->assertSuccessful();
        expect(File::exists(base_path("$relative/en.json")))->toBeTrue();
    } finally {
        File::deleteDirectory(base_path($relative));
        File::deleteDirectory(getcwd()."/$relative");
    }
});

it('reads a lang path configured relative to the base path, in a full export and with --source', function () use ($read) {
    $relative = 'lang-rel-'.Str::random(8);
    File::ensureDirectoryExists(base_path("$relative/en"));
    File::put(base_path("$relative/en/rel.php"), "<?php return ['a' => 'A'];");
    config()->set('lang-export.paths', [$relative]);

    try {
        $this->artisan('lang:export')->assertSuccessful();
        $full = $read('en.json')['rel'] ?? null;
        File::put(base_path("$relative/en/rel.php"), "<?php return ['a' => 'B'];");
        $this->artisan('lang:export', ['--source' => base_path("$relative/en/rel.php")])->assertSuccessful();
        expect($full)->toBe(['a' => 'A'])->and($read('en.json')['rel'])->toBe(['a' => 'B']);
    } finally {
        File::deleteDirectory(base_path($relative));
    }
});

it('refuses an output path that is a registered lang directory', function (string $key, bool $source) {
    $live = base_path('lang-live-'.Str::random(8));
    File::ensureDirectoryExists("$live/en");
    File::put("$live/en/live.php", "<?php return ['a' => 'A'];");
    File::put("$live/en.json", '{"Hi": "Hi"}');
    config()->set("lang-export.{$key}", $key === 'namespaces' ? ['live' => $live] : [$live]);
    $listing = fn () => collect(File::allFiles($live))->mapWithKeys(fn ($file) => [$file->getRelativePathname() => $file->getContents()])->sort()->all();
    $before = $listing();

    try {
        $this->artisan('lang:export', ['--output' => "$live/", ...($source ? ['--source' => "$live/en/live.php"] : [])])
            ->expectsOutputToContain("The output path [$live/] is a registered lang directory")
            ->assertFailed();
        expect($listing())->toBe($before);
    } finally {
        File::deleteDirectory($live);
    }
})->with(['paths', 'json_paths', 'namespaces'])->with(['full export' => false, '--source' => true]);

it('treats an empty --output as not given', function () {
    $this->artisan('lang:export', ['--output' => ''])->assertSuccessful();
    expect(File::exists($this->outputPath().'/en.json'))->toBeTrue();
});

it('fails without writing when a lang file cannot be loaded', function () {
    $dir = $this->outputPath().'-broken';
    File::ensureDirectoryExists("$dir/en");
    File::put("$dir/en/broken.php", "<?php return ['a' => ;");
    config()->set('lang-export.paths', [$dir]);
    $this->artisan('lang:export')->expectsOutputToContain('broken.php')->assertFailed();
    expect(File::exists($this->outputPath().'/en.json'))->toBeFalse()
        ->and(File::files($this->outputPath()))->toBeEmpty();
});

it('fails without writing when a line cannot be encoded', function () {
    $dir = $this->outputPath().'-unencodable';
    File::ensureDirectoryExists("$dir/en");
    File::put("$dir/en/bad.php", "<?php return ['bad' => \"\\xB1\\x31\"];");
    config()->set('lang-export.paths', [$dir]);
    $this->artisan('lang:export')->expectsOutputToContain('Could not encode the en locale file')->assertFailed();
    expect(File::files($this->outputPath()))->toBeEmpty();
});

it('writes byte-identical files on a second run', function () {
    $this->artisan('lang:export')->assertSuccessful();
    $first = File::get($this->outputPath().'/en.json');
    $this->artisan('lang:export')->expectsOutputToContain('Unchanged en.json')->assertSuccessful();
    expect(File::get($this->outputPath().'/en.json'))->toBe($first);
});

it('exports configured extra paths', function () use ($read) {
    config()->set('lang-export.paths', [workbench_path('lang-extra')]);
    $this->artisan('lang:export')->assertSuccessful();
    expect($read('fr.json'))->toBe(['extra' => ['hello' => 'Bonjour']]);
});

it('publishes the workbench sample output', function () {
    File::deleteDirectory(workbench_path('resources/js/lang'));
    config()->set('lang-export.pretty_print', true);
    $this->artisan('lang:export', ['--output' => workbench_path('resources/js/lang')])->assertSuccessful();
    expect(File::exists(workbench_path('resources/js/lang/en.json')))->toBeTrue()
        ->and(File::exists(workbench_path('resources/js/lang/manifest.json')))->toBeTrue()
        ->and(File::get(workbench_path('resources/js/lang/es.json')))->toStartWith("{\n    \"Welcome\"");
});

it('prints malformed config as an error', function () {
    config()->set('lang-export.locales', 'en');
    $this->artisan('lang:export')
        ->expectsOutputToContain('Config lang-export.locales must be a list of strings.')->assertFailed();
});

it('refuses an output path that is the base path', function (array $config, array $flags) {
    config()->set($config);

    try {
        $this->artisan('lang:export', $flags)->expectsOutputToContain('is the base path.')->assertFailed();
        expect(File::exists(base_path('manifest.json')))->toBeFalse();
    } finally {
        // The red run writes into the Testbench skeleton; leave it clean.
        File::delete(array_map(base_path(...), ['de.json', 'en.json', 'es.json', 'manifest.json']));
    }
})->with([
    'empty config' => [['lang-export.output_path' => ''], []],
    'dot config' => [['lang-export.output_path' => '.'], []],
    'dot flag' => [[], ['--output' => '.']],
]);

it('prints written, unchanged and removed files, then warnings, in that order', function () {
    $live = $this->outputPath().'-live';
    File::ensureDirectoryExists("{$live}/en");
    File::put("{$live}/en/live.php", "<?php return ['a' => 'A'];");
    config()->set('lang-export.paths', [$live]);
    Artisan::call('lang:export');
    File::put("{$live}/en/live.php", "<?php return ['a' => 'B'];");

    Artisan::call('lang:export', ['--locale' => ['en', 'es', 'xx']]);

    expect(array_values(array_filter(preg_split('/\R/', Artisan::output()))))->toBe([
        'Wrote en.json', 'Unchanged es.json', 'Removed de.json', 'xx: no lang files found',
        'en: mixed.count skipped (int)', 'en: mixed.closure skipped (Closure)',
        'en: mixed.missing skipped (null)', 'en: mixed.deep.flag skipped (bool)',
    ]);
});
