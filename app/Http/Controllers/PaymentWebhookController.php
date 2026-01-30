<?php

namespace App\Http\Controllers;

use App\Jobs\SendPaymentConfirmationJob;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle incoming payment webhook from payment gateway.
     *
     * This method:
     * 1. Verifies webhook signature for authentication
     * 2. Parses webhook data
     * 3. Updates invoice status
     * 4. Extends service expiry_date
     * 5. Queues payment confirmation notification
     *
     * @param Request $request The webhook request
     * @param string $gateway The payment gateway name (midtrans, xendit, tripay)
     * @return JsonResponse
     */
    public function handle(Request $request, string $gateway): JsonResponse
    {
        // Log webhook attempt for audit trail
        Log::info('Payment webhook received', [
            'gateway' => $gateway,
            'ip' => $request->ip(),
            'payload' => $request->all(),
        ]);

        try {
            $paymentGatewayService = new PaymentGatewayService();

            // Step 1: Verify webhook signature (Requirement 4.2)
            if (!$paymentGatewayService->verifyWebhookSignature($request, $gateway)) {
                // Requirement 4.3: Log security warning for invalid signature
                Log::warning('Invalid webhook signature detected', [
                    'gateway' => $gateway,
                    'ip' => $request->ip(),
                    'payload' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature',
                ], 403);
            }

            // Step 2: Parse webhook data
            $webhookData = $paymentGatewayService->parseWebhookData($request, $gateway);

            // Validate required fields
            if (!isset($webhookData['transaction_id']) || !isset($webhookData['status'])) {
                Log::error('Invalid webhook data structure', [
                    'gateway' => $gateway,
                    'data' => $webhookData,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook data',
                ], 400);
            }

            // Check if payment already processed (idempotency)
            $existingPayment = Payment::where('transaction_id', $webhookData['transaction_id'])
                ->where('payment_gateway', $gateway)
                ->first();

            if ($existingPayment) {
                Log::info('Duplicate webhook received, payment already processed', [
                    'transaction_id' => $webhookData['transaction_id'],
                    'gateway' => $gateway,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment already processed',
                ], 200);
            }

            // Only process successful payments
            if ($webhookData['status'] !== 'success') {
                Log::info('Webhook received for non-successful payment', [
                    'transaction_id' => $webhookData['transaction_id'],
                    'status' => $webhookData['status'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Webhook received',
                ], 200);
            }

            // Find invoice by external_id or invoice_id from webhook data
            $invoice = $this->findInvoiceFromWebhookData($webhookData);

            if (!$invoice) {
                Log::error('Invoice not found for webhook', [
                    'gateway' => $gateway,
                    'webhook_data' => $webhookData,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], 404);
            }

            // Process payment in a database transaction
            DB::transaction(function () use ($invoice, $webhookData, $gateway) {
                // Step 3: Create payment record
                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'payment_gateway' => $gateway,
                    'transaction_id' => $webhookData['transaction_id'],
                    'amount' => $webhookData['amount'],
                    'status' => 'success',
                    'metadata' => $webhookData['metadata'] ?? [],
                ]);

                // Step 4: Update invoice status (Requirement 4.4)
                $invoice->markAsPaid($payment);

                // Step 5: Extend service expiry_date (Requirement 4.5)
                $service = $invoice->service;
                if ($service) {
                    $billingCycleDays = config('billing.cycle_days', 30);
                    $service->extendExpiry($billingCycleDays);

                    // If service was isolated, trigger reactivation (Requirement 4.7)
                    if ($service->status === 'isolated') {
                        // Queue restoration job
                        \App\Jobs\RestoreServiceJob::dispatch($service);
                    }
                }

                // Step 6: Queue payment confirmation notification (Requirement 4.6)
                SendPaymentConfirmationJob::dispatch($invoice, $payment);
            });

            Log::info('Payment webhook processed successfully', [
                'gateway' => $gateway,
                'transaction_id' => $webhookData['transaction_id'],
                'invoice_id' => $invoice->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing payment webhook', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Find invoice from webhook data.
     *
     * Different gateways send invoice reference in different fields.
     *
     * @param array $webhookData
     * @return Invoice|null
     */
    protected function findInvoiceFromWebhookData(array $webhookData): ?Invoice
    {
        // Try to find by external_id (most common)
        if (isset($webhookData['external_id'])) {
            $invoice = Invoice::find($webhookData['external_id']);
            if ($invoice) {
                return $invoice;
            }
        }

        // Try to find by invoice_id
        if (isset($webhookData['invoice_id'])) {
            $invoice = Invoice::find($webhookData['invoice_id']);
            if ($invoice) {
                return $invoice;
            }
        }

        // Try to find by order_id
        if (isset($webhookData['order_id'])) {
            $invoice = Invoice::find($webhookData['order_id']);
            if ($invoice) {
                return $invoice;
            }
        }

        return null;
    }
}
