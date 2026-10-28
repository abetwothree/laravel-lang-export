<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Support;

use AbeTwoThree\LaravelLangExport\Exceptions\LangExportException;
use ErrorException;

/**
 * An exclusive lock on a file, so concurrent exports queue instead of interleaving their reads and writes.
 */
final class ExportLock
{
    private const int POLL_MICROSECONDS = 100_000;

    /**
     * Wraps an open lock file; acquire() opens and locks it.
     *
     * @param  resource  $handle
     */
    private function __construct(
        private $handle,
        public readonly bool $held,
    ) {}

    /**
     * Opens the lock file and waits up to the timeout for an exclusive lock.
     *
     * A filesystem that cannot lock at all, as some network and container mounts cannot, yields an unheld lock
     * instead of failing every export.
     *
     * @throws LangExportException
     */
    public static function acquire(string $path, float $timeout): self
    {
        try {
            $handle = fopen($path, 'c');
        } catch (ErrorException $exception) {
            throw LangExportException::unwritableFile($path, $exception);
        }

        if ($handle === false) {
            throw LangExportException::unwritableFile($path);
        }

        $deadline = microtime(true) + $timeout;

        while (! flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
            if ($wouldBlock !== 1) {
                return new self($handle, false);
            }

            if (microtime(true) >= $deadline) {
                fclose($handle);

                throw LangExportException::lockTimeout($path, $timeout);
            }

            usleep(self::POLL_MICROSECONDS);
        }

        return new self($handle, true);
    }

    /**
     * Releases the lock and closes the file.
     */
    public function release(): void
    {
        if ($this->held) {
            flock($this->handle, LOCK_UN);
        }

        fclose($this->handle);
    }
}
