# laravel-lang-export

A Laravel package that exports an application's translations as one JSON file per locale for the `@tolki/lang` frontend Translator.

## Language

**Export**:
One run of `lang:export`. It writes a Locale file per locale and a Manifest.
_Avoid_: compile, build, generate, publish

**Translation unit**:
One source of lines for one locale: a PHP group file (`{lang path}/{locale}/{group}.php`, or the same shape below a Namespace hint) or a JSON file (`{locale}.json` in a Lang path or JSON path). A unit is the smallest thing `--source` re-exports.
_Avoid_: translation file, resource

**Locale file**:
The exported `{locale}.json`, holding every unit of that locale. App groups sit under their name, package groups under `namespace::group`, JSON lines at the top level.
_Avoid_: language file, bundle

**Manifest**:
The `manifest.json` beside the Locale files. It lists the exported locales and the directories a watcher should observe.

**Lang path**:
A directory Laravel reads PHP translation groups and `{locale}.json` files from: the framework's and the app's `lang/`, plus configured `paths`. A package's directory is a Namespace hint, not a Lang path.

**JSON path**:
A directory Laravel reads `{locale}.json` lines from, including configured `json_paths`.

**Namespace hint**:
The lang directory registered for a package namespace, as `loadTranslationsFrom()` and configured `namespaces` do. Its groups export as `namespace::group`, as in `courier::mail`.

**Vendor override**:
An app file at `lang/vendor/{namespace}/{locale}/{group}.php` that replaces a package's group, merged by Laravel's loader.

**Source re-export**:
Re-exporting only the Translation unit one changed file belongs to, via `--source`. It writes what a full Export would.
_Avoid_: partial export, incremental export
