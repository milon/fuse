<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    |
    | "cache" shares circuit state through Laravel's cache. "database" stores
    | it in the fuse_circuits table. Publish and run the migration first.
    |
    */

    'store' => env('FUSE_STORE', 'cache'),

    /*
    |--------------------------------------------------------------------------
    | Cache store
    |--------------------------------------------------------------------------
    |
    | Used when the store above is "cache". Null uses the default cache store.
    |
    */

    'cache_store' => env('FUSE_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Database store
    |--------------------------------------------------------------------------
    |
    | Used when the store above is "database". Null uses the default connection.
    | The table name must match the published migration.
    |
    */

    'database' => [
        'connection' => env('FUSE_DB_CONNECTION'),
        'table' => 'fuse_circuits',
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | These apply to every circuit unless a breaker below overrides them.
    | The defaults are intentionally lenient.
    |
    */

    'key_prefix' => env('FUSE_KEY_PREFIX', 'fuse'),

    'failure_threshold' => 8,

    'failure_window_seconds' => 60,

    'open_seconds' => 30,

    'half_open_probes' => 2,

    'counted_http_statuses' => [502, 503, 504],

    'count_timeouts' => true,

    'count_connection_errors' => true,

    'count_http_500' => false,

    /*
    |--------------------------------------------------------------------------
    | Named breakers
    |--------------------------------------------------------------------------
    |
    | Override any default for a single circuit. The name is the one you pass
    | to FuseManager::for() or return from a Saloon connector.
    |
    */

    'breakers' => [
        // 'billing' => [
        //     'failure_threshold' => 3,
        //     'open_seconds' => 15,
        // ],
    ],

];
