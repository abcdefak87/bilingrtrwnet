<?php

namespace App\Contracts;

use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Payment Gateway Interface
 *
 * This interface defines the contract that all payment gateway implementations
 * must follow. It provides methods for creating payment links, verifying webhooks,
 * parsing webhook data, and checking payment status.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a payment link for an invoice.
     *
     * This method generates a unique payment link via the payment gateway
     * that customers can use to pay their invoice.
     *
     * @param Invoice $invoice The invoice to create a payment link for
     * @return string The payment link URL
     * @throws \Exception If payment link creation fails
     */
    public function createPaymentLink(Invoice $invoice): string;

    /**
     * Verify the webhook signature from the payment gateway.
     *
     * This method validates that the webhook request is authentic and
     * comes from the payment gateway by verifying its signature.
     *
     * @param Request $request The webhook request
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Parse webhook data from the payment gateway.
     *
     * This method extracts and normalizes payment data from the webhook
     * request into a standard format.
     *
     * @param Request $request The webhook request
     * @return array Normalized payment data with keys:
     *               - transaction_id: string
     *               - status: string (pending|success|failed|expired)
     *               - amount: float
     *               - paid_at: string|null (ISO 8601 datetime)
     *               - metadata: array (gateway-specific data)
     */
    public function parseWebhookData(Request $request): array;

    /**
     * Get the payment status for a transaction.
     *
     * This method queries the payment gateway to get the current
     * status of a transaction.
     *
     * @param string $transactionId The transaction ID from the gateway
     * @return string The payment status (pending|success|failed|expired)
     * @throws \Exception If status check fails
     */
    public function getPaymentStatus(string $transactionId): string;
}
