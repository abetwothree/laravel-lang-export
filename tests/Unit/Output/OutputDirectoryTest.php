<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\Manifest;
use AbeTwoThree\LaravelLangExport\Data\ParsedUnit;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use AbeTwoThree\LaravelLangExport\Output\LocaleDocument;
use AbeTwoThree\LaravelLangExport\Output\OutputDirectory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

$doc = function (): LocaleDocument {
    $doc = new LocaleDocument('en');
    $doc->put(new ParsedUnit(new TranslationUnit('en', '*', 'auth'), ['failed' => 'F'], []));

    return $doc;
};

it('writes a locale file and reports it', function () use ($doc) {
    $out = $this->outputPath();

    expect((new OutputDirectory(new Filesystem, $out))->write($doc(), false))->toBeTrue()
        ->and(File::get("{$out}/en.json"))->toBe($doc()->toJson(false)."\n");
});

it('creates a missing output directory', function () use ($doc) {
    $out = $this->outputPath().'/nested/lang';

    expect((new OutputDirectory(new Filesystem, $out))->write($doc(), false))->toBeTrue()
        ->and(File::exists("{$out}/en.json"))->toBeTrue();
});

it('skips writing identical content', function () use ($doc) {
    $out = $this->outputPath();
    $dir = new OutputDirectory(new Filesystem, $out);
    $dir->write($doc(), false);
    touch("{$out}/en.json", time() - 3600);
    $mtime = filemtime("{$out}/en.json");
    clearstatcache();

    expect($dir->write($doc(), false))->toBeFalse();
    clearstatcache();

    expect(filemtime("{$out}/en.json"))->toBe($mtime)
        ->and($dir->write($doc(), true))->toBeTrue();
});

it('leaves no temp file behind', function () use ($doc) {
    $out = $this->outputPath();
    $dir = new OutputDirectory(new Filesystem, $out);
    $dir->write($doc(), false);
    $dir->writeManifest(new Manifest(['en'], ['lang']));

    expect(glob("{$out}/*.tmp"))->toBe([]);
});

it('returns null for a missing or invalid locale file', function () {
    $out = $this->outputPath();
    $dir = new OutputDirectory(new Filesystem, $out);
    File::put("{$out}/en.json", 'garbage');

    expect($dir->read('xx'))->toBeNull()
        ->and($dir->read('en'))->toBeNull();
});

it('reads back a written locale file', function () use ($doc) {
    $dir = new OutputDirectory(new Filesystem, $this->outputPath());
    $dir->write($doc(), false);

    expect($dir->read('en')?->toJson(false))->toBe($doc()->toJson(false));
});

it('round trips the manifest', function () {
    $out = $this->outputPath();
    $dir = new OutputDirectory(new Filesystem, $out);

    expect($dir->readManifest())->toBeNull()
        ->and($dir->writeManifest(new Manifest(['en'], ['lang'])))->toBeTrue()
        ->and(File::get("{$out}/manifest.json"))->toBe('{"version":1,"locales":["en"],"watch":["lang"]}'."\n")
        ->and($dir->readManifest())->toEqual(new Manifest(['en'], ['lang']))
        ->and($dir->writeManifest(new Manifest(['en'], ['lang'])))->toBeFalse();

    File::put("{$out}/manifest.json", '{"version":2,"locales":[],"watch":[]}');
    expect($dir->readManifest())->toBeNull();
});

it('deletes a locale file', function () use ($doc) {
    $out = $this->outputPath();
    $dir = new OutputDirectory(new Filesystem, $out);
    $dir->write($doc(), false);

    expect($dir->delete('en'))->toBeTrue()
        ->and(File::exists("{$out}/en.json"))->toBeFalse()
        ->and($dir->delete('en'))->toBeFalse();
});

it('throws and leaves no temp file when a file cannot be written', function (string $target) use ($doc) {
    $out = $this->outputPath();
    mkdir("{$out}/{$target}");
    $dir = new OutputDirectory(new Filesystem, $out);
    $write = fn () => $target === 'manifest.json'
        ? $dir->writeManifest(new Manifest(['en'], ['lang']))
        : $dir->write($doc(), false);

    expect($write)->toThrow(LangExportException::class, "Could not write [{$out}/{$target}]")
        ->and(glob("{$out}/*.tmp"))->toBe([]);
})->with(['en.json', 'manifest.json']);

it('chains the OS error that stopped a write and shows its reason', function () use ($doc) {
    $out = $this->outputPath();
    mkdir("{$out}/en.json");

    try {
        (new OutputDirectory(new Filesystem, $out))->write($doc(), false);
    } catch (LangExportException $exception) {
    }

    expect($exception->getPrevious())->toBeInstanceOf(ErrorException::class)
        ->and($exception->getMessage())->toBe("Could not write [{$out}/en.json]: {$exception->getPrevious()->getMessage()}");
});

it('writes through a temp file unique to each write', function () use ($doc) {
    $out = $this->outputPath();
    $files = new class extends Filesystem
    {
        /** @var list<string> */
        public array $puts = [];

        public function put($path, $contents, $lock = false)
        {
            $this->puts[] = $path;

            return parent::put($path, $contents, $lock);
        }
    };
    $dir = new OutputDirectory($files, $out);
    $dir->write($doc(), false);
    $dir->write($doc(), true);

    expect($files->puts)->toHaveCount(2)
        ->and($files->puts[0])->not->toBe($files->puts[1])
        ->and($files->puts)->each->toStartWith("{$out}/en.json.")->toEndWith('.tmp')
        ->and(glob("{$out}/*.tmp"))->toBe([]);
});
