<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Cycle Days
    |--------------------------------------------------------------------------
    |
    | This value determines the number of days in a billing cycle.
    | The default is 30 days (monthly billing).
    | When an invoice is generated, the due_date will be set to
    | (invoice_date + cycle_days).
    |
    */

    'cycle_days' => env('BILLING_CYCLE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Grace Period Days
    |--------------------------------------------------------------------------
    |
    | This value determines the number of days after the due date
    | before a service is isolated for non-payment.
    | Default is 3 days.
    |
    */

    'grace_period_days' => env('BILLING_GRACE_PERIOD_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Invoice Generation Time
    |--------------------------------------------------------------------------
    |
    | The time (in 24-hour format) when the daily invoice generation
    | job should run. Default is 00:00 (midnight).
    |
    */

    'invoice_generation_time' => env('BILLING_INVOICE_TIME', '00:00'),

    /*
    |--------------------------------------------------------------------------
    | Isolation Check Time
    |--------------------------------------------------------------------------
    |
    | The time (in 24-hour format) when the daily isolation check
    | job should run. Default is 01:00 (1 AM).
    |
    */

    'isolation_check_time' => env('BILLING_ISOLATION_TIME', '01:00'),

];
