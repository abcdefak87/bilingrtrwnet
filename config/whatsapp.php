<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk WhatsApp gateway yang digunakan untuk mengirim
    | notifikasi ke pelanggan. Sistem mendukung Fonnte dan Wablas.
    |
    */

    'gateway' => env('WHATSAPP_GATEWAY', 'fonnte'), // fonnte atau wablas

    /*
    |--------------------------------------------------------------------------
    | Fonnte Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk Fonnte WhatsApp gateway.
    | API Documentation: https://fonnte.com/api
    |
    */

    'fonnte' => [
        'api_key' => env('FONNTE_API_KEY'),
        'base_url' => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),
        'timeout' => env('FONNTE_TIMEOUT', 30), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Wablas Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk Wablas WhatsApp gateway.
    | API Documentation: https://wablas.com/api
    |
    */

    'wablas' => [
        'api_key' => env('WABLAS_API_KEY'),
        'base_url' => env('WABLAS_BASE_URL', 'https://console.wablas.com'),
        'timeout' => env('WABLAS_TIMEOUT', 30), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk retry mechanism saat pengiriman gagal.
    |
    */

    'retry' => [
        'max_attempts' => env('WHATSAPP_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('WHATSAPP_RETRY_DELAY', 5), // seconds
        'multiplier' => env('WHATSAPP_RETRY_MULTIPLIER', 2), // exponential backoff
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk rate limiting pengiriman pesan.
    |
    */

    'rate_limit' => [
        'enabled' => env('WHATSAPP_RATE_LIMIT_ENABLED', true),
        'max_per_minute' => env('WHATSAPP_RATE_LIMIT_PER_MINUTE', 50),
    ],
];
