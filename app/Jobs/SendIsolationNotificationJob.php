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
 * Job to send WhatsApp and Email notification to customer when service is isolated.
 *
 * The notification includes:
 * - Information that service has been isolated
 * - Reason: unpaid invoice
 * - Payment instructions
 * - Payment link
 * - Contact information for support
 *
 * Requirements: 5.6, 7.1, 7.2, 7.3, 7.4
 */
class SendIsolationNotificationJob implements ShouldQueue
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
        Log::info('Processing isolation notification', [
            'service_id' => $this->serviceId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Load service with related data
            $service = Service::with(['customer', 'package', 'invoices' => function ($query) {
                $query->where('status', 'unpaid')->orderBy('due_date', 'asc');
            }])->findOrFail($this->serviceId);

            // Get the oldest unpaid invoice
            $unpaidInvoice = $service->invoices->first();

            if (!$unpaidInvoice) {
                Log::warning('No unpaid invoice found for isolated service', [
                    'service_id' => $this->serviceId,
                ]);
                return;
            }

            // Build notification message
            $message = $this->buildNotificationMessage($service, $unpaidInvoice);

            // Send WhatsApp notification
            $whatsappSuccess = false;
            if (!empty($service->customer->phone)) {
                $whatsappSuccess = $whatsappService->sendMessage(
                    $service->customer->phone,
                    $message
                );

                if ($whatsappSuccess) {
                    Log::info('Isolation WhatsApp notification sent', [
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
                            ->subject('Pemberitahuan Isolasi Layanan - ISP Billing System');
                    });

                    $emailSuccess = true;

                    Log::info('Isolation email notification sent', [
                        'service_id' => $this->serviceId,
                        'customer_id' => $service->customer_id,
                        'email' => $service->customer->email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send isolation email', [
                        'service_id' => $this->serviceId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Check if at least one notification succeeded
            if (!$whatsappSuccess && !$emailSuccess) {
                throw new \Exception('All notification channels failed for isolation notification');
            }

            Log::info('Isolation notification completed', [
                'service_id' => $this->serviceId,
                'whatsapp_success' => $whatsappSuccess,
                'email_success' => $emailSuccess,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send isolation notification', [
                'service_id' => $this->serviceId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Build the notification message for isolation.
     *
     * @param Service $service
     * @param \App\Models\Invoice $invoice
     * @return string
     */
    protected function buildNotificationMessage(Service $service, $invoice): string
    {
        $customerName = $service->customer->name;
        $packageName = $service->package->name;
        $invoiceAmount = number_format($invoice->amount, 0, ',', '.');
        $dueDate = $invoice->due_date->format('d/m/Y');
        $paymentLink = $invoice->payment_link ?? 'Hubungi admin untuk link pembayaran';

        $message = <<<MSG
*PEMBERITAHUAN ISOLASI LAYANAN*

Yth. Bapak/Ibu {$customerName},

Layanan internet Anda (Paket: {$packageName}) telah diisolir karena terdapat tagihan yang belum dibayar.

*Detail Tagihan:*
- Nomor Invoice: #{$invoice->id}
- Jumlah: Rp {$invoiceAmount}
- Jatuh Tempo: {$dueDate}
- Status: Belum Dibayar

*Instruksi Pembayaran:*
Silakan lakukan pembayaran melalui link berikut:
{$paymentLink}

Setelah pembayaran dikonfirmasi, layanan Anda akan segera diaktifkan kembali secara otomatis.

*Catatan:*
Selama masa isolasi, kecepatan internet Anda dibatasi hingga pembayaran diterima.

Jika Anda memiliki pertanyaan atau memerlukan bantuan, silakan hubungi customer service kami.

Terima kasih atas perhatian dan kerjasamanya.

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
        Log::critical('SendIsolationNotificationJob failed after all retries', [
            'service_id' => $this->serviceId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // TODO: Queue admin alert for failed notification
        // AdminAlertJob::dispatch('isolation_notification_failed', [
        //     'service_id' => $this->serviceId,
        // ]);
    }
}
