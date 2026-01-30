<?php

namespace Tests\Feature;

use App\Jobs\RestoreServiceJob;
use App\Jobs\SendPaymentConfirmationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\PaymentGatewayService;
use App\Services\PaymentGateways\MidtransGateway;
use App\Services\PaymentGateways\TripayGateway;
use App\Services\PaymentGateways\XenditGateway;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Package $package;
    protected MikrotikRouter $router;
    protected Service $service;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);
        $this->package = Package::factory()->create([
            'name' => 'Paket 10 Mbps',
            'price' => 200000,
        ]);
        $this->router = MikrotikRouter::factory()->create();
        $this->service = Service::factory()->create([
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'mikrotik_id' => $this->router->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'active',
            'expiry_date' => Carbon::today()->addDays(5),
        ]);
        $this->invoice = Invoice::factory()->create([
            'service_id' => $this->service->id,
            'tenant_id' => $this->user->tenant_id,
            'amount' => 200000,
            'status' => 'unpaid',
            'invoice_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(7),
        ]);
    }

    /** @test */
    public function it_processes_midtrans_webhook_with_valid_signature()
    {
        Queue::fake();

        // Calculate valid signature
        $serverKey = config('payment-gateways.midtrans.server_key');
        $orderId = (string) $this->invoice->id;
        $statusCode = '200';
        $grossAmount = '200000.00';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        // Mock Midtrans webhook payload
        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_status' => 'settlement',
            'transaction_id' => 'MIDTRANS-' . uniqid(),
            'payment_type' => 'bank_transfer',
            'transaction_time' => now()->toIso8601String(),
        ];

        // Send webhook request
        $response = $this->postJson('/webhooks/payment/midtrans', $payload);

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment processed successfully',
            ]);

        // Assert payment record created
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'midtrans',
            'transaction_id' => $payload['transaction_id'],
            'amount' => 200000,
            'status' => 'success',
        ]);

        // Assert invoice marked as paid
        $this->invoice->refresh();
        $this->assertEquals('paid', $this->invoice->status);
        $this->assertNotNull($this->invoice->paid_at);

        // Assert service expiry extended
        $this->service->refresh();
        $expectedExpiry = Carbon::today()->addDays(5)->addDays(30); // Original + billing cycle
        $this->assertEquals($expectedExpiry->format('Y-m-d'), $this->service->expiry_date->format('Y-m-d'));

        // Assert notification job queued
        Queue::assertPushed(SendPaymentConfirmationJob::class, function ($job) {
            return $job->invoice->id === $this->invoice->id;
        });
    }

    /** @test */
    public function it_processes_xendit_webhook_with_valid_signature()
    {
        Queue::fake();

        // Mock Xendit webhook payload
        $payload = [
            'external_id' => (string) $this->invoice->id,
            'status' => 'PAID',
            'id' => 'XENDIT-' . uniqid(),
            'amount' => 200000,
            'paid_at' => now()->toIso8601String(),
        ];

        // Send webhook request with valid callback token header
        $response = $this->withHeaders([
            'X-Callback-Token' => config('payment-gateways.xendit.webhook_token'),
        ])->postJson('/webhooks/payment/xendit', $payload);

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment processed successfully',
            ]);

        // Assert payment record created
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'xendit',
            'transaction_id' => $payload['id'],
            'status' => 'success',
        ]);
    }

    /** @test */
    public function it_processes_tripay_webhook_with_valid_signature()
    {
        Queue::fake();

        // Mock Tripay webhook payload
        $payload = [
            'merchant_ref' => (string) $this->invoice->id,
            'status' => 'PAID',
            'reference' => 'TRIPAY-' . uniqid(),
            'amount' => 200000,
            'paid_at' => now()->timestamp,
        ];

        // Calculate valid signature
        $privateKey = config('payment-gateways.tripay.private_key');
        $json = json_encode($payload);
        $signature = hash_hmac('sha256', $json, $privateKey);

        // Send webhook request with valid signature header
        $response = $this->withHeaders([
            'X-Callback-Signature' => $signature,
        ])->postJson('/webhooks/payment/tripay', $payload);

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment processed successfully',
            ]);

        // Assert payment record created
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'tripay',
            'transaction_id' => $payload['reference'],
            'status' => 'success',
        ]);
    }

    /** @test */
    public function it_rejects_webhook_with_invalid_signature()
    {
        Queue::fake();

        $payload = [
            'order_id' => (string) $this->invoice->id,
            'status_code' => '200',
            'gross_amount' => '200000.00',
            'signature_key' => 'INVALID_SIGNATURE',
            'transaction_status' => 'settlement',
            'transaction_id' => 'INVALID-' . uniqid(),
        ];

        // Send webhook request with invalid signature
        $response = $this->postJson('/webhooks/payment/midtrans', $payload);

        // Assert response
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid signature',
            ]);

        // Assert no payment record created
        $this->assertDatabaseMissing('payments', [
            'transaction_id' => $payload['transaction_id'],
        ]);

        // Assert invoice still unpaid
        $this->invoice->refresh();
        $this->assertEquals('unpaid', $this->invoice->status);

        // Assert no notification job queued
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_handles_duplicate_webhook_idempotently()
    {
        Queue::fake();

        $transactionId = 'MIDTRANS-' . uniqid();

        // Create existing payment
        Payment::create([
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'midtrans',
            'transaction_id' => $transactionId,
            'amount' => 200000,
            'status' => 'success',
            'metadata' => [],
        ]);

        // Calculate valid signature
        $serverKey = config('payment-gateways.midtrans.server_key');
        $orderId = (string) $this->invoice->id;
        $statusCode = '200';
        $grossAmount = '200000.00';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_id' => $transactionId,
            'transaction_status' => 'settlement',
        ];

        // Send duplicate webhook
        $response = $this->postJson('/webhooks/payment/midtrans', $payload);

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment already processed',
            ]);

        // Assert only one payment record exists
        $this->assertEquals(1, Payment::where('transaction_id', $transactionId)->count());
    }

    /** @test */
    public function it_extends_service_expiry_correctly_when_not_expired()
    {
        Queue::fake();

        // Set service expiry to 10 days from now
        $this->service->update(['expiry_date' => Carbon::today()->addDays(10)]);

        // Calculate valid signature
        $serverKey = config('payment-gateways.midtrans.server_key');
        $orderId = (string) $this->invoice->id;
        $statusCode = '200';
        $grossAmount = '200000.00';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        // Send webhook
        $this->postJson('/webhooks/payment/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_status' => 'settlement',
            'transaction_id' => 'TXN-' . uniqid(),
        ]);

        // Assert service expiry extended from current expiry
        $this->service->refresh();
        $expectedExpiry = Carbon::today()->addDays(10)->addDays(30); // Current expiry + billing cycle
        $this->assertEquals($expectedExpiry->format('Y-m-d'), $this->service->expiry_date->format('Y-m-d'));
    }

    /** @test */
    public function it_extends_service_expiry_from_today_when_expired()
    {
        Queue::fake();

        // Set service expiry to past date
        $this->service->update(['expiry_date' => Carbon::today()->subDays(5)]);

        // Calculate valid signature
        $serverKey = config('payment-gateways.midtrans.server_key');
        $orderId = (string) $this->invoice->id;
        $statusCode = '200';
        $grossAmount = '200000.00';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        // Send webhook
        $this->postJson('/webhooks/payment/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_status' => 'settlement',
            'transaction_id' => 'TXN-' . uniqid(),
        ]);

        // Assert service expiry extended from today
        $this->service->refresh();
        $expectedExpiry = Carbon::today()->addDays(30); // Today + billing cycle
        $this->assertEquals($expectedExpiry->format('Y-m-d'), $this->service->expiry_date->format('Y-m-d'));
    }

    /** @test */
    public function it_queues_restoration_job_for_isolated_service()
    {
        Queue::fake();

        // Set service as isolated
        $this->service->update(['status' => 'isolated']);

        // Calculate valid signature
        $serverKey = config('payment-gateways.midtrans.server_key');
        $orderId = (string) $this->invoice->id;
        $statusCode = '200';
        $grossAmount = '200000.00';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        // Send webhook
        $this->postJson('/webhooks/payment/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_status' => 'settlement',
            'transaction_id' => 'TXN-' . uniqid(),
        ]);

        // Assert restoration job queued
        Queue::assertPushed(RestoreServiceJob::class, function ($job) {
            return $job->service->id === $this->service->id;
        });
    }

    /** @test */
    public function it_does_not_queue_restoration_job_for_active_service()
    {
        Queue::fake();

        // Service is already active
        $this->service->update(['status' => 'active']);

        // Calculate valid signature
        $serverKey = config('payment-gateways.midtrans.server_key');
        $orderId = (string) $this->invoice->id;
        $statusCode = '200';
        $grossAmount = '200000.00';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        // Send webhook
        $this->postJson('/webhooks/payment/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_status' => 'settlement',
            'transaction_id' => 'TXN-' . uniqid(),
        ]);

        // Assert restoration job NOT queued
        Queue::assertNotPushed(RestoreServiceJob::class);
    }

    /** @test */
    public function it_returns_404_when_invoice_not_found()
    {
        Queue::fake();

        // Calculate valid signature for non-existent invoice
        $serverKey = config('payment-gateways.midtrans.server_key');
        $orderId = '99999';
        $statusCode = '200';
        $grossAmount = '200000.00';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        // Send webhook
        $response = $this->postJson('/webhooks/payment/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_status' => 'settlement',
            'transaction_id' => 'TXN-' . uniqid(),
        ]);

        // Assert response
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Invoice not found',
            ]);
    }

    /** @test */
    public function it_ignores_non_successful_payment_status()
    {
        Queue::fake();

        // Calculate valid signature
        $serverKey = config('payment-gateways.midtrans.server_key');
        $orderId = (string) $this->invoice->id;
        $statusCode = '201'; // Pending status
        $grossAmount = '200000.00';
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        // Send webhook with pending status
        $response = $this->postJson('/webhooks/payment/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_status' => 'pending', // Not settlement
            'transaction_id' => 'TXN-' . uniqid(),
        ]);

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook received',
            ]);

        // Assert invoice still unpaid
        $this->invoice->refresh();
        $this->assertEquals('unpaid', $this->invoice->status);

        // Assert no payment record created
        $this->assertEquals(0, Payment::where('invoice_id', $this->invoice->id)->count());
    }

    /** @test */
    public function it_logs_webhook_attempts()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Payment webhook received', \Mockery::type('array'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Invalid webhook signature detected', \Mockery::type('array'));
        
        // Allow any error logs (from exception handler)
        Log::shouldReceive('error')
            ->zeroOrMoreTimes();

        $payload = [
            'order_id' => (string) $this->invoice->id,
            'status_code' => '200',
            'gross_amount' => '200000.00',
            'signature_key' => 'INVALID_SIGNATURE',
            'transaction_status' => 'settlement',
        ];

        // Send webhook with invalid signature
        $this->postJson('/webhooks/payment/midtrans', $payload);
    }

    /** @test */
    public function it_validates_gateway_parameter()
    {
        // Send webhook with invalid gateway
        $response = $this->postJson('/webhooks/payment/invalid-gateway', [
            'order_id' => (string) $this->invoice->id,
        ]);

        // Assert 404 (route not found due to whereIn constraint)
        $response->assertStatus(404);
    }
}
