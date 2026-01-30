<?php

namespace App\Jobs;

use App\Models\Service;
use App\Services\IsolationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RestoreServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The service instance.
     *
     * @var Service
     */
    public Service $service;

    /**
     * Create a new job instance.
     */
    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    /**
     * Execute the job.
     *
     * Restores an isolated service by calling IsolationService to restore the original profile.
     */
    public function handle(IsolationService $isolationService): void
    {
        try {
            Log::info('Starting service restoration', [
                'service_id' => $this->service->id,
                'customer_id' => $this->service->customer_id,
                'current_status' => $this->service->status,
            ]);

            // Call IsolationService to restore the service
            $success = $isolationService->restoreService($this->service);

            if (!$success) {
                throw new \Exception('IsolationService failed to restore service');
            }

            // Queue restoration notification (Requirement 5.9)
            SendRestorationNotificationJob::dispatch($this->service->id);

            Log::info('Service restoration completed successfully', [
                'service_id' => $this->service->id,
                'customer_id' => $this->service->customer_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to restore service', [
                'service_id' => $this->service->id,
                'customer_id' => $this->service->customer_id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // Exponential backoff: 1 minute, 5 minutes, 15 minutes
        return [60, 300, 900];
    }
}
