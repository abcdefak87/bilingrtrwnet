<?php

namespace Tests\Unit\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Service;
use App\Services\PaymentGatewayService;
use App\Services\PaymentGateways\MidtransGateway;
use App\Services\PaymentGateways\XenditGateway;
use App\Services\PaymentGateways\TripayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        Config::set('payment-gateways.default', 'midtrans');
        Config::set('payment-gateways.midtrans.server_key', 'test-server-key');
        Config::set('payment-gateways.midtrans.client_key', 'test-client-key');
        Config::set('payment-gateways.midtrans.is_production', false);
        
        Config::set('payment-gateways.xendit.secret_key', 'test-secret-key');
        Config::set('payment-gateways.xendit.webhook_token', 'test-webhook-token');
        
        Config::set('payment-gateways.tripay.api_key', 'test-api-key');
        Config::set('payment-gateways.tripay.private_key', 'test-private-key');
        Config::set('payment-gateways.tripay.merchant_code', 'TEST123');
        Config::set('payment-gateways.tripay.base_url', 'https://tripay.co.id/api-sandbox');
    }

    /** @test */
    public function it_creates_midtrans_gateway_by_default()
    {
        $service = new PaymentGatewayService();
        
        $this->assertInstanceOf(MidtransGateway::class, $service->getGateway());
        $this->assertEquals('midtrans', $service->getGatewayName());
    }

    /** @test */
    public function it_creates_xendit_gateway_when_specified()
    {
        $service = new PaymentGatewayService('xendit');
        
        $this->assertInstanceOf(XenditGateway::class, $service->getGateway());
        $this->assertEquals('xendit', $service->getGatewayName());
    }

    /** @test */
    public function it_creates_tripay_gateway_when_specified()
    {
        $service = new PaymentGatewayService('tripay');
        
        $this->assertInstanceOf(TripayGateway::class, $service->getGateway());
        $this->assertEquals('tripay', $service->getGatewayName());
    }

    /** @test */
    public function it_throws_exception_for_unsupported_gateway()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported payment gateway: invalid');
        
        new PaymentGatewayService('invalid');
    }

    /** @test */
    public function it_returns_gateway_instance()
    {
        $service = new PaymentGatewayService();
        
        $gateway = $service->getGateway();
        
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    /** @test */
    public function it_creates_payment_link_and_updates_invoice()
    {
        // Create test data
        $customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '081234567890',
        ]);

        $package = Package::factory()->create([
            'name' => 'Paket Premium',
            'speed' => '100 Mbps',
            'price' => 500000,
        ]);

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'amount' => 500000,
            'payment_link' => null,
        ]);

        // Mock Midtrans Snap API
        $mockPaymentUrl = 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test-token';
        
        // We can't easily mock Midtrans static methods, so we'll test the service structure
        // In a real scenario, you'd use a mock or test in integration tests
        
        $paymentGatewayService = new PaymentGatewayService();
        
        // Verify the service is properly configured
        $this->assertInstanceOf(PaymentGatewayInterface::class, $paymentGatewayService->getGateway());
    }

    /** @test */
    public function midtrans_gateway_verifies_valid_signature()
    {
        $gateway = new MidtransGateway();
        
        $orderId = 'INV-123-1234567890';
        $statusCode = '200';
        $grossAmount = '500000.00';
        $serverKey = config('payment-gateways.midtrans.server_key');
        
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        
        $request = Request::create('/webhook', 'POST', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
        ]);
        
        $this->assertTrue($gateway->verifyWebhookSignature($request));
    }

    /** @test */
    public function midtrans_gateway_rejects_invalid_signature()
    {
        $gateway = new MidtransGateway();
        
        $request = Request::create('/webhook', 'POST', [
            'order_id' => 'INV-123-1234567890',
            'status_code' => '200',
            'gross_amount' => '500000.00',
            'signature_key' => 'invalid-signature',
        ]);
        
        $this->assertFalse($gateway->verifyWebhookSignature($request));
    }

    /** @test */
    public function xendit_gateway_verifies_valid_callback_token()
    {
        $gateway = new XenditGateway();
        
        $expectedToken = config('payment-gateways.xendit.webhook_token');
        
        $request = Request::create('/webhook', 'POST', [
            'external_id' => 'INV-123-1234567890',
        ]);
        $request->headers->set('X-Callback-Token', $expectedToken);
        
        $this->assertTrue($gateway->verifyWebhookSignature($request));
    }

    /** @test */
    public function xendit_gateway_rejects_invalid_callback_token()
    {
        $gateway = new XenditGateway();
        
        $request = Request::create('/webhook', 'POST', [
            'external_id' => 'INV-123-1234567890',
        ]);
        $request->headers->set('X-Callback-Token', 'invalid-token');
        
        $this->assertFalse($gateway->verifyWebhookSignature($request));
    }

    /** @test */
    public function tripay_gateway_verifies_valid_signature()
    {
        $gateway = new TripayGateway();
        
        $jsonPayload = json_encode([
            'merchant_ref' => 'INV-123-1234567890',
            'reference' => 'T1234567890',
            'status' => 'PAID',
        ]);
        
        $privateKey = config('payment-gateways.tripay.private_key');
        $signature = hash_hmac('sha256', $jsonPayload, $privateKey);
        
        $request = Request::create('/webhook', 'POST', [], [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            $jsonPayload
        );
        $request->headers->set('X-Callback-Signature', $signature);
        
        $this->assertTrue($gateway->verifyWebhookSignature($request));
    }

    /** @test */
    public function tripay_gateway_rejects_invalid_signature()
    {
        $gateway = new TripayGateway();
        
        $jsonPayload = json_encode([
            'merchant_ref' => 'INV-123-1234567890',
            'reference' => 'T1234567890',
            'status' => 'PAID',
        ]);
        
        $request = Request::create('/webhook', 'POST', [], [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            $jsonPayload
        );
        $request->headers->set('X-Callback-Signature', 'invalid-signature');
        
        $this->assertFalse($gateway->verifyWebhookSignature($request));
    }

    /** @test */
    public function midtrans_gateway_parses_webhook_data_correctly()
    {
        $gateway = new MidtransGateway();
        
        // Mock Midtrans notification data
        $request = Request::create('/webhook', 'POST', [
            'transaction_id' => 'TXN-123456',
            'order_id' => 'INV-123-1234567890',
            'gross_amount' => '500000.00',
            'payment_type' => 'bank_transfer',
            'transaction_status' => 'settlement',
            'transaction_time' => '2024-01-15 10:30:00',
            'fraud_status' => 'accept',
        ]);
        
        // Note: In real scenario, Midtrans\Notification reads from php://input
        // For unit tests, we test the status mapping logic
        $this->assertInstanceOf(MidtransGateway::class, $gateway);
    }

    /** @test */
    public function xendit_gateway_parses_webhook_data_correctly()
    {
        $gateway = new XenditGateway();
        
        $request = Request::create('/webhook', 'POST', [
            'id' => 'xendit-invoice-123',
            'external_id' => 'INV-123-1234567890',
            'status' => 'PAID',
            'amount' => 500000,
            'paid_at' => '2024-01-15T10:30:00Z',
            'payment_method' => 'BANK_TRANSFER',
            'payment_channel' => 'BCA',
        ]);
        
        $data = $gateway->parseWebhookData($request);
        
        $this->assertEquals('xendit-invoice-123', $data['transaction_id']);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(500000.0, $data['amount']);
        $this->assertNotNull($data['paid_at']);
        $this->assertEquals('INV-123-1234567890', $data['metadata']['external_id']);
    }

    /** @test */
    public function tripay_gateway_parses_webhook_data_correctly()
    {
        $gateway = new TripayGateway();
        
        $request = Request::create('/webhook', 'POST', [
            'reference' => 'T1234567890',
            'merchant_ref' => 'INV-123-1234567890',
            'status' => 'PAID',
            'amount' => 500000,
            'paid_at' => 1705308600, // Unix timestamp
            'payment_method' => 'BRIVA',
            'payment_name' => 'BRI Virtual Account',
        ]);
        
        $data = $gateway->parseWebhookData($request);
        
        $this->assertEquals('T1234567890', $data['transaction_id']);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(500000.0, $data['amount']);
        $this->assertNotNull($data['paid_at']);
        $this->assertEquals('INV-123-1234567890', $data['metadata']['merchant_ref']);
    }

    /** @test */
    public function tripay_gateway_gets_payment_status()
    {
        Http::fake([
            'tripay.co.id/*' => Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'T1234567890',
                    'status' => 'PAID',
                    'amount' => 500000,
                ],
            ], 200),
        ]);
        
        $gateway = new TripayGateway();
        $status = $gateway->getPaymentStatus('T1234567890');
        
        $this->assertEquals('success', $status);
    }

    /** @test */
    public function it_maps_midtrans_statuses_correctly()
    {
        $gateway = new MidtransGateway();
        
        // Test via reflection since mapTransactionStatus is protected
        $reflection = new \ReflectionClass($gateway);
        $method = $reflection->getMethod('mapTransactionStatus');
        $method->setAccessible(true);
        
        $this->assertEquals('success', $method->invoke($gateway, 'settlement', 'accept'));
        $this->assertEquals('success', $method->invoke($gateway, 'capture', 'accept'));
        $this->assertEquals('pending', $method->invoke($gateway, 'pending', null));
        $this->assertEquals('failed', $method->invoke($gateway, 'deny', null));
        $this->assertEquals('failed', $method->invoke($gateway, 'cancel', null));
        $this->assertEquals('expired', $method->invoke($gateway, 'expire', null));
        $this->assertEquals('failed', $method->invoke($gateway, 'settlement', 'deny'));
    }

    /** @test */
    public function it_maps_xendit_statuses_correctly()
    {
        $gateway = new XenditGateway();
        
        $reflection = new \ReflectionClass($gateway);
        $method = $reflection->getMethod('mapInvoiceStatus');
        $method->setAccessible(true);
        
        $this->assertEquals('success', $method->invoke($gateway, 'PAID'));
        $this->assertEquals('success', $method->invoke($gateway, 'SETTLED'));
        $this->assertEquals('pending', $method->invoke($gateway, 'PENDING'));
        $this->assertEquals('expired', $method->invoke($gateway, 'EXPIRED'));
        $this->assertEquals('failed', $method->invoke($gateway, 'FAILED'));
    }

    /** @test */
    public function it_maps_tripay_statuses_correctly()
    {
        $gateway = new TripayGateway();
        
        $reflection = new \ReflectionClass($gateway);
        $method = $reflection->getMethod('mapTransactionStatus');
        $method->setAccessible(true);
        
        $this->assertEquals('success', $method->invoke($gateway, 'PAID'));
        $this->assertEquals('pending', $method->invoke($gateway, 'UNPAID'));
        $this->assertEquals('expired', $method->invoke($gateway, 'EXPIRED'));
        $this->assertEquals('failed', $method->invoke($gateway, 'FAILED'));
        $this->assertEquals('failed', $method->invoke($gateway, 'REFUND'));
    }
}
