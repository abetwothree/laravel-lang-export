# ADR: Load translations through Laravel's FileLoader, not a PHP parser

**Status:** accepted 2026-10-27.

## Context

`laravel-vue-i18n` reads lang files with `php-parser`. Anything the parser cannot evaluate is missed: constants, function calls, conditionals, package namespaces, `lang/vendor` overrides, and loaders other than the file loader.

## Decision

The package asks the app's `Translator` for its `FileLoader` and calls `load()` for each unit. Output equals what `__()` resolves, and extra directories are registered on that loader.

## Consequences

- Lang files execute, as they do in `__()`. Exporting runs the app's own code.
- Only strings survive, plus arrays in PHP groups. Other values are dropped with a warning.
- The floor is Laravel 12, where `FileLoader::paths()` first ships.
- A loader that does not extend `FileLoader` fails with `LangExportException::unsupportedLoader()`.
- Units are discovered by scanning files on disk, so groups that exist only in a DB-backed `FileLoader` subclass are not exported.
