<?php

namespace App\Jobs;

use App\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * This job generates invoices for all active services that are due for billing.
     * It runs daily at 00:00 WIB via Laravel Scheduler.
     *
     * Requirements: 3.1 - Laravel Scheduler berjalan setiap hari pada pukul 00:00 WIB,
     * generate invoice untuk semua layanan aktif yang jatuh tempo billing
     */
    public function handle(BillingService $billingService): void
    {
        Log::info('GenerateInvoicesJob started');

        try {
            // Generate invoices for all services that are due for billing
            $invoices = $billingService->generateInvoicesForDueServices();

            Log::info('GenerateInvoicesJob completed', [
                'invoices_generated' => $invoices->count(),
                'invoice_ids' => $invoices->pluck('id')->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('GenerateInvoicesJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('GenerateInvoicesJob failed after all retries', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
