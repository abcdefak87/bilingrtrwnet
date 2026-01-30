<?php

namespace App\Jobs;

use App\Models\Service;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job to send WhatsApp and Email notification to customer when service is restored.
 *
 * The notification includes:
 * - Information that service has been restored
 * - Confirmation of payment received
 * - Service details (package, speed)
 * - Next billing date
 * - Thank you message
 *
 * Requirements: 5.9, 7.1, 7.2, 7.3, 7.4
 */
class SendRestorationNotificationJob implements ShouldQueue
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
     * The service ID for which to send notification.
     *
     * @var int
     */
    public int $serviceId;

    /**
     * Create a new job instance.
     *
     * @param int $serviceId
     */
    public function __construct(int $serviceId)
    {
        $this->serviceId = $serviceId;
    }

    /**
     * Execute the job.
     *
     * @param WhatsAppService $whatsappService
     * @return void
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        Log::info('Processing restoration notification', [
            'service_id' => $this->serviceId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Load service with related data
            $service = Service::with(['customer', 'package'])->findOrFail($this->serviceId);

            // Build notification message
            $message = $this->buildNotificationMessage($service);

            // Send WhatsApp notification
            $whatsappSuccess = false;
            if (!empty($service->customer->phone)) {
                $whatsappSuccess = $whatsappService->sendMessage(
                    $service->customer->phone,
                    $message
                );

                if ($whatsappSuccess) {
                    Log::info('Restoration WhatsApp notification sent', [
                        'service_id' => $this->serviceId,
                        'customer_id' => $service->customer_id,
                        'phone' => $service->customer->phone,
                    ]);
                }
            }

            // Send Email notification
            $emailSuccess = false;
            if (!empty($service->customer->email)) {
                try {
                    Mail::raw($message, function ($mail) use ($service) {
                        $mail->to($service->customer->email)
                            ->subject('Layanan Telah Diaktifkan Kembali - ISP Billing System');
                    });

                    $emailSuccess = true;

                    Log::info('Restoration email notification sent', [
                        'service_id' => $this->serviceId,
                        'customer_id' => $service->customer_id,
                        'email' => $service->customer->email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send restoration email', [
                        'service_id' => $this->serviceId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Check if at least one notification succeeded
            if (!$whatsappSuccess && !$emailSuccess) {
                throw new \Exception('All notification channels failed for restoration notification');
            }

            Log::info('Restoration notification completed', [
                'service_id' => $this->serviceId,
                'whatsapp_success' => $whatsappSuccess,
                'email_success' => $emailSuccess,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send restoration notification', [
                'service_id' => $this->serviceId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Build the notification message for restoration.
     *
     * @param Service $service
     * @return string
     */
    protected function buildNotificationMessage(Service $service): string
    {
        $customerName = $service->customer->name;
        $packageName = $service->package->name;
        $packageSpeed = $service->package->speed;
        $expiryDate = $service->expiry_date ? $service->expiry_date->format('d/m/Y') : 'N/A';

        $message = <<<MSG
*LAYANAN TELAH DIAKTIFKAN KEMBALI*

Yth. Bapak/Ibu {$customerName},

Terima kasih atas pembayaran Anda! 🎉

Layanan internet Anda telah berhasil diaktifkan kembali dengan detail sebagai berikut:

*Detail Layanan:*
- Paket: {$packageName}
- Kecepatan: {$packageSpeed}
- Status: Aktif
- Berlaku hingga: {$expiryDate}

Anda sekarang dapat menikmati layanan internet dengan kecepatan penuh.

*Informasi Penting:*
- Pastikan untuk melakukan pembayaran tepat waktu agar layanan tetap aktif
- Anda akan menerima notifikasi invoice untuk periode berikutnya

Jika Anda memiliki pertanyaan atau memerlukan bantuan, silakan hubungi customer service kami.

Terima kasih telah mempercayai layanan kami! 🙏

---
ISP Billing System
MSG;

        return $message;
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('SendRestorationNotificationJob failed after all retries', [
            'service_id' => $this->serviceId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // TODO: Queue admin alert for failed notification
        // AdminAlertJob::dispatch('restoration_notification_failed', [
        //     'service_id' => $this->serviceId,
        // ]);
    }
}
