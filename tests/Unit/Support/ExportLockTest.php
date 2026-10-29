<?php

declare(strict_types=1);

use AbeTwoThree\LangExport\Exceptions\LangExportException;
use AbeTwoThree\LangExport\Support\ExportLock;
use AbeTwoThree\LangExport\Tests\Support\NoLockStream;

it('holds an exclusive lock until released', function () {
    $path = $this->outputPath().'/test.lock';
    $lock = ExportLock::acquire($path, 1.0);
    $other = fopen($path, 'c');
    expect($lock->held)->toBeTrue()->and(flock($other, LOCK_EX | LOCK_NB))->toBeFalse();
    $lock->release();
    expect(flock($other, LOCK_EX | LOCK_NB))->toBeTrue();
    fclose($other);
});

it('times out while another handle holds the lock', function () {
    $path = $this->outputPath().'/test.lock';
    $other = fopen($path, 'c');
    flock($other, LOCK_EX);
    $start = microtime(true);

    try {
        expect(fn () => ExportLock::acquire($path, 0.3))
            ->toThrow(LangExportException::class, "Timed out after 0.3 seconds waiting for the export lock [{$path}].")
            ->and(microtime(true) - $start)->toBeGreaterThanOrEqual(0.3);
    } finally {
        fclose($other);
    }
});

it('waits for a lock another process releases', function () {
    $path = $this->outputPath().'/test.lock';
    $code = '$h = fopen($argv[1], "c"); flock($h, LOCK_EX); echo "locked\n"; usleep(300000);';
    $child = proc_open([PHP_BINARY, '-r', $code, $path], [1 => ['pipe', 'w']], $pipes);
    fgets($pipes[1]);
    $lock = ExportLock::acquire($path, 5.0);
    expect($lock->held)->toBeTrue();
    $lock->release();
    proc_close($child);
});

it('returns an unheld lock at once when the filesystem cannot lock', function () {
    NoLockStream::register();
    $start = microtime(true);
    $lock = ExportLock::acquire('nolock://storage/test.lock', 1.0);
    expect($lock->held)->toBeFalse()->and(microtime(true) - $start)->toBeLessThan(0.5);
    $lock->release();
});

it('fails when the lock file cannot be opened', function () {
    $path = $this->outputPath().'/missing/dir/test.lock';
    expect(fn () => ExportLock::acquire($path, 1.0))->toThrow(LangExportException::class, "Could not write [{$path}]");
});
