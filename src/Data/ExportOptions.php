<?php

declare(strict_types=1);

namespace AbeTwoThree\LangExport\Data;

use AbeTwoThree\LangExport\Exceptions\LangExportException;
use AbeTwoThree\LangExport\Support\BasePath;
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
     * Creates options from already validated values; fromConfig() is the config boundary.
     *
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
     * This is the config boundary; downstream classes trust the result. Relative paths resolve against the base path.
     *
     * @throws LangExportException
     */
    public static function fromConfig(): self
    {
        $locales = Config::get('lang-export.locales');

        // Laravel's typed getters and this class's validators both throw InvalidArgumentException.
        try {
            return new self(
                outputPath: BasePath::resolve(Config::string('lang-export.output_path')),
                locales: $locales === null || $locales === [] ? null : self::stringList($locales, 'locales'),
                only: self::stringList(Config::array('lang-export.only'), 'only'),
                except: self::stringList(Config::array('lang-export.except'), 'except'),
                json: Config::boolean('lang-export.json'),
                paths: array_map(BasePath::resolve(...), self::stringList(Config::array('lang-export.paths'), 'paths')),
                jsonPaths: array_map(
                    BasePath::resolve(...),
                    self::stringList(Config::array('lang-export.json_paths'), 'json_paths'),
                ),
                namespaces: array_map(
                    BasePath::resolve(...),
                    self::namespaceMap(Config::array('lang-export.namespaces')),
                ),
                prettyPrint: Config::boolean('lang-export.pretty_print'),
            );
        } catch (InvalidArgumentException $exception) {
            throw LangExportException::invalidConfig($exception);
        }
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
