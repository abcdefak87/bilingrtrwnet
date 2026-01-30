<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used
    | for generating payment links. Supported: "midtrans", "xendit", "tripay"
    |
    */

    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'midtrans'),

    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Midtrans payment gateway integration.
    | Get your credentials from: https://dashboard.midtrans.com/
    |
    */

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
        'is_3ds' => env('MIDTRANS_IS_3DS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Xendit Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Xendit payment gateway integration.
    | Get your credentials from: https://dashboard.xendit.co/
    |
    */

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'public_key' => env('XENDIT_PUBLIC_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        'is_production' => env('XENDIT_IS_PRODUCTION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tripay Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Tripay payment gateway integration.
    | Get your credentials from: https://tripay.co.id/member/merchant
    |
    */

    'tripay' => [
        'api_key' => env('TRIPAY_API_KEY'),
        'private_key' => env('TRIPAY_PRIVATE_KEY'),
        'merchant_code' => env('TRIPAY_MERCHANT_CODE'),
        'is_production' => env('TRIPAY_IS_PRODUCTION', false),
        'base_url' => env('TRIPAY_IS_PRODUCTION', false) 
            ? 'https://tripay.co.id/api' 
            : 'https://tripay.co.id/api-sandbox',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for payment gateway webhook endpoints.
    |
    */

    'webhook' => [
        'midtrans_url' => '/api/webhooks/midtrans',
        'xendit_url' => '/api/webhooks/xendit',
        'tripay_url' => '/api/webhooks/tripay',
    ],

];
