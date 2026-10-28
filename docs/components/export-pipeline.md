# Export pipeline

`lang:export` loads each translation unit through Laravel's loader and writes one JSON file per locale. The code is the source of truth.

| Class | Role |
| --- | --- |
| [`LangExportCommand`](../../src/Commands/LangExportCommand.php) | Flags to `ExportOptions`; picks `exportAll()` or `exportFile()`; maps `LangExportException` to exit 1. |
| [`LangExporter`](../../src/LangExporter.php) | Orchestrates the run under an exclusive lock: parse, encode, write, prune, manifest. |
| [`ExportOptions`](../../src/Data/ExportOptions.php) | Validates `config/lang-export.php` and resolves its relative paths; flags replace config values. |
| [`LangSources`](../../src/Discovery/LangSources.php) | Registers configured paths on the loader and snapshots lang, JSON and namespace directories. |
| [`UnitFinder`](../../src/Discovery/UnitFinder.php) | Lists every `TranslationUnit`, maps one file path to its unit, and tells whether a path lies in a lang directory. |
| [`UnitFilter`](../../src/Discovery/UnitFilter.php) | Applies the locale, `json`, `only` and `except` rules. |
| [`TranslationParser`](../../src/Parsing/TranslationParser.php) | Loads a unit via `FileLoader`; drops non-string leaves with warnings. |
| [`LocaleDocument`](../../src/Output/LocaleDocument.php) | Assembles a locale's lines, resolves key collisions, encodes JSON. |
| [`OutputDirectory`](../../src/Output/OutputDirectory.php) | Atomic, change-aware reads and writes of locale files and the manifest; sweeps orphaned temp files. |
| [`ExportLock`](../../src/Support/ExportLock.php) | Exclusive run lock with a 30-second wait; unheld where the filesystem cannot lock. |
| [`Manifest`](../../src/Data/Manifest.php) | Locale list, watch directories and export fingerprint. |
| [`ExportFingerprint`](../../src/Support/ExportFingerprint.php) | Hashes the package version, output-shaping options and lang sources; a mismatch turns `--source` into a full export. |
| [`PackageVersion`](../../src/Support/PackageVersion.php) | Installed package version, folded into the export fingerprint. |
| [`BasePath`](../../src/Support/BasePath.php) | Resolves relative config, `--output` and `--source` paths against `base_path()`. |
| [`Path`](../../src/Support/Path.php) | Normalizes slashes, `.`, `..` and drive-letter case so lang directories and `--source` paths compare equal. |
| [`LangExportException`](../../src/Exceptions/LangExportException.php) | Every expected failure. |

Value objects: [`TranslationUnit`](../../src/Data/TranslationUnit.php), [`ParsedUnit`](../../src/Data/ParsedUnit.php), [`ExportResult`](../../src/Data/ExportResult.php), [`SkipReason`](../../src/Data/SkipReason.php).

Why it loads through `FileLoader`: [ADR](../decisions/2026-10-27-load-through-laravel-loader.md).
