# Export your Laravel translations as JSON for Vue, React, Svelte or any other JS framework

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abetwothree/laravel-lang-export.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-lang-export)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-lang/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/abetwothree/laravel-lang/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/abetwothree/laravel-lang/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/abetwothree/laravel-lang/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/abetwothree/laravel-lang-export.svg?style=flat-square)](https://packagist.org/packages/abetwothree/laravel-lang-export)

This package exports your Laravel translations as one JSON file per locale, ready for the `Translator` in [`@tolki/lang`](https://github.com/abetwothree/tolki/tree/master/packages/lang). It loads every file through Laravel's own translation loader, so the output matches what `__()` resolves.

## Installation

```bash
composer require abetwothree/laravel-lang-export
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="lang-export-config"
```

## Usage

```bash
php artisan lang:export
```

This writes `resources/js/lang/en.json` (one file per locale) and a `manifest.json`:

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

## Key capabilities

- Package namespaces and `lang/vendor` overrides, exactly as Laravel resolves them
- Modular apps through extra lang paths, JSON paths and namespaces
- Allow and deny patterns for groups, plus locale filtering
- Single-file re-export with `--source` for file watchers
- Laravel's framework lines (validation, auth, pagination, passwords) included

## Documentation

See the [Tolki localization docs](https://tolki.abe.dev/localization/) for usage in depth.

## Testing

```bash
composer test
```

## Credits

- [Abraham Arango](https://github.com/abetwothree)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
