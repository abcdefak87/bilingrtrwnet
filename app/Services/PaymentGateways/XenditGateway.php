<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;

/**
 * Xendit Payment Gateway Implementation
 *
 * This class implements the PaymentGatewayInterface for Xendit payment gateway.
 * It uses the Xendit Invoice API for payment link generation and handles webhook callbacks.
 */
class XenditGateway implements PaymentGatewayInterface
{
    /**
     * Xendit Invoice API instance.
     */
    protected InvoiceApi $invoiceApi;

    /**
     * Create a new XenditGateway instance.
     */
    public function __construct()
    {
        // Configure Xendit
        Configuration::setXenditKey(config('payment-gateways.xendit.secret_key'));
        
        $this->invoiceApi = new InvoiceApi();
    }

    /**
     * Create a payment link for an invoice using Xendit Invoice API.
     *
     * @param Invoice $invoice The invoice to create a payment link for
     * @return string The payment link URL
     * @throws \Exception If payment link creation fails
     */
    public function createPaymentLink(Invoice $invoice): string
    {
        try {
            // Load relationships
            $invoice->load(['service.customer', 'service.package']);

            // Prepare invoice parameters
            $createInvoiceRequest = new CreateInvoiceRequest([
                'external_id' => 'INV-' . $invoice->id . '-' . time(),
                'amount' => (float) $invoice->amount,
                'payer_email' => $invoice->service->customer->email ?? 'noreply@example.com',
                'description' => 'Pembayaran ' . $invoice->service->package->name . ' - ' . $invoice->service->package->speed,
                'invoice_duration' => 86400, // 24 hours
                'currency' => 'IDR',
                'reminder_time' => 1,
                'success_redirect_url' => url('/customer/invoices'),
                'failure_redirect_url' => url('/customer/invoices'),
                'customer' => [
                    'given_names' => $invoice->service->customer->name,
                    'mobile_number' => $invoice->service->customer->phone,
                ],
                'items' => [
                    [
                        'name' => $invoice->service->package->name . ' - ' . $invoice->service->package->speed,
                        'quantity' => 1,
                        'price' => (float) $invoice->amount,
                    ],
                ],
            ]);

            // Create invoice
            $xenditInvoice = $this->invoiceApi->createInvoice($createInvoiceRequest);

            Log::info('Xendit payment link created', [
                'invoice_id' => $invoice->id,
                'external_id' => $createInvoiceRequest['external_id'],
                'xendit_invoice_id' => $xenditInvoice['id'],
                'amount' => $invoice->amount,
            ]);

            return $xenditInvoice['invoice_url'];
        } catch (\Exception $e) {
            Log::error('Failed to create Xendit payment link', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create payment link: ' . $e->getMessage());
        }
    }

    /**
     * Verify the webhook callback token from Xendit.
     *
     * @param Request $request The webhook request
     * @return bool True if callback token is valid, false otherwise
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        try {
            $callbackToken = $request->header('X-Callback-Token');
            $expectedToken = config('payment-gateways.xendit.webhook_token');

            $isValid = hash_equals($expectedToken, $callbackToken ?? '');

            if (!$isValid) {
                Log::warning('Invalid Xendit webhook callback token', [
                    'external_id' => $request->input('external_id'),
                    'ip_address' => $request->ip(),
                ]);
            }

            return $isValid;
        } catch (\Exception $e) {
            Log::error('Error verifying Xendit webhook signature', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Parse webhook data from Xendit.
     *
     * @param Request $request The webhook request
     * @return array Normalized payment data
     */
    public function parseWebhookData(Request $request): array
    {
        try {
            $data = $request->all();

            // Map Xendit status to our status
            $status = $this->mapInvoiceStatus($data['status'] ?? 'PENDING');
            
            // Extract invoice ID from external_id (format: INV-{id}-{timestamp} or just {id})
            $externalId = $data['external_id'] ?? '';
            $invoiceId = $externalId;
            
            if (str_starts_with($externalId, 'INV-')) {
                $parts = explode('-', $externalId);
                $invoiceId = $parts[1] ?? $externalId;
            }

            return [
                'transaction_id' => $data['id'] ?? '',
                'external_id' => $invoiceId,
                'status' => $status,
                'amount' => (float) ($data['amount'] ?? 0),
                'paid_at' => $status === 'success' && isset($data['paid_at']) 
                    ? date('c', strtotime($data['paid_at'])) 
                    : null,
                'metadata' => [
                    'external_id' => $externalId,
                    'payment_method' => $data['payment_method'] ?? null,
                    'payment_channel' => $data['payment_channel'] ?? null,
                    'paid_amount' => $data['paid_amount'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error parsing Xendit webhook data', [
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to parse webhook data: ' . $e->getMessage());
        }
    }

    /**
     * Get the payment status for a transaction from Xendit.
     *
     * @param string $transactionId The invoice ID from Xendit
     * @return string The payment status (pending|success|failed|expired)
     * @throws \Exception If status check fails
     */
    public function getPaymentStatus(string $transactionId): string
    {
        try {
            $invoice = $this->invoiceApi->getInvoiceById($transactionId);

            return $this->mapInvoiceStatus($invoice['status']);
        } catch (\Exception $e) {
            Log::error('Failed to get Xendit payment status', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to get payment status: ' . $e->getMessage());
        }
    }

    /**
     * Map Xendit invoice status to our standard status.
     *
     * @param string $xenditStatus The Xendit invoice status
     * @return string Our standard status (pending|success|failed|expired)
     */
    protected function mapInvoiceStatus(string $xenditStatus): string
    {
        return match (strtoupper($xenditStatus)) {
            'PAID', 'SETTLED' => 'success',
            'PENDING' => 'pending',
            'EXPIRED' => 'expired',
            default => 'failed',
        };
    }
}
