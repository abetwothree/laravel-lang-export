<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport\Tests\Support;

/**
 * A `nolock://` stream wrapper standing in for a filesystem without file locking: every path is a directory, every
 * file opens, and no lock is ever granted.
 */
final class NoLockStream
{
    public const string SCHEME = 'nolock';

    /** @var resource|null */
    public $context;

    /**
     * Registers the wrapper once per process.
     */
    public static function register(): void
    {
        if (! in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }
    }

    /**
     * Opens any path.
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    /**
     * Refuses every lock, as a filesystem without flock does.
     */
    public function stream_lock(int $operation): bool
    {
        return false;
    }

    /**
     * Closes nothing.
     */
    public function stream_close(): void {}

    /**
     * Reports every path as a directory, so ensureDirectoryExists() creates nothing.
     *
     * @return array{mode: int}
     */
    public function url_stat(string $path, int $flags): array
    {
        return ['mode' => 0040777];
    }
}
