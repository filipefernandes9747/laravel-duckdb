<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DuckDB Installer
    |--------------------------------------------------------------------------
    |
    | Controls the automatic download and installation of DuckDB native
    | binaries and the FFI header when the application boots.
    |
    */

    'installer' => [

        /*
         | Enable or disable the auto-installer entirely.
         | Set to false in environments where you manage binaries manually.
         */
        'enabled' => env('DUCKDB_AUTO_INSTALL', true),

        /*
         | Directory where the native library and header will be stored.
         | Defaults to {storage_path}/duckdb — must be writable by your web server.
         */
        'path' => env('DUCKDB_INSTALL_PATH', null), // null = storage_path('duckdb') resolved at runtime

        /*
         | The DuckDB release version to download.
         | See: https://github.com/duckdb/duckdb/releases
         */
        'version' => env('DUCKDB_VERSION', '1.2.1'),

    ],

];
