<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\Manifest;
use AbeTwoThree\LaravelLangExport\Data\ParsedUnit;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
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
