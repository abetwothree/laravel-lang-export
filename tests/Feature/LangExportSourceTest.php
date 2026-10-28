<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->live = base_path('lang-live-'.Str::random(8));
    File::ensureDirectoryExists("{$this->live}/en");
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 'A'];");
    File::put("{$this->live}/zz.json", '{"Hi": "Hi"}');
    config()->set('lang-export.paths', [$this->live]);
    $this->artisan('lang:export')->assertSuccessful();
});
afterEach(function () {
    File::deleteDirectory($this->live);
    File::deleteDirectory("{$this->live}-outside");
});
$read = fn (string $file) => json_decode(File::get(test()->outputPath()."/$file"), true);
$snapshot = function (): array {
    $files = [];

    foreach (File::files(test()->outputPath()) as $file) {
        $files[$file->getFilename()] = $file->getContents();
    }

    ksort($files);

    return $files;
};
$fresh = function (): string {
    $fresh = test()->outputPath().'/fresh';
    test()->artisan('lang:export', ['--output' => $fresh])->assertSuccessful();

    return $fresh;
};

it('patches one group into its locale file', function () use ($read) {
    $before = $read('en.json');
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 'B'];");
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])
        ->expectsOutputToContain('Wrote en.json')->assertSuccessful();
    expect($read('en.json'))->toBe([...$before, 'live' => ['a' => 'B']]);
});
it('matches a fresh full export byte for byte', function () {
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 'B', 'b' => 'C'];");
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])->assertSuccessful();
    $fresh = $this->outputPath().'/fresh';
    $this->artisan('lang:export', ['--output' => $fresh])->assertSuccessful();
    expect(File::get($this->outputPath().'/en.json'))->toBe(File::get("$fresh/en.json"));
});
it('removes a group whose file was deleted', function () use ($read) {
    File::delete("{$this->live}/en/live.php");
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])->assertSuccessful();
    expect($read('en.json'))->not->toHaveKey('live');
});
it('replaces JSON lines when a JSON file changes', function () use ($read) {
    File::put("{$this->live}/zz.json", '{"Bye": "Bye"}');
    $this->artisan('lang:export', ['--source' => "{$this->live}/zz.json"])->assertSuccessful();
    expect($read('zz.json'))->toBe(['Bye' => 'Bye']);
});
it('exports a whole new locale and adds it to the manifest', function () use ($read) {
    File::ensureDirectoryExists("{$this->live}/nl");
    File::put("{$this->live}/nl/live.php", "<?php return ['a' => 'NL'];");
    $this->artisan('lang:export', ['--source' => "{$this->live}/nl/live.php"])->assertSuccessful();
    expect($read('nl.json'))->toBe(['live' => ['a' => 'NL']])
        ->and($read('manifest.json')['locales'])->toContain('nl');
});
it('accepts a path relative to the base path', function () use ($read) {
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 'R'];");
    $this->artisan('lang:export', ['--source' => basename($this->live).'/en/live.php'])->assertSuccessful();
    expect($read('en.json')['live'])->toBe(['a' => 'R']);
});
it('skips a file the filters exclude', function () {
    $before = File::get($this->outputPath().'/en.json');
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 'X'];");
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php", '--except' => ['live']])
        ->expectsOutputToContain("Skipped {$this->live}/en/live.php: excluded by filters")->assertSuccessful();
    expect(File::get($this->outputPath().'/en.json'))->toBe($before);
});
it('skips a file inside a lang directory that is not a translation file', function (string $file) use ($snapshot) {
    $before = $snapshot();
    $this->artisan('lang:export', ['--source' => "{$this->live}/{$file}"])
        ->expectsOutputToContain("Skipped {$this->live}/{$file}: not a translation file")
        ->assertSuccessful();
    expect($snapshot())->toBe($before);
})->with(['en/readme.txt', 'en/.live.php.swp', 'vendor/ghost/en/boo.php', 'en']);
it('refuses a file outside every lang directory without loading it', function () {
    $this->artisan('lang:export', ['--source' => __FILE__])
        ->expectsOutputToContain('is not inside a registered lang directory')->assertFailed();
});
it('fails without touching output when the changed file is broken', function () {
    $before = File::get($this->outputPath().'/en.json');
    File::put("{$this->live}/en/live.php", "<?php return ['a' => ;");
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])
        ->expectsOutputToContain('live.php')->assertFailed();
    expect(File::get($this->outputPath().'/en.json'))->toBe($before);
});

it('refuses a path that climbs out of a lang directory without loading it', function () {
    $outside = "{$this->live}-outside";
    File::ensureDirectoryExists($outside);
    File::put("$outside/trap.php", "<?php file_put_contents(__DIR__.'/loaded', '1'); return [];");
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/../../".basename($outside).'/trap.php'])
        ->expectsOutputToContain('is not inside a registered lang directory')->assertFailed();
    expect(File::exists("$outside/loaded"))->toBeFalse();
});
it('only parses the changed file', function () use ($read) {
    File::put("{$this->live}/en/other.php", "<?php return ['a' => ;");
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 'B'];");
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])->assertSuccessful();
    expect($read('en.json')['live'])->toBe(['a' => 'B']);
});
it('reports Unchanged when the file did not change', function () {
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])
        ->expectsOutputToContain('Unchanged en.json')->assertSuccessful();
});
it('prints the warnings of the patched unit', function () {
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 1, 'b' => 'B'];");
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])
        ->expectsOutputToContain('en: live.a skipped (int)')->assertSuccessful();
    File::put("{$this->live}/en.json", '{"live": "Live line"}');
    $this->artisan('lang:export', ['--source' => "{$this->live}/en.json"])
        ->expectsOutputToContain('en: JSON line "live" skipped, a group has the same key')->assertSuccessful();
});
it('restores a JSON line once the group that displaced it is deleted', function () use ($fresh) {
    File::put("{$this->live}/en.json", '{"live": "Live line"}');
    $this->artisan('lang:export')->assertSuccessful();
    File::delete("{$this->live}/en/live.php");
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])->assertSuccessful();
    expect(File::get($this->outputPath().'/en.json'))->toBe(File::get($fresh().'/en.json'))
        ->and(json_decode(File::get($this->outputPath().'/en.json'), true)['live'])->toBe('Live line');
});
it('keeps a JSON line beside an empty same-named group file, matching a fresh export', function () use ($read, $fresh) {
    File::put("{$this->live}/en/hello.php", '<?php return [];');
    File::put("{$this->live}/en.json", '{"hello": "Hello line"}');
    $this->artisan('lang:export')->assertSuccessful();
    File::put("{$this->live}/en.json", '{"hello": "Hello again"}');
    $this->artisan('lang:export', ['--source' => "{$this->live}/en.json"])->assertSuccessful();
    expect(File::get($this->outputPath().'/en.json'))->toBe(File::get($fresh().'/en.json'))
        ->and($read('en.json')['hello'])->toBe('Hello again');
});
it('drops a locale whose last file was deleted', function () use ($read, $fresh) {
    File::delete("{$this->live}/zz.json");
    $this->artisan('lang:export', ['--source' => "{$this->live}/zz.json"])
        ->expectsOutputToContain('Removed zz.json')->assertSuccessful();
    expect(File::exists($this->outputPath().'/zz.json'))->toBeFalse()
        ->and($read('manifest.json'))->toBe(json_decode(File::get($fresh().'/manifest.json'), true));
});
it('re-exports a whole locale the manifest does not list', function () use ($read) {
    File::ensureDirectoryExists("{$this->live}/nl");
    File::put("{$this->live}/nl/live.php", "<?php return ['a' => 'NL'];");
    File::put($this->outputPath().'/nl.json', '{"stale": "x"}');
    File::put($this->outputPath().'/es.json', '{"untouched": "x"}');
    $this->artisan('lang:export', ['--source' => "{$this->live}/nl/live.php"])->assertSuccessful();
    expect($read('nl.json'))->toBe(['live' => ['a' => 'NL']])
        ->and($read('es.json'))->toBe(['untouched' => 'x'])
        ->and($read('manifest.json')['locales'])->toBe(['de', 'en', 'es', 'nl', 'zz']);
});
it('re-exports a whole locale whose file is unreadable', function () use ($fresh) {
    File::put($this->outputPath().'/en.json', 'not json');
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])->assertSuccessful();
    expect(File::get($this->outputPath().'/en.json'))->toBe(File::get($fresh().'/en.json'));
});
it('runs a full export when there is no manifest', function () use ($read) {
    File::delete($this->outputPath().'/manifest.json');
    File::delete($this->outputPath().'/es.json');
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/live.php"])->assertSuccessful();
    expect(File::exists($this->outputPath().'/es.json'))->toBeTrue()
        ->and($read('manifest.json')['locales'])->toBe(['de', 'en', 'es', 'zz']);
});
it('re-exports a file of a namespace registered through ..', function () use ($read) {
    $package = "{$this->live}-outside/pkg";
    File::ensureDirectoryExists("$package/src");
    File::ensureDirectoryExists("$package/lang/en");
    File::put("$package/lang/en/x.php", "<?php return ['a' => 'A'];");
    app('translator')->addNamespace('hop', "$package/src/../lang");
    $this->artisan('lang:export')->assertSuccessful();
    File::put("$package/lang/en/x.php", "<?php return ['a' => 'B'];");
    $this->artisan('lang:export', ['--source' => "$package/lang/en/x.php"])->assertSuccessful();
    expect($read('en.json')['hop::x'])->toBe(['a' => 'B'])
        ->and($read('manifest.json')['watch'])->toContain(basename($this->live).'-outside/pkg/lang');
});
it('leaves a stray locale file the manifest does not list', function () use ($read) {
    File::put($this->outputPath().'/qq.json', '{"mine": "x"}');
    $this->artisan('lang:export', ['--source' => "{$this->live}/qq.json"])->assertSuccessful();
    expect(File::get($this->outputPath().'/qq.json'))->toBe('{"mine": "x"}')
        ->and($read('manifest.json')['locales'])->not->toContain('qq');
});
it('fails without touching output when a changed JSON file is malformed', function () use ($snapshot) {
    $before = $snapshot();
    File::put("{$this->live}/zz.json", '{');
    $this->artisan('lang:export', ['--source' => "{$this->live}/zz.json"])
        ->expectsOutputToContain('zz.json')->assertFailed();
    expect($snapshot())->toBe($before);
});

it('names an empty lang file an editor just created', function () use ($snapshot) {
    $before = $snapshot();
    File::put("{$this->live}/en/new.php", '');
    $this->artisan('lang:export', ['--source' => "{$this->live}/en/new.php"])
        ->expectsOutputToContain('('.normalizePath("{$this->live}/en/new.php").'): the file must return an array.')
        ->assertFailed();
    expect($snapshot())->toBe($before);
});
