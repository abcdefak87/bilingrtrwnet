<?php

namespace App\Jobs;

use App\Services\IsolationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to check for overdue invoices and queue isolation jobs.
 *
 * This job runs daily at 01:00 WIB via Laravel Scheduler.
 * It identifies all services with overdue invoices and queues
 * ProcessIsolationJob for each service that needs to be isolated.
 *
 * Requirements: 5.1, 5.2
 */
class CheckOverdueInvoicesJob implements ShouldQueue
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
     * This method:
     * 1. Calls IsolationService->checkOverdueServices() to get overdue services
     * 2. For each overdue service, queues ProcessIsolationJob
     * 3. Logs summary of total checked and total queued
     *
     * @param IsolationService $isolationService
     * @return void
     */
    public function handle(IsolationService $isolationService): void
    {
        Log::info('Starting overdue invoice check');

        try {
            // Get all services that need to be isolated
            $overdueServices = $isolationService->checkOverdueServices();

            $totalChecked = $overdueServices->count();
            $totalQueued = 0;

            // Queue ProcessIsolationJob for each overdue service
            foreach ($overdueServices as $service) {
                // Get the overdue invoice for this service
                $overdueInvoice = $service->invoices()
                    ->where('status', 'unpaid')
                    ->whereDate('due_date', '<', now()->subDays(config('billing.grace_period_days', 3)))
                    ->orderBy('due_date', 'asc')
                    ->first();

                if ($overdueInvoice) {
                    ProcessIsolationJob::dispatch($service->id, $overdueInvoice->id);
                    $totalQueued++;

                    Log::info('Queued isolation job', [
                        'service_id' => $service->id,
                        'customer_id' => $service->customer_id,
                        'invoice_id' => $overdueInvoice->id,
                    ]);
                }
            }

            Log::info('Overdue invoice check completed', [
                'total_checked' => $totalChecked,
                'total_queued' => $totalQueued,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check overdue invoices', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('CheckOverdueInvoicesJob failed after all retries', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
