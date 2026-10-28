<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\ExportOptions;
use AbeTwoThree\LaravelLangExport\LangExporter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->storage = $this->outputPath().'-storage';
    app()->useStoragePath($this->storage);
    $this->live = base_path('lang-live-'.Str::random(8));
    File::ensureDirectoryExists("{$this->live}/en");
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 'A'];");
    config()->set('lang-export.paths', [$this->live]);
});
afterEach(function () {
    File::deleteDirectory($this->live);
});

// Records, at every read and write inside the output directory, whether a second handle could take the export lock.
$probing = fn (string $output): Filesystem => new class($output) extends Filesystem
{
    /** @var list<string> */
    public array $probes = [];

    public function __construct(private string $output) {}

    public function get($path, $lock = false)
    {
        $this->probe('read', $path);

        return parent::get($path, $lock);
    }

    public function put($path, $contents, $lock = false)
    {
        $this->probe('write', $path);

        return parent::put($path, $contents, $lock);
    }

    private function probe(string $kind, string $path): void
    {
        if (! str_starts_with($path, $this->output)) {
            return;
        }

        $lock = storage_path('framework/lang-export.lock');
        File::ensureDirectoryExists(dirname($lock));
        $handle = fopen($lock, 'c');
        $state = flock($handle, LOCK_EX | LOCK_NB) ? 'free' : 'held';
        fclose($handle);
        $this->probes[] = "{$state} {$kind} ".preg_replace('/\.[0-9a-f]+\.tmp$/', '', basename($path));
    }
};

it('holds the export lock through every read and write of a full export', function () use ($probing) {
    app(LangExporter::class)->exportAll(ExportOptions::fromConfig());
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 'B'];");
    $files = $probing($this->outputPath());

    (new LangExporter(app('translator'), $files))->exportAll(ExportOptions::fromConfig());

    expect($files->probes)->toContain('held read manifest.json', 'held read en.json', 'held write en.json')
        ->each->toStartWith('held ');
});

it('holds the export lock through every read and write of a source re-export', function () use ($probing) {
    app(LangExporter::class)->exportAll(ExportOptions::fromConfig());
    File::put("{$this->live}/en/live.php", "<?php return ['a' => 'B'];");
    $files = $probing($this->outputPath());

    (new LangExporter(app('translator'), $files))->exportFile("{$this->live}/en/live.php", ExportOptions::fromConfig());

    expect($files->probes)->toContain('held read manifest.json', 'held read en.json', 'held write en.json')
        ->each->toStartWith('held ');
});

it('keeps its lock file under storage/framework, creating the directory', function () {
    expect(File::exists($this->storage))->toBeFalse();

    $this->artisan('lang:export')->assertSuccessful();

    expect(File::isFile(storage_path('framework/lang-export.lock')))->toBeTrue()
        ->and(glob($this->outputPath().'/*.lock'))->toBe([]);
});

it('fails without writing when the lock file cannot be created', function () {
    File::put($this->storage, 'not a directory');

    $this->artisan('lang:export')
        ->expectsOutputToContain('Could not write ['.storage_path('framework/lang-export.lock').']: ')
        ->assertFailed();

    expect(File::files($this->outputPath()))->toBeEmpty();
});
