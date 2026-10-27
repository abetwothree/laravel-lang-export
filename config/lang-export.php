<?php

declare(strict_types=1);

return [
    // Directory the per-locale JSON files are written to.
    'output_path' => resource_path('js/lang'),

    // Locales to export; null exports every locale found.
    'locales' => null,

    // Only export groups matching these Str::is() patterns, e.g. 'auth', 'admin/*', 'courier::*'.
    'only' => [],

    // Skip groups matching these Str::is() patterns, e.g. 'auth', 'admin/*', 'courier::*'.
    'except' => [],

    // Include the top-level JSON translation lines.
    'json' => true,

    // Extra directories to read PHP translation groups from.
    'paths' => [],

    // Extra directories to read JSON translation files from.
    'json_paths' => [],

    // Maps a package namespace to its lang directory.
    'namespaces' => [],

    // Pretty print the JSON output.
    'pretty_print' => false,
];
