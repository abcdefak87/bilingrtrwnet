<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Bulk notification job that processes notifications in batches to respect rate limiting.
 *
 * Features:
 * - Processes notifications in batches of 50 (configurable)
 * - Adds delay between batches to respect rate limiting (50 msg/min)
 * - Uses WhatsAppService->sendBulk() method for efficient batch processing
 * - Automatic retry up to 3 times with exponential backoff
 * - Comprehensive error handling and logging
 *
 * Requirements: 7.6
 */
class SendBulkNotificationJob implements ShouldQueue
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
     * Exponential backoff: 30s, 60s, 120s
     *
     * @var array
     */
    public $backoff = [30, 60, 120];

    /**
     * Notification channel (whatsapp, email)
     *
     * @var string
     */
    protected string $channel;

    /**
     * Array of recipients with their messages
     * Format: [['phone' => '628xxx', 'message' => 'text'], ...]
     * or [['email' => 'user@example.com', 'message' => 'text', 'subject' => 'Subject'], ...]
     *
     * @var array
     */
    protected array $recipients;

    /**
     * Batch size for processing
     *
     * @var int
     */
    protected int $batchSize;

    /**
     * Additional metadata
     *
     * @var array
     */
    protected array $metadata;

    /**
     * Create a new job instance.
     *
     * @param string $channel Channel to send notification (whatsapp, email)
     * @param array $recipients Array of recipients with their messages
     * @param int $batchSize Batch size for processing (default: 50)
     * @param array $metadata Additional metadata for logging
     */
    public function __construct(
        string $channel,
        array $recipients,
        int $batchSize = 50,
        array $metadata = []
    ) {
        $this->channel = $channel;
        $this->recipients = $recipients;
        $this->batchSize = $batchSize;
        $this->metadata = $metadata;
    }

    /**
     * Execute the job.
     *
     * @param WhatsAppService $whatsappService
     * @return void
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        Log::info('Processing bulk notification', [
            'channel' => $this->channel,
            'total_recipients' => count($this->recipients),
            'batch_size' => $this->batchSize,
            'attempt' => $this->attempts(),
            'metadata' => $this->metadata,
        ]);

        try {
            $results = [];

            if ($this->channel === 'whatsapp') {
                $results = $this->sendBulkWhatsApp($whatsappService);
            } elseif ($this->channel === 'email') {
                $results = $this->sendBulkEmail();
            } else {
                throw new \InvalidArgumentException("Unsupported channel: {$this->channel}");
            }

            // Calculate success rate
            $successCount = count(array_filter($results, fn($r) => $r['success'] ?? false));
            $totalCount = count($results);
            $successRate = $totalCount > 0 ? ($successCount / $totalCount) * 100 : 0;

            Log::info('Bulk notification completed', [
                'channel' => $this->channel,
                'total' => $totalCount,
                'success' => $successCount,
                'failed' => $totalCount - $successCount,
                'success_rate' => round($successRate, 2) . '%',
                'metadata' => $this->metadata,
            ]);

            // If success rate is too low, consider it a failure
            if ($successRate < 50) {
                throw new \Exception("Bulk notification success rate too low: {$successRate}%");
            }

        } catch (\Exception $e) {
            Log::error('Failed to process bulk notification', [
                'channel' => $this->channel,
                'total_recipients' => count($this->recipients),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'metadata' => $this->metadata,
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Send bulk notifications via WhatsApp in batches.
     *
     * @param WhatsAppService $whatsappService
     * @return array
     */
    protected function sendBulkWhatsApp(WhatsAppService $whatsappService): array
    {
        $allResults = [];
        $batches = array_chunk($this->recipients, $this->batchSize);
        $totalBatches = count($batches);

        Log::info('Processing WhatsApp bulk notification in batches', [
            'total_recipients' => count($this->recipients),
            'batch_size' => $this->batchSize,
            'total_batches' => $totalBatches,
        ]);

        foreach ($batches as $batchIndex => $batch) {
            $batchNumber = $batchIndex + 1;

            Log::info("Processing batch {$batchNumber}/{$totalBatches}", [
                'batch_size' => count($batch),
            ]);

            try {
                // Send batch using WhatsAppService->sendBulk()
                $batchResults = $whatsappService->sendBulk($batch);
                $allResults = array_merge($allResults, $batchResults);

                // Calculate batch success rate
                $batchSuccess = count(array_filter($batchResults, fn($r) => $r['success'] ?? false));
                $batchTotal = count($batchResults);

                Log::info("Batch {$batchNumber}/{$totalBatches} completed", [
                    'success' => $batchSuccess,
                    'total' => $batchTotal,
                ]);

                // Add delay between batches to respect rate limiting
                // Rate limit: 50 msg/min = 1 message per 1.2 seconds
                // For a batch of 50, we need to wait ~60 seconds before next batch
                if ($batchNumber < $totalBatches) {
                    $delaySeconds = 60; // Wait 60 seconds between batches
                    
                    Log::info("Waiting {$delaySeconds} seconds before next batch to respect rate limiting");
                    sleep($delaySeconds);
                }

            } catch (\Exception $e) {
                Log::error("Batch {$batchNumber}/{$totalBatches} failed", [
                    'error' => $e->getMessage(),
                ]);

                // Mark all recipients in this batch as failed
                foreach ($batch as $recipient) {
                    $allResults[] = [
                        'phone' => $recipient['phone'] ?? 'unknown',
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $allResults;
    }

    /**
     * Send bulk notifications via Email in batches.
     *
     * @return array
     */
    protected function sendBulkEmail(): array
    {
        $allResults = [];
        $batches = array_chunk($this->recipients, $this->batchSize);
        $totalBatches = count($batches);

        Log::info('Processing Email bulk notification in batches', [
            'total_recipients' => count($this->recipients),
            'batch_size' => $this->batchSize,
            'total_batches' => $totalBatches,
        ]);

        foreach ($batches as $batchIndex => $batch) {
            $batchNumber = $batchIndex + 1;

            Log::info("Processing email batch {$batchNumber}/{$totalBatches}", [
                'batch_size' => count($batch),
            ]);

            foreach ($batch as $recipient) {
                try {
                    $email = $recipient['email'] ?? '';
                    $message = $recipient['message'] ?? '';
                    $subject = $recipient['subject'] ?? 'Notifikasi dari ISP Billing System';

                    if (empty($email) || empty($message)) {
                        $allResults[] = [
                            'email' => $email,
                            'success' => false,
                            'error' => 'Missing email or message',
                        ];
                        continue;
                    }

                    \Illuminate\Support\Facades\Mail::raw($message, function ($mail) use ($email, $subject) {
                        $mail->to($email)->subject($subject);
                    });

                    $allResults[] = [
                        'email' => $email,
                        'success' => true,
                    ];

                } catch (\Exception $e) {
                    $allResults[] = [
                        'email' => $recipient['email'] ?? 'unknown',
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Add small delay between batches
            if ($batchNumber < $totalBatches) {
                $delaySeconds = 5; // Shorter delay for email
                Log::info("Waiting {$delaySeconds} seconds before next email batch");
                sleep($delaySeconds);
            }
        }

        return $allResults;
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('SendBulkNotificationJob failed after all retries', [
            'channel' => $this->channel,
            'total_recipients' => count($this->recipients),
            'batch_size' => $this->batchSize,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'metadata' => $this->metadata,
        ]);

        // TODO: Queue admin alert for failed bulk notification
        // This could be implemented in a future task to notify admins
        // about critical bulk notification failures
    }
}
