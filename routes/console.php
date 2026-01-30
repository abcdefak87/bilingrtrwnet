<?php

use App\Jobs\CheckOverdueInvoicesJob;
use App\Jobs\GenerateInvoicesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Schedule automated tasks
 *
 * Requirements: 3.1 - Laravel Scheduler berjalan setiap hari pada pukul 00:00 WIB,
 * generate invoice untuk semua layanan aktif yang jatuh tempo billing
 */
Schedule::job(new GenerateInvoicesJob())
    ->dailyAt('00:00')
    ->timezone('Asia/Jakarta') // WIB timezone
    ->name('generate-invoices')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Scheduled invoice generation completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Scheduled invoice generation failed');
    });

/**
 * Schedule overdue invoice check and isolation
 *
 * Requirements: 5.1, 5.2 - Laravel Scheduler berjalan setiap hari pada pukul 01:00 WIB,
 * mengidentifikasi semua invoice yang belum dibayar dimana (current_date > due_date + masa_tenggang)
 * dan mengantri job untuk mengisolir layanan terkait
 */
Schedule::job(new CheckOverdueInvoicesJob())
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta') // WIB timezone
    ->name('check-overdue-invoices')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Log::info('Scheduled overdue invoice check completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Scheduled overdue invoice check failed');
    });
