<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Data;

/**
 * The list of exported locales and the paths a watcher should observe, stored beside the locale files.
 */
final readonly class Manifest
{
    public const int VERSION = 1;

    /**
     * Creates a manifest from the exported locales and the directories to watch.
     *
     * @param  list<string>  $locales
     * @param  list<string>  $watch
     */
    public function __construct(
        public array $locales,
        public array $watch,
    ) {}

    /**
     * Reads a manifest from decoded JSON, or returns null when the data is not a valid manifest.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ($data['version'] ?? null) !== self::VERSION) {
            return null;
        }

        $locales = $data['locales'] ?? null;
        $watch = $data['watch'] ?? null;

        if (! self::isStringList($locales) || ! self::isStringList($watch)) {
            return null;
        }

        return new self($locales, $watch);
    }

    /**
     * Returns the manifest in its stored shape.
     *
     * @return array{version: int, locales: list<string>, watch: list<string>}
     */
    public function toArray(): array
    {
        return ['version' => self::VERSION, 'locales' => $this->locales, 'watch' => $this->watch];
    }

    /**
     * Tells whether a value is a list holding only strings.
     *
     * @phpstan-assert-if-true list<string> $value
     */
    private static function isStringList(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
