<?php

declare(strict_types=1);

use AbeTwoThree\LaravelLangExport\Data\ParsedUnit;
use AbeTwoThree\LaravelLangExport\Data\TranslationUnit;
use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use AbeTwoThree\LaravelLangExport\Output\LocaleDocument;

$group = fn (string $key, array $lines, string $locale = 'en') => new ParsedUnit(
    str_contains($key, '::') ? new TranslationUnit($locale, ...explode('::', $key)) : new TranslationUnit($locale, '*', $key),
    $lines, [],
);
$json = fn (array $lines, string $locale = 'en') => new ParsedUnit(TranslationUnit::json($locale), $lines, []);

it('encodes lines as a JSON object with sorted top-level keys', function () use ($group, $json) {
    $doc = new LocaleDocument('en');
    $doc->put($group('auth', ['failed' => 'F']));
    $doc->put($json(['Welcome' => 'W']));

    expect($doc->toJson(false))->toBe('{"Welcome":"W","auth":{"failed":"F"}}');
});

it('does not reorder the document when encoding', function () use ($group) {
    $doc = new LocaleDocument('en');
    $doc->put($group('b', ['x' => 'X']));
    $doc->put($group('a', ['y' => 'Y']));
    $doc->toJson(false);

    $ref = new ReflectionProperty($doc, 'lines');

    expect(array_keys($ref->getValue($doc)))->toBe(['b', 'a']);
});

it('encodes an empty locale as an object', function () {
    expect((new LocaleDocument('en'))->toJson(false))->toBe('{}');
});

it('keeps numeric JSON keys as object keys', function () use ($json) {
    $doc = new LocaleDocument('de');
    $doc->put($json(['0' => 'Zero'], 'de'));

    expect($doc->toJson(false))->toBe('{"0":"Zero"}');
});

it('keeps a group over a JSON line with the same key and warns', function () use ($group, $json) {
    foreach ([true, false] as $jsonFirst) {
        $doc = new LocaleDocument('de');
        $units = [$json(['pagination' => 'Collides'], 'de'), $group('pagination', ['next' => 'Weiter'], 'de')];

        foreach ($jsonFirst ? $units : array_reverse($units) as $unit) {
            $doc->put($unit);
        }

        expect($doc->toJson(false))->toBe('{"pagination":{"next":"Weiter"}}')
            ->and($doc->warnings())->toBe(['de: JSON line "pagination" skipped, a group has the same key']);
    }
});

it('keeps a JSON line when a group with the same key is empty, without a warning', function () use ($group, $json) {
    foreach ([true, false] as $jsonFirst) {
        $doc = new LocaleDocument('de');
        $units = [$json(['pagination' => 'Line'], 'de'), $group('pagination', [], 'de')];

        foreach ($jsonFirst ? $units : array_reverse($units) as $unit) {
            $doc->put($unit);
        }

        expect($doc->toJson(false))->toBe('{"pagination":"Line"}')
            ->and($doc->warnings())->toBe([]);
    }
});

it('removes a group whose lines are empty', function () use ($group) {
    $doc = new LocaleDocument('en', ['auth' => ['failed' => 'F']]);
    $doc->put($group('auth', []));

    expect($doc->toJson(false))->toBe('{}');
});

it('replaces every JSON line when JSON lines are put', function () use ($json) {
    $doc = new LocaleDocument('en', ['A' => 'a', 'B' => 'b', 'auth' => ['failed' => 'F']]);
    $doc->put($json(['C' => 'c']));

    expect($doc->toJson(false))->toBe('{"C":"c","auth":{"failed":"F"}}');
});

it('leaves unicode and slashes unescaped', function () use ($json) {
    $doc = new LocaleDocument('fr');
    $doc->put($json(['Café' => 'Café / Ü'], 'fr'));

    expect($doc->toJson(false))->toBe('{"Café":"Café / Ü"}');
});

it('pretty prints when asked', function () use ($group) {
    $doc = new LocaleDocument('en');
    $doc->put($group('auth', ['failed' => 'F']));

    expect($doc->toJson(true))->toBe("{\n    \"auth\": {\n        \"failed\": \"F\"\n    }\n}");
});

it('reads back what it encodes', function () use ($group) {
    $doc = new LocaleDocument('en');
    $doc->put($group('courier::mail', ['sent' => 'S']));

    expect(LocaleDocument::fromJson('en', $doc->toJson(false))?->toJson(false))->toBe($doc->toJson(false))
        ->and(LocaleDocument::fromJson('en', '["list"]'))->toBeNull()
        ->and(LocaleDocument::fromJson('en', 'garbage'))->toBeNull()
        ->and(LocaleDocument::fromJson('en', '{}')?->warnings())->toBe([]);
});

it('names the locale when a line cannot be encoded', function () {
    try {
        (new LocaleDocument('en', ['bad' => "\xB1\x31"]))->toJson(false);
    } catch (LangExportException $e) {
        expect($e->getMessage())->toBe('Could not encode the en locale file: Malformed UTF-8 characters, possibly incorrectly encoded')
            ->and($e->getPrevious())->toBeInstanceOf(JsonException::class);

        return;
    }

    $this->fail('Expected a LangExportException.');
});

it('rejects a read-back file holding values other than strings and objects', function (string $json) {
    expect(LocaleDocument::fromJson('en', $json))->toBeNull();
})->with([
    'number line' => ['{"Hi": 5}'],
    'null leaf' => ['{"auth": {"failed": null}}'],
    'deep bool leaf' => ['{"auth": {"a": {"b": true}}}'],
]);

it('reads back nested groups of strings, empty ones included', function () {
    expect(LocaleDocument::fromJson('en', '{"Hi": "Hi", "auth": {"a": {"b": "B"}, "c": []}}')?->toJson(false))
        ->toBe('{"Hi":"Hi","auth":{"a":{"b":"B"},"c":[]}}');
});

it('warns about a non-string JSON value as skipped, not as a collision', function () {
    $doc = new LocaleDocument('en');
    $doc->put(new ParsedUnit(TranslationUnit::json('en'), ['Hi' => ['x' => 'y'], 'Bye' => 'Bye'], []));
    expect($doc->warnings())->toBe(['en: Hi skipped (array)'])
        ->and($doc->toJson(false))->toBe('{"Bye":"Bye"}');
});

it('returns the warnings of every unit put, each before its collisions', function () {
    $doc = new LocaleDocument('en');
    $doc->put(new ParsedUnit(TranslationUnit::json('en'), ['auth' => 'Auth'], ['en: x skipped (int)']));
    $doc->put(new ParsedUnit(new TranslationUnit('en', '*', 'auth'), ['a' => 'A'], ['en: auth.y skipped (null)']));
    expect($doc->warnings())->toBe([
        'en: x skipped (int)',
        'en: auth.y skipped (null)',
        'en: JSON line "auth" skipped, a group has the same key',
    ]);
});
