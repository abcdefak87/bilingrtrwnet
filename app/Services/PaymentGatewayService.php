<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Invoice;
use App\Services\PaymentGateways\MidtransGateway;
use App\Services\PaymentGateways\XenditGateway;
use App\Services\PaymentGateways\TripayGateway;
use Illuminate\Support\Facades\Log;

/**
 * Payment Gateway Service (Factory)
 *
 * This service acts as a factory for payment gateway implementations.
 * It instantiates the appropriate gateway based on configuration and
 * provides a unified interface for payment operations.
 */
class PaymentGatewayService
{
    /**
     * The active payment gateway instance.
     */
    protected PaymentGatewayInterface $gateway;

    /**
     * The name of the active gateway.
     */
    protected string $gatewayName;

    /**
     * Create a new PaymentGatewayService instance.
     *
     * @param string|null $gatewayName The gateway to use (midtrans|xendit|tripay)
     * @throws \Exception If gateway is not supported
     */
    public function __construct(?string $gatewayName = null)
    {
        $this->gatewayName = $gatewayName ?? config('payment-gateways.default', 'midtrans');
        $this->gateway = $this->createGateway($this->gatewayName);
    }

    /**
     * Create a gateway instance based on the gateway name.
     *
     * @param string $gatewayName The gateway name (midtrans|xendit|tripay)
     * @return PaymentGatewayInterface The gateway instance
     * @throws \Exception If gateway is not supported
     */
    protected function createGateway(string $gatewayName): PaymentGatewayInterface
    {
        return match (strtolower($gatewayName)) {
            'midtrans' => new MidtransGateway(),
            'xendit' => new XenditGateway(),
            'tripay' => new TripayGateway(),
            default => throw new \Exception("Unsupported payment gateway: {$gatewayName}"),
        };
    }

    /**
     * Get the active payment gateway instance.
     *
     * @return PaymentGatewayInterface The gateway instance
     */
    public function getGateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }

    /**
     * Get the name of the active gateway.
     *
     * @return string The gateway name
     */
    public function getGatewayName(): string
    {
        return $this->gatewayName;
    }

    /**
     * Create a payment link for an invoice.
     *
     * This method delegates to the active gateway to generate a payment link.
     *
     * @param Invoice $invoice The invoice to create a payment link for
     * @return string The payment link URL
     * @throws \Exception If payment link creation fails
     */
    public function createPaymentLink(Invoice $invoice): string
    {
        try {
            Log::info('Creating payment link', [
                'invoice_id' => $invoice->id,
                'gateway' => $this->gatewayName,
                'amount' => $invoice->amount,
            ]);

            $paymentLink = $this->gateway->createPaymentLink($invoice);

            // Update invoice with payment link
            $invoice->update(['payment_link' => $paymentLink]);

            Log::info('Payment link created successfully', [
                'invoice_id' => $invoice->id,
                'gateway' => $this->gatewayName,
            ]);

            return $paymentLink;
        } catch (\Exception $e) {
            Log::error('Failed to create payment link', [
                'invoice_id' => $invoice->id,
                'gateway' => $this->gatewayName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Verify webhook signature.
     *
     * @param \Illuminate\Http\Request $request The webhook request
     * @param string|null $gatewayName The gateway name (optional, uses default if not provided)
     * @return bool True if signature is valid, false otherwise
     */
    public function verifyWebhookSignature($request, ?string $gatewayName = null): bool
    {
        // If gateway name is provided and different from current, create new gateway instance
        if ($gatewayName && strtolower($gatewayName) !== strtolower($this->gatewayName)) {
            $gateway = $this->createGateway($gatewayName);
            return $gateway->verifyWebhookSignature($request);
        }

        return $this->gateway->verifyWebhookSignature($request);
    }

    /**
     * Parse webhook data.
     *
     * @param \Illuminate\Http\Request $request The webhook request
     * @param string|null $gatewayName The gateway name (optional, uses default if not provided)
     * @return array Normalized payment data
     */
    public function parseWebhookData($request, ?string $gatewayName = null): array
    {
        // If gateway name is provided and different from current, create new gateway instance
        if ($gatewayName && strtolower($gatewayName) !== strtolower($this->gatewayName)) {
            $gateway = $this->createGateway($gatewayName);
            return $gateway->parseWebhookData($request);
        }

        return $this->gateway->parseWebhookData($request);
    }

    /**
     * Get payment status.
     *
     * @param string $transactionId The transaction ID
     * @return string The payment status (pending|success|failed|expired)
     */
    public function getPaymentStatus(string $transactionId): string
    {
        return $this->gateway->getPaymentStatus($transactionId);
    }
}
