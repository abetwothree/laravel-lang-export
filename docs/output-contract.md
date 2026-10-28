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

- Atomic (a temp file unique to each write, then rename), and skipped when the bytes are unchanged.
- Each run holds an exclusive lock on `storage/framework/lang-export.lock` from its first output read to its last write, so concurrent runs queue. The lock file is never deleted.
- Every locale is encoded before any file is written.
- A full export removes a stale locale file only when the previous manifest listed it.

## Command

`php artisan lang:export` with `--locale=*`, `--only=*`, `--except=*`, `--output=` and `--source=`. Flags override config. A relative `--output`, and relative config `output_path`, `paths`, `json_paths` and `namespaces` values, resolve against `base_path()`.

- An output path that is the base path, or a registered lang, JSON or namespace directory, fails before anything is written. So does malformed config.
- `--locale` (or config `locales`) warns `{locale}: no lang files found` for each requested locale without lang files. When none has any, the run fails and writes or deletes nothing. An empty `locales` list means every locale.
- `--source=` takes one changed file, absolute or relative to `base_path()`. Paths with `..` segments are refused, and only files under registered lang directories load.
- `--source` falls back to a full export when the manifest is missing, and to a whole-locale export when the locale is unlisted or its file is missing or unreadable, or when the changed unit parses empty. The result matches a full export.
- A source file excluded by filters prints `Skipped {source}: excluded by filters`.
- A source path inside a registered lang, JSON or namespace directory that is not a translation unit (a readme, an editor swap file, an unregistered `lang/vendor` namespace) prints `Skipped {source}: not a translation file`. A path outside every registered directory fails.
- Exit `0` on success, including a skip. Exit `1` on any `LangExportException`, printed with `error()`. Warnings print on stdout. No prompts.

## Caller obligations

- Serialize or debounce `--source` calls. The command's lock queues concurrent runs, but each one still runs.
- Pass `--source` the same `--locale`, `--only` and `--except` as the full export. The manifest records no filters, so a mismatch patches in units the full export left out.
- Prefer `--source` paths relative to the base path. Absolute paths compare as normalized strings, so a host path never matches one inside a Sail container.
- Expect `Skipped {source}: not a translation file` (exit `0`) for other files in watched directories. A path outside every registered directory, or with a `..` segment, exits `1`.
