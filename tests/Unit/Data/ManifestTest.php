<?php

declare(strict_types=1);

use AbeTwoThree\LangExport\Data\Manifest;

it('round trips through its stored shape', function () {
    $manifest = new Manifest(['en'], ['lang'], 'abc');
    expect($manifest->toArray())->toBe(['version' => 1, 'locales' => ['en'], 'watch' => ['lang'], 'fingerprint' => 'abc'])
        ->and(Manifest::fromArray($manifest->toArray()))->toEqual($manifest);
});

it('rejects data that is not a valid manifest', function (mixed $data) {
    expect(Manifest::fromArray($data))->toBeNull();
})->with([
    'not an array' => ['manifest'],
    'no version' => [['locales' => [], 'watch' => [], 'fingerprint' => 'abc']],
    'other version' => [['version' => 2, 'locales' => [], 'watch' => [], 'fingerprint' => 'abc']],
    'string version' => [['version' => '1', 'locales' => [], 'watch' => [], 'fingerprint' => 'abc']],
    'keyed locales' => [['version' => 1, 'locales' => ['a' => 'en'], 'watch' => [], 'fingerprint' => 'abc']],
    'non-string locale' => [['version' => 1, 'locales' => [5], 'watch' => [], 'fingerprint' => 'abc']],
    'missing watch' => [['version' => 1, 'locales' => [], 'fingerprint' => 'abc']],
    'non-string watch' => [['version' => 1, 'locales' => [], 'watch' => [null], 'fingerprint' => 'abc']],
    'missing fingerprint' => [['version' => 1, 'locales' => [], 'watch' => []]],
    'non-string fingerprint' => [['version' => 1, 'locales' => [], 'watch' => [], 'fingerprint' => 5]],
]);
