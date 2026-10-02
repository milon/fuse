<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache store
    |--------------------------------------------------------------------------
    |
    | Circuit state is shared through Laravel's cache. Null uses the default
    | cache store. Set a store name to pin Fuse to a specific one.
    |
    */

    'cache_store' => env('FUSE_CACHE_STORE'),

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
