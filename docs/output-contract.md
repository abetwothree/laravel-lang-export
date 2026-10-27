# Output contract

What the `@tolki/lang/vite-laravel` plugin and the frontend Translator rely on. Source of truth: [`LocaleDocument`](../src/Output/LocaleDocument.php), [`Manifest`](../src/Data/Manifest.php), [`LangExportCommand`](../src/Commands/LangExportCommand.php).

## Locale file

`{output}/{locale}.json`, default output `resource_path('js/lang')`.

- Top level is always an object with sorted keys; the file ends with a newline.
- App groups sit under their name (`auth`, `admin/users`); nested groups use `/` on every OS.
- Package groups sit under `namespace::group`.
- JSON lines are top-level strings.
- A JSON line colliding with a group key is dropped with a warning; an empty group file does not remove it.
- Non-string leaves are dropped with a warning: `{locale}: {key}.{path} skipped ({type})`. A JSON line that is not a string is dropped as `{locale}: {line} skipped ({type})`.

## Manifest

`{output}/manifest.json`:

- `version`: `1`.
- `locales`: sorted exported locales.
- `watch`: registered lang, JSON and namespace directories that exist on disk, de-duplicated and sorted. Relative to the base path when inside it; `.` and `..` collapsed.

## Writes

- Atomic (temp file and rename), and skipped when the bytes are unchanged.
- Every locale is encoded before any file is written.
- A full export removes a stale locale file only when the previous manifest listed it.

## Command

`php artisan lang:export` with `--locale=*`, `--only=*`, `--except=*`, `--output=` and `--source=`. Flags override config. Relative `--output` resolves against `base_path()`.

- `--source=` takes one changed file, absolute or relative to `base_path()`. Paths with `..` segments are refused, and only files under registered lang directories load.
- `--source` falls back to a full export when the manifest is missing, and to a whole-locale export when the locale is unlisted or its file is missing or unreadable, or when the changed unit parses empty. The result matches a full export.
- A source file excluded by filters prints `Skipped {source}: excluded by filters`.
- Exit `0` on success, including a skip. Exit `1` on any `LangExportException`, printed with `error()`. Warnings print on stdout. No prompts.
