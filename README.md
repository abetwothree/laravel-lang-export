# Export your Laravel translations as JSON for Vue, React, Svelte or any other JS framework

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abetwothree/lang-export.svg?style=flat-square)](https://packagist.org/packages/abetwothree/lang-export)
[![Laravel Compatibility](https://badge.laravel.cloud/badge/abetwothree/lang-export)](https://packagist.org/packages/abetwothree/lang-export)
[![PHP Compatibility](https://badge.laravel.cloud/php-badge/abetwothree/lang-export)](https://packagist.org/packages/abetwothree/lang-export)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-lang/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abetwothree/laravel-lang/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-lang/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abetwothree/laravel-lang/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abetwothree/lang-export.svg?style=flat-square)](https://packagist.org/packages/abetwothree/lang-export)

This package writes your Laravel translations to one JSON file per locale. It reads every file through Laravel's own translation loader, so the JSON holds the same lines `__()` returns.

It is the Laravel side of [`@tolki/lang`](https://github.com/abetwothree/tolki/tree/master/packages/lang), a JavaScript port of Laravel's translator from [Tolki](https://github.com/abetwothree/tolki). `@tolki/lang` does not read lang files itself. It takes the JSON this package writes.

## Installation

```bash
composer require abetwothree/lang-export
```

```bash
npm install @tolki/lang
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="lang-export-config"
```

## Exporting

```bash
php artisan lang:export
```

This writes `resources/js/lang/{locale}.json` for each locale, plus a `manifest.json` that lists the locales and the lang directories they came from. PHP groups become nested objects, JSON lines stay at the top level, and a package's groups sit under `namespace::group` keys:

```json
{
    "Welcome": "Welcome",
    "auth": {
        "failed": "These credentials do not match our records."
    },
    "courier::mail": {
        "sent": "Your message was sent."
    }
}
```

Pass `--locale`, `--only`, `--except` or `--output` to narrow a run, or set them in `config/lang-export.php`.

## Re-exporting one file

```bash
php artisan lang:export --source=lang/en/auth.php
```

`--source` re-exports one changed lang file and updates its locale's JSON file. File watchers call it on every save.

## Vite plugin

The Vite plugin in `@tolki/lang` runs the export for you. It exports when `vite dev` starts and before `vite build`, re-exports each lang file you save, and serves the locales as the `virtual:tolki-lang` module.

```ts
// vite.config.ts
import laravelLangExport from "@tolki/lang/vite-laravel";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [laravelLangExport()],
});
```

```ts
import { createLang } from "@tolki/lang";
import { load } from "virtual:tolki-lang";

const lang = createLang({ locale: "en", fallback: "en", load });
await lang.ready;

lang.__("auth.failed");
// => "These credentials do not match our records."
```

## Vue, React and Svelte plugins

`@tolki/lang` has a native plugin for each framework. Each one hands the lang to your components and re-renders them when the locale changes. All three work with Inertia, server-side rendering included.

- [`@tolki/lang/vue`](https://tolki.abe.dev/localization/vue.html) installs into a Vue 3.5 app. Templates get `__`, `trans` and `transChoice`, and `useLang()` gives a `locale` you can bind with `v-model`.
- [`@tolki/lang/react`](https://tolki.abe.dev/localization/react.html) gives React 19 a `<LangProvider>` and a `useLang()` hook.
- [`@tolki/lang/svelte`](https://tolki.abe.dev/localization/svelte.html) passes the lang to Svelte 5.7 components through context, with `setLang()` and `getLang()`.

## Key capabilities

- Package namespaces and `lang/vendor` overrides, resolved as Laravel resolves them
- Extra lang paths, JSON paths and namespaces for modular apps
- Locale filtering, plus allow and deny patterns for groups
- Single-file re-export with `--source` for file watchers
- Laravel's own validation, auth, pagination and password lines included
- Skips rewriting locale files whose lines did not change
- Overlapping runs wait for each other instead of overwriting each other's files

## Documentation

The [Tolki localization docs](https://tolki.abe.dev/localization/) cover usage in depth:

- [Exporting from Laravel](https://tolki.abe.dev/localization/index.html#exporting-from-laravel)
- [Vite plugin](https://tolki.abe.dev/localization/vite.html), with its options, Sail setup and the virtual module
- [Vue](https://tolki.abe.dev/localization/vue.html), [React](https://tolki.abe.dev/localization/react.html) and [Svelte](https://tolki.abe.dev/localization/svelte.html) bindings

## Testing

```bash
composer test
```

## Credits

- [Abraham Arango](https://github.com/abetwothree)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
