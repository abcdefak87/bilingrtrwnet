<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job to send payment confirmation notification via WhatsApp and Email.
 *
 * The notification includes:
 * - Payment confirmation message
 * - Invoice details
 * - Payment amount and transaction ID
 * - Service extension information
 * - Thank you message
 *
 * Requirements: 4.6, 7.1, 7.2, 7.3, 7.4
 */
class SendPaymentConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The invoice instance.
     *
     * @var Invoice
     */
    public Invoice $invoice;

    /**
     * The payment instance.
     *
     * @var Payment
     */
    public Payment $payment;

    /**
     * Create a new job instance.
     */
    public function __construct(Invoice $invoice, Payment $payment)
    {
        $this->invoice = $invoice;
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     *
     * Sends payment confirmation via WhatsApp and Email.
     *
     * @param WhatsAppService $whatsappService
     */
    public function handle(WhatsAppService $whatsappService): void
    {
        Log::info('Processing payment confirmation notification', [
            'invoice_id' => $this->invoice->id,
            'payment_id' => $this->payment->id,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Load relationships
            $this->invoice->load(['service.customer', 'service.package']);

            $customer = $this->invoice->service->customer;
            $package = $this->invoice->service->package;

            if (!$customer) {
                Log::error('Cannot send payment confirmation: customer not found', [
                    'invoice_id' => $this->invoice->id,
                ]);
                return;
            }

            // Prepare payment details message
            $message = $this->buildConfirmationMessage($customer, $package);

            // Send WhatsApp notification
            $whatsappSuccess = false;
            if (!empty($customer->phone)) {
                $whatsappSuccess = $whatsappService->sendMessage(
                    $customer->phone,
                    $message
                );

                if ($whatsappSuccess) {
                    Log::info('Payment confirmation WhatsApp sent', [
                        'invoice_id' => $this->invoice->id,
                        'payment_id' => $this->payment->id,
                        'customer_id' => $customer->id,
                        'phone' => $customer->phone,
                    ]);
                }
            }

            // Send Email notification
            $emailSuccess = false;
            if (!empty($customer->email)) {
                try {
                    Mail::raw($message, function ($mail) use ($customer) {
                        $mail->to($customer->email)
                            ->subject('Konfirmasi Pembayaran - ISP Billing System');
                    });

                    $emailSuccess = true;

                    Log::info('Payment confirmation email sent', [
                        'invoice_id' => $this->invoice->id,
                        'payment_id' => $this->payment->id,
                        'customer_id' => $customer->id,
                        'email' => $customer->email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send payment confirmation email', [
                        'invoice_id' => $this->invoice->id,
                        'payment_id' => $this->payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Check if at least one notification succeeded
            if (!$whatsappSuccess && !$emailSuccess) {
                throw new \Exception('All notification channels failed for payment confirmation');
            }

            Log::info('Payment confirmation notification completed', [
                'invoice_id' => $this->invoice->id,
                'payment_id' => $this->payment->id,
                'whatsapp_success' => $whatsappSuccess,
                'email_success' => $emailSuccess,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation', [
                'invoice_id' => $this->invoice->id,
                'payment_id' => $this->payment->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Build payment confirmation message.
     *
     * @param \App\Models\Customer $customer
     * @param \App\Models\Package|null $package
     * @return string
     */
    protected function buildConfirmationMessage($customer, $package): string
    {
        $packageName = $package ? $package->name : 'N/A';
        $amount = number_format($this->payment->amount, 0, ',', '.');
        $transactionId = $this->payment->transaction_id;
        $paidAt = $this->invoice->paid_at->format('d/m/Y H:i');
        $expiryDate = $this->invoice->service->expiry_date 
            ? $this->invoice->service->expiry_date->format('d/m/Y')
            : 'N/A';

        $message = <<<MSG
*KONFIRMASI PEMBAYARAN*

Yth. Bapak/Ibu {$customer->name},

Pembayaran Anda telah berhasil dikonfirmasi! 🎉

*Detail Pembayaran:*
- Paket: {$packageName}
- Jumlah: Rp {$amount}
- ID Transaksi: {$transactionId}
- Tanggal: {$paidAt}

*Status Layanan:*
Layanan internet Anda telah diperpanjang hingga {$expiryDate}.

Terima kasih atas pembayaran Anda. Anda dapat terus menikmati layanan internet dengan kecepatan penuh.

Jika Anda memiliki pertanyaan, silakan hubungi customer service kami.

Salam,
---
ISP Billing System
MSG;

        return $message;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // Exponential backoff: 30s, 60s, 120s
        return [30, 60, 120];
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('SendPaymentConfirmationJob failed after all retries', [
            'invoice_id' => $this->invoice->id,
            'payment_id' => $this->payment->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // TODO: Queue admin alert for failed notification
    }
}
