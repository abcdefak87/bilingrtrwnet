<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Service;
use App\Services\IsolationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to process isolation for a specific service.
 *
 * This job:
 * 1. Calls IsolationService->isolateService()
 * 2. Queues WhatsApp notification (placeholder for now)
 * 3. Implements retry mechanism (3 attempts with exponential backoff)
 * 4. Logs success/failure
 *
 * Requirements: 5.2, 5.3, 5.4, 5.5, 5.6
 */
class ProcessIsolationJob implements ShouldQueue
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
     * Exponential backoff: 60s, 120s, 240s
     *
     * @var array
     */
    public $backoff = [60, 120, 240];

    /**
     * The service ID to isolate.
     *
     * @var int
     */
    public int $serviceId;

    /**
     * The invoice ID causing the isolation.
     *
     * @var int
     */
    public int $invoiceId;

    /**
     * Create a new job instance.
     *
     * @param int $serviceId
     * @param int $invoiceId
     */
    public function __construct(int $serviceId, int $invoiceId)
    {
        $this->serviceId = $serviceId;
        $this->invoiceId = $invoiceId;
    }

    /**
     * Execute the job.
     *
     * @param IsolationService $isolationService
     * @return void
     */
    public function handle(IsolationService $isolationService): void
    {
        Log::info('Processing isolation job', [
            'service_id' => $this->serviceId,
            'invoice_id' => $this->invoiceId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Load service and invoice
            $service = Service::with(['customer', 'mikrotikRouter', 'package'])->findOrFail($this->serviceId);
            $invoice = Invoice::findOrFail($this->invoiceId);

            // Call IsolationService to isolate the service
            $success = $isolationService->isolateService($service, $invoice);

            if ($success) {
                Log::info('Isolation processed successfully', [
                    'service_id' => $this->serviceId,
                    'invoice_id' => $this->invoiceId,
                    'customer_id' => $service->customer_id,
                ]);

                // Queue WhatsApp notification to customer
                SendIsolationNotificationJob::dispatch($service->id);

                Log::info('Isolation notification queued', [
                    'service_id' => $this->serviceId,
                ]);
            } else {
                // Isolation failed, throw exception to trigger retry
                throw new \Exception('Isolation service returned false');
            }
        } catch (\Exception $e) {
            Log::error('Failed to process isolation', [
                'service_id' => $this->serviceId,
                'invoice_id' => $this->invoiceId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
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
        Log::critical('ProcessIsolationJob failed after all retries', [
            'service_id' => $this->serviceId,
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // TODO: Queue manual review notification to admin
        // AdminAlertJob::dispatch('isolation_failed', [
        //     'service_id' => $this->serviceId,
        //     'invoice_id' => $this->invoiceId,
        // ]);
    }
}
