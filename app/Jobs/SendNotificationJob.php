<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Generic notification job that handles sending notifications via multiple channels.
 *
 * Supports:
 * - WhatsApp notifications via WhatsAppService
 * - Email notifications via Laravel Mail
 *
 * Features:
 * - Automatic retry up to 3 times with exponential backoff
 * - Comprehensive error handling and logging
 * - Support for multiple notification channels
 *
 * Requirements: 7.1, 7.2, 7.3, 7.4
 */
class SendNotificationJob implements ShouldQueue
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
     * Notification channel (whatsapp, email, both)
     *
     * @var string
     */
    protected string $channel;

    /**
     * Recipient phone number (for WhatsApp)
     *
     * @var string|null
     */
    protected ?string $phone;

    /**
     * Recipient email address (for Email)
     *
     * @var string|null
     */
    protected ?string $email;

    /**
     * Notification message
     *
     * @var string
     */
    protected string $message;

    /**
     * Email subject (for Email channel)
     *
     * @var string|null
     */
    protected ?string $subject;

    /**
     * Additional metadata
     *
     * @var array
     */
    protected array $metadata;

    /**
     * Create a new job instance.
     *
     * @param string $channel Channel to send notification (whatsapp, email, both)
     * @param string|null $phone Recipient phone number
     * @param string|null $email Recipient email address
     * @param string $message Notification message
     * @param string|null $subject Email subject (required for email channel)
     * @param array $metadata Additional metadata for logging
     */
    public function __construct(
        string $channel,
        ?string $phone,
        ?string $email,
        string $message,
        ?string $subject = null,
        array $metadata = []
    ) {
        $this->channel = $channel;
        $this->phone = $phone;
        $this->email = $email;
        $this->message = $message;
        $this->subject = $subject;
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
        Log::info('Processing notification', [
            'channel' => $this->channel,
            'phone' => $this->phone,
            'email' => $this->email,
            'attempt' => $this->attempts(),
            'metadata' => $this->metadata,
        ]);

        try {
            $results = [];

            // Send via WhatsApp if channel includes whatsapp
            if (in_array($this->channel, ['whatsapp', 'both']) && !empty($this->phone)) {
                $results['whatsapp'] = $this->sendWhatsApp($whatsappService);
            }

            // Send via Email if channel includes email
            if (in_array($this->channel, ['email', 'both']) && !empty($this->email)) {
                $results['email'] = $this->sendEmail();
            }

            // Check if at least one channel succeeded
            $hasSuccess = !empty($results) && in_array(true, $results, true);

            if (!$hasSuccess) {
                throw new \Exception('All notification channels failed');
            }

            Log::info('Notification sent successfully', [
                'channel' => $this->channel,
                'results' => $results,
                'metadata' => $this->metadata,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'channel' => $this->channel,
                'phone' => $this->phone,
                'email' => $this->email,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'metadata' => $this->metadata,
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Send notification via WhatsApp.
     *
     * @param WhatsAppService $whatsappService
     * @return bool
     */
    protected function sendWhatsApp(WhatsAppService $whatsappService): bool
    {
        try {
            $success = $whatsappService->sendMessage($this->phone, $this->message);

            if ($success) {
                Log::info('WhatsApp notification sent', [
                    'phone' => $this->phone,
                    'metadata' => $this->metadata,
                ]);
            } else {
                Log::warning('WhatsApp notification failed', [
                    'phone' => $this->phone,
                    'metadata' => $this->metadata,
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('WhatsApp notification exception', [
                'phone' => $this->phone,
                'error' => $e->getMessage(),
                'metadata' => $this->metadata,
            ]);

            return false;
        }
    }

    /**
     * Send notification via Email.
     *
     * @return bool
     */
    protected function sendEmail(): bool
    {
        try {
            Mail::raw($this->message, function ($mail) {
                $mail->to($this->email)
                    ->subject($this->subject ?? 'Notifikasi dari ISP Billing System');
            });

            Log::info('Email notification sent', [
                'email' => $this->email,
                'subject' => $this->subject,
                'metadata' => $this->metadata,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Email notification exception', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'metadata' => $this->metadata,
            ]);

            return false;
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
        Log::critical('SendNotificationJob failed after all retries', [
            'channel' => $this->channel,
            'phone' => $this->phone,
            'email' => $this->email,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'metadata' => $this->metadata,
        ]);

        // TODO: Queue admin alert for failed notification
        // This could be implemented in a future task to notify admins
        // about critical notification failures
    }
}
