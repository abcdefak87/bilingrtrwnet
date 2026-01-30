<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tripay Payment Gateway Implementation
 *
 * This class implements the PaymentGatewayInterface for Tripay payment gateway.
 * It uses the Tripay Closed Payment API for payment link generation and handles webhook callbacks.
 */
class TripayGateway implements PaymentGatewayInterface
{
    /**
     * Tripay API base URL.
     */
    protected string $baseUrl;

    /**
     * Tripay API key.
     */
    protected string $apiKey;

    /**
     * Tripay private key.
     */
    protected string $privateKey;

    /**
     * Tripay merchant code.
     */
    protected string $merchantCode;

    /**
     * Create a new TripayGateway instance.
     */
    public function __construct()
    {
        $this->baseUrl = config('payment-gateways.tripay.base_url');
        $this->apiKey = config('payment-gateways.tripay.api_key');
        $this->privateKey = config('payment-gateways.tripay.private_key');
        $this->merchantCode = config('payment-gateways.tripay.merchant_code');
    }

    /**
     * Create a payment link for an invoice using Tripay Closed Payment API.
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

            // Generate unique merchant reference
            $merchantRef = 'INV-' . $invoice->id . '-' . time();

            // Calculate signature
            $amount = (int) $invoice->amount;
            $signature = hash_hmac('sha256', $this->merchantCode . $merchantRef . $amount, $this->privateKey);

            // Prepare transaction data
            $data = [
                'method' => 'BRIVA', // Default payment method, can be configured
                'merchant_ref' => $merchantRef,
                'amount' => $amount,
                'customer_name' => $invoice->service->customer->name,
                'customer_email' => $invoice->service->customer->email ?? 'noreply@example.com',
                'customer_phone' => $invoice->service->customer->phone,
                'order_items' => [
                    [
                        'name' => $invoice->service->package->name . ' - ' . $invoice->service->package->speed,
                        'price' => $amount,
                        'quantity' => 1,
                    ],
                ],
                'return_url' => url('/customer/invoices'),
                'expired_time' => (time() + (24 * 60 * 60)), // 24 hours
                'signature' => $signature,
            ];

            // Make API request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/transaction/create', $data);

            if (!$response->successful()) {
                throw new \Exception('Tripay API error: ' . $response->body());
            }

            $result = $response->json();

            if (!$result['success']) {
                throw new \Exception('Tripay transaction creation failed: ' . ($result['message'] ?? 'Unknown error'));
            }

            Log::info('Tripay payment link created', [
                'invoice_id' => $invoice->id,
                'merchant_ref' => $merchantRef,
                'reference' => $result['data']['reference'] ?? null,
                'amount' => $invoice->amount,
            ]);

            return $result['data']['checkout_url'] ?? $result['data']['pay_url'] ?? '';
        } catch (\Exception $e) {
            Log::error('Failed to create Tripay payment link', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create payment link: ' . $e->getMessage());
        }
    }

    /**
     * Verify the webhook signature from Tripay.
     *
     * @param Request $request The webhook request
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        try {
            $callbackSignature = $request->header('X-Callback-Signature');
            
            if (!$callbackSignature) {
                Log::warning('Missing Tripay callback signature header');
                return false;
            }

            // Get raw JSON body
            $json = $request->getContent();

            // Calculate expected signature
            $expectedSignature = hash_hmac('sha256', $json, $this->privateKey);

            $isValid = hash_equals($expectedSignature, $callbackSignature);

            if (!$isValid) {
                Log::warning('Invalid Tripay webhook signature', [
                    'merchant_ref' => $request->input('merchant_ref'),
                    'ip_address' => $request->ip(),
                ]);
            }

            return $isValid;
        } catch (\Exception $e) {
            Log::error('Error verifying Tripay webhook signature', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Parse webhook data from Tripay.
     *
     * @param Request $request The webhook request
     * @return array Normalized payment data
     */
    public function parseWebhookData(Request $request): array
    {
        try {
            $data = $request->all();

            // Map Tripay status to our status
            $status = $this->mapTransactionStatus($data['status'] ?? 'UNPAID');
            
            // Extract invoice ID from merchant_ref (format: INV-{id}-{timestamp} or just {id})
            $merchantRef = $data['merchant_ref'] ?? '';
            $invoiceId = $merchantRef;
            
            if (str_starts_with($merchantRef, 'INV-')) {
                $parts = explode('-', $merchantRef);
                $invoiceId = $parts[1] ?? $merchantRef;
            }

            return [
                'transaction_id' => $data['reference'] ?? '',
                'order_id' => $invoiceId,
                'status' => $status,
                'amount' => (float) ($data['amount'] ?? 0),
                'paid_at' => $status === 'success' && isset($data['paid_at']) 
                    ? date('c', $data['paid_at']) 
                    : null,
                'metadata' => [
                    'merchant_ref' => $merchantRef,
                    'payment_method' => $data['payment_method'] ?? null,
                    'payment_name' => $data['payment_name'] ?? null,
                    'fee_merchant' => $data['fee_merchant'] ?? null,
                    'fee_customer' => $data['fee_customer'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error parsing Tripay webhook data', [
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to parse webhook data: ' . $e->getMessage());
        }
    }

    /**
     * Get the payment status for a transaction from Tripay.
     *
     * @param string $transactionId The reference from Tripay
     * @return string The payment status (pending|success|failed|expired)
     * @throws \Exception If status check fails
     */
    public function getPaymentStatus(string $transactionId): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/transaction/detail', [
                'reference' => $transactionId,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Tripay API error: ' . $response->body());
            }

            $result = $response->json();

            if (!$result['success']) {
                throw new \Exception('Failed to get transaction detail: ' . ($result['message'] ?? 'Unknown error'));
            }

            return $this->mapTransactionStatus($result['data']['status'] ?? 'UNPAID');
        } catch (\Exception $e) {
            Log::error('Failed to get Tripay payment status', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to get payment status: ' . $e->getMessage());
        }
    }

    /**
     * Map Tripay transaction status to our standard status.
     *
     * @param string $tripayStatus The Tripay transaction status
     * @return string Our standard status (pending|success|failed|expired)
     */
    protected function mapTransactionStatus(string $tripayStatus): string
    {
        return match (strtoupper($tripayStatus)) {
            'PAID' => 'success',
            'UNPAID' => 'pending',
            'EXPIRED' => 'expired',
            'FAILED', 'REFUND' => 'failed',
            default => 'pending',
        };
    }
}
