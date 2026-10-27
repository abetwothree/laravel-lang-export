<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelLangExport\Data;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Validated export settings read from config, optionally overridden by command flags.
 *
 * @phpstan-type StringList = list<string>
 * @phpstan-type NamespaceMap = array<string, string>
 */
final readonly class ExportOptions
{
    /**
     * @param  StringList|null  $locales
     * @param  StringList  $only
     * @param  StringList  $except
     * @param  StringList  $paths
     * @param  StringList  $jsonPaths
     * @param  NamespaceMap  $namespaces
     */
    public function __construct(
        public string $outputPath,
        public ?array $locales,
        public array $only,
        public array $except,
        public bool $json,
        public array $paths,
        public array $jsonPaths,
        public array $namespaces,
        public bool $prettyPrint,
    ) {}

    /**
     * Builds the options from the `lang-export` config, rejecting malformed values.
     *
     * This is the config boundary; downstream classes trust the result.
     *
     * @throws InvalidArgumentException
     */
    public static function fromConfig(): self
    {
        $locales = Config::get('lang-export.locales');

        return new self(
            outputPath: Config::string('lang-export.output_path'),
            locales: $locales === null ? null : self::stringList($locales, 'locales'),
            only: self::stringList(Config::array('lang-export.only'), 'only'),
            except: self::stringList(Config::array('lang-export.except'), 'except'),
            json: Config::boolean('lang-export.json'),
            paths: self::stringList(Config::array('lang-export.paths'), 'paths'),
            jsonPaths: self::stringList(Config::array('lang-export.json_paths'), 'json_paths'),
            namespaces: self::namespaceMap(Config::array('lang-export.namespaces')),
            prettyPrint: Config::boolean('lang-export.pretty_print'),
        );
    }

    /**
     * Returns a copy where each non-null, non-empty argument replaces the matching option.
     *
     * @param  StringList  $locales
     * @param  StringList  $only
     * @param  StringList  $except
     */
    public function with(
        ?string $outputPath = null,
        array $locales = [],
        array $only = [],
        array $except = [],
    ): self {
        return new self(
            outputPath: $outputPath ?? $this->outputPath,
            locales: $locales !== [] ? $locales : $this->locales,
            only: $only !== [] ? $only : $this->only,
            except: $except !== [] ? $except : $this->except,
            json: $this->json,
            paths: $this->paths,
            jsonPaths: $this->jsonPaths,
            namespaces: $this->namespaces,
            prettyPrint: $this->prettyPrint,
        );
    }

    /**
     * Asserts a config value is a list of strings.
     *
     * @return StringList
     *
     * @throws InvalidArgumentException
     */
    private static function stringList(mixed $value, string $key): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException("Config lang-export.{$key} must be a list of strings.");
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException("Config lang-export.{$key} must be a list of strings.");
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * Asserts a config value maps namespace names to directory strings.
     *
     * @param  array<array-key, mixed>  $value
     * @return NamespaceMap
     *
     * @throws InvalidArgumentException
     */
    private static function namespaceMap(array $value): array
    {
        $map = [];

        foreach ($value as $namespace => $directory) {
            if (! is_string($namespace) || ! is_string($directory)) {
                throw new InvalidArgumentException('Config lang-export.namespaces must map string names to string paths.');
            }

            $map[$namespace] = $directory;
        }

        return $map;
    }
}
