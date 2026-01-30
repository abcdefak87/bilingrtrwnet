<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mikrotik Connection Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the behavior of Mikrotik RouterOS API connections.
    |
    */

    'connection' => [
        // Connection timeout in seconds
        'timeout' => env('MIKROTIK_TIMEOUT', 5),

        // Number of connection attempts before failing
        'attempts' => env('MIKROTIK_ATTEMPTS', 3),

        // Delay between retry attempts in milliseconds
        'retry_delay' => env('MIKROTIK_RETRY_DELAY', 1000),

        // Enable connection pooling
        'pooling_enabled' => env('MIKROTIK_POOLING_ENABLED', true),

        // Maximum number of connections to keep in pool per router
        'pool_size' => env('MIKROTIK_POOL_SIZE', 3),

        // Connection idle timeout in seconds (how long to keep idle connections)
        'pool_idle_timeout' => env('MIKROTIK_POOL_IDLE_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | PPPoE Profile Settings
    |--------------------------------------------------------------------------
    |
    | Default profile names used for PPPoE users.
    |
    */

    'profiles' => [
        // Profile name for isolated/suspended users
        'isolation' => env('MIKROTIK_ISOLATION_PROFILE', 'Isolir'),

        // Default profile prefix for regular users
        'default_prefix' => env('MIKROTIK_DEFAULT_PROFILE_PREFIX', 'Package-'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Control logging behavior for Mikrotik operations.
    |
    */

    'logging' => [
        // Enable detailed logging of API calls
        'enabled' => env('MIKROTIK_LOGGING_ENABLED', true),

        // Log channel to use
        'channel' => env('MIKROTIK_LOG_CHANNEL', 'stack'),
    ],

];
