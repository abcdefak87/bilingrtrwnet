<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;

/**
 * Midtrans Payment Gateway Implementation
 *
 * This class implements the PaymentGatewayInterface for Midtrans payment gateway.
 * It uses the Midtrans Snap API for payment link generation and handles webhook notifications.
 */
class MidtransGateway implements PaymentGatewayInterface
{
    /**
     * Create a new MidtransGateway instance.
     */
    public function __construct()
    {
        // Configure Midtrans
        Config::$serverKey = config('payment-gateways.midtrans.server_key');
        Config::$clientKey = config('payment-gateways.midtrans.client_key');
        Config::$isProduction = config('payment-gateways.midtrans.is_production', false);
        Config::$isSanitized = config('payment-gateways.midtrans.is_sanitized', true);
        Config::$is3ds = config('payment-gateways.midtrans.is_3ds', true);
    }

    /**
     * Create a payment link for an invoice using Midtrans Snap.
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

            // Prepare transaction parameters
            $params = [
                'transaction_details' => [
                    'order_id' => 'INV-' . $invoice->id . '-' . time(),
                    'gross_amount' => (int) $invoice->amount,
                ],
                'customer_details' => [
                    'first_name' => $invoice->service->customer->name,
                    'email' => $invoice->service->customer->email ?? 'noreply@example.com',
                    'phone' => $invoice->service->customer->phone,
                ],
                'item_details' => [
                    [
                        'id' => 'PKG-' . $invoice->service->package->id,
                        'price' => (int) $invoice->amount,
                        'quantity' => 1,
                        'name' => $invoice->service->package->name . ' - ' . $invoice->service->package->speed,
                    ],
                ],
                'callbacks' => [
                    'finish' => url('/customer/invoices'),
                ],
            ];

            // Create Snap transaction
            $snapToken = Snap::getSnapToken($params);
            
            // Generate payment URL
            $paymentUrl = config('payment-gateways.midtrans.is_production')
                ? "https://app.midtrans.com/snap/v2/vtweb/{$snapToken}"
                : "https://app.sandbox.midtrans.com/snap/v2/vtweb/{$snapToken}";

            Log::info('Midtrans payment link created', [
                'invoice_id' => $invoice->id,
                'order_id' => $params['transaction_details']['order_id'],
                'amount' => $invoice->amount,
            ]);

            return $paymentUrl;
        } catch (\Exception $e) {
            Log::error('Failed to create Midtrans payment link', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create payment link: ' . $e->getMessage());
        }
    }

    /**
     * Verify the webhook signature from Midtrans.
     *
     * @param Request $request The webhook request
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        try {
            $serverKey = config('payment-gateways.midtrans.server_key');
            $orderId = $request->input('order_id');
            $statusCode = $request->input('status_code');
            $grossAmount = $request->input('gross_amount');
            $signatureKey = $request->input('signature_key');

            // Check if required fields are present
            if (!$orderId || !$statusCode || !$grossAmount || !$signatureKey) {
                Log::warning('Missing required fields in Midtrans webhook', [
                    'order_id' => $orderId,
                    'ip_address' => $request->ip(),
                ]);
                return false;
            }

            // Calculate expected signature
            $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

            $isValid = hash_equals($expectedSignature, $signatureKey);

            if (!$isValid) {
                Log::warning('Invalid Midtrans webhook signature', [
                    'order_id' => $orderId,
                    'ip_address' => $request->ip(),
                ]);
            }

            return $isValid;
        } catch (\Exception $e) {
            Log::error('Error verifying Midtrans webhook signature', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Parse webhook data from Midtrans.
     *
     * @param Request $request The webhook request
     * @return array Normalized payment data
     */
    public function parseWebhookData(Request $request): array
    {
        try {
            // In test environment or when data is already parsed, use request data directly
            // Otherwise, use Midtrans Notification class which reads from php://input
            if (app()->environment('testing') || $request->has('transaction_id')) {
                $transactionStatus = $request->input('transaction_status');
                $fraudStatus = $request->input('fraud_status');
                
                // Map Midtrans transaction status to our status
                $status = $this->mapTransactionStatus($transactionStatus, $fraudStatus);
                
                // Extract order_id from order_id field (format: INV-{id}-{timestamp} or just {id})
                $orderId = $request->input('order_id');
                
                // If order_id contains INV- prefix, extract the invoice ID
                if (str_starts_with($orderId, 'INV-')) {
                    $parts = explode('-', $orderId);
                    $invoiceId = $parts[1] ?? $orderId;
                } else {
                    $invoiceId = $orderId;
                }

                return [
                    'transaction_id' => $request->input('transaction_id'),
                    'order_id' => $invoiceId,
                    'status' => $status,
                    'amount' => (float) $request->input('gross_amount'),
                    'paid_at' => $status === 'success' ? now()->toIso8601String() : null,
                    'metadata' => [
                        'order_id' => $orderId,
                        'payment_type' => $request->input('payment_type'),
                        'transaction_time' => $request->input('transaction_time'),
                        'fraud_status' => $fraudStatus,
                    ],
                ];
            }
            
            // Production environment: use Midtrans Notification class
            $notification = new Notification();

            // Map Midtrans transaction status to our status
            $status = $this->mapTransactionStatus(
                $notification->transaction_status,
                $notification->fraud_status ?? null
            );
            
            // Extract order_id from order_id field
            $orderId = $notification->order_id;
            
            // If order_id contains INV- prefix, extract the invoice ID
            if (str_starts_with($orderId, 'INV-')) {
                $parts = explode('-', $orderId);
                $invoiceId = $parts[1] ?? $orderId;
            } else {
                $invoiceId = $orderId;
            }

            return [
                'transaction_id' => $notification->transaction_id,
                'order_id' => $invoiceId,
                'status' => $status,
                'amount' => (float) $notification->gross_amount,
                'paid_at' => $status === 'success' ? now()->toIso8601String() : null,
                'metadata' => [
                    'order_id' => $orderId,
                    'payment_type' => $notification->payment_type,
                    'transaction_time' => $notification->transaction_time,
                    'fraud_status' => $notification->fraud_status ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error parsing Midtrans webhook data', [
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to parse webhook data: ' . $e->getMessage());
        }
    }

    /**
     * Get the payment status for a transaction from Midtrans.
     *
     * @param string $transactionId The transaction ID from Midtrans
     * @return string The payment status (pending|success|failed|expired)
     * @throws \Exception If status check fails
     */
    public function getPaymentStatus(string $transactionId): string
    {
        try {
            $status = \Midtrans\Transaction::status($transactionId);

            return $this->mapTransactionStatus(
                $status->transaction_status,
                $status->fraud_status ?? null
            );
        } catch (\Exception $e) {
            Log::error('Failed to get Midtrans payment status', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to get payment status: ' . $e->getMessage());
        }
    }

    /**
     * Map Midtrans transaction status to our standard status.
     *
     * @param string $transactionStatus The Midtrans transaction status
     * @param string|null $fraudStatus The Midtrans fraud status
     * @return string Our standard status (pending|success|failed|expired)
     */
    protected function mapTransactionStatus(string $transactionStatus, ?string $fraudStatus = null): string
    {
        // Handle fraud status first
        if ($fraudStatus === 'deny') {
            return 'failed';
        }

        // Map transaction status
        return match ($transactionStatus) {
            'capture', 'settlement' => 'success',
            'pending' => 'pending',
            'deny', 'cancel', 'failure' => 'failed',
            'expire' => 'expired',
            default => 'pending',
        };
    }
}
