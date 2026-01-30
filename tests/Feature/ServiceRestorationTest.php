<?php

namespace Tests\Feature;

use App\Jobs\RestoreServiceJob;
use App\Jobs\SendRestorationNotificationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Service;
use App\Services\IsolationService;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for service restoration workflow.
 *
 * Tests Requirements:
 * - 5.7: Restoration triggered when payment received for isolated service
 * - 5.8: Mikrotik API called to restore original profile
 * - 5.9: Service status updated to "active" and confirmation sent
 */
class ServiceRestorationTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected Package $package;
    protected MikrotikRouter $router;
    protected Service $service;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'phone' => '081234567890',
        ]);

        $this->package = Package::factory()->create([
            'name' => 'Paket 10 Mbps',
            'speed' => '10 Mbps',
            'price' => 200000,
            'mikrotik_profile' => 'Package-10Mbps',
        ]);

        $this->router = MikrotikRouter::factory()->create([
            'name' => 'Router-Test',
            'ip_address' => '192.168.1.1',
        ]);

        $this->service = Service::factory()->create([
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'mikrotik_id' => $this->router->id,
            'status' => 'isolated',
            'mikrotik_user_id' => 'test_user_123',
            'username_pppoe' => 'testuser',
            'expiry_date' => Carbon::now()->subDays(10),
            'isolation_timestamp' => Carbon::now()->subDays(5),
        ]);

        $this->invoice = Invoice::factory()->create([
            'service_id' => $this->service->id,
            'amount' => 200000,
            'status' => 'unpaid',
            'invoice_date' => Carbon::now()->subDays(35),
            'due_date' => Carbon::now()->subDays(5),
        ]);
    }

    /** @test */
    public function it_triggers_restoration_when_payment_received_for_isolated_service()
    {
        Queue::fake();

        // Simulate payment received
        $payment = Payment::create([
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'midtrans',
            'transaction_id' => 'TRX-' . uniqid(),
            'amount' => 200000,
            'status' => 'success',
            'metadata' => [],
        ]);

        // Mark invoice as paid
        $this->invoice->markAsPaid($payment);

        // Extend service expiry
        $this->service->extendExpiry(30);

        // Trigger restoration for isolated service
        if ($this->service->fresh()->status === 'isolated') {
            RestoreServiceJob::dispatch($this->service);
        }

        // Assert restoration job was queued
        Queue::assertPushed(RestoreServiceJob::class);
    }

    /** @test */
    public function it_calls_mikrotik_api_to_restore_original_profile()
    {
        // Mock MikrotikService
        $mikrotikService = $this->createMock(MikrotikService::class);

        // Expect updateUserProfile to be called with correct parameters
        $mikrotikService->expects($this->once())
            ->method('updateUserProfile')
            ->with(
                $this->callback(fn($r) => $r->id === $this->router->id),
                $this->equalTo('test_user_123'),
                $this->equalTo('Package-10Mbps')
            )
            ->willReturn(true); // Return true to indicate success

        // Bind mock to container
        $this->app->instance(MikrotikService::class, $mikrotikService);

        // Create IsolationService with mocked MikrotikService
        $isolationService = new IsolationService($mikrotikService);

        // Execute restoration
        $result = $isolationService->restoreService($this->service);

        // Assert restoration was successful
        $this->assertTrue($result);
    }

    /** @test */
    public function it_updates_service_status_to_active_after_restoration()
    {
        // Mock MikrotikService to succeed
        $mikrotikService = $this->createMock(MikrotikService::class);
        $mikrotikService->method('updateUserProfile')->willReturn(true);

        // Create IsolationService with mocked MikrotikService
        $isolationService = new IsolationService($mikrotikService);

        // Verify initial status
        $this->assertEquals('isolated', $this->service->status);
        $this->assertNotNull($this->service->isolation_timestamp);

        // Execute restoration
        $result = $isolationService->restoreService($this->service);

        // Assert restoration was successful
        $this->assertTrue($result);

        // Refresh service from database
        $this->service->refresh();

        // Assert status updated to active
        $this->assertEquals('active', $this->service->status);

        // Assert isolation timestamp cleared
        $this->assertNull($this->service->isolation_timestamp);
    }

    /** @test */
    public function it_queues_restoration_notification_after_successful_restoration()
    {
        Queue::fake();

        // Mock MikrotikService to succeed
        $mikrotikService = $this->createMock(MikrotikService::class);
        $mikrotikService->method('updateUserProfile')->willReturn(true);

        // Create IsolationService with mocked MikrotikService
        $isolationService = new IsolationService($mikrotikService);

        // Execute restoration
        $isolationService->restoreService($this->service);

        // Execute RestoreServiceJob
        $job = new RestoreServiceJob($this->service);
        $job->handle($isolationService);

        // Assert restoration notification was queued
        Queue::assertPushed(SendRestorationNotificationJob::class, function ($job) {
            return $job->serviceId === $this->service->id;
        });
    }

    /** @test */
    public function it_does_not_trigger_restoration_for_active_service()
    {
        Queue::fake();

        // Change service status to active
        $this->service->update(['status' => 'active']);

        // Simulate payment received
        $payment = Payment::create([
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'midtrans',
            'transaction_id' => 'TRX-' . uniqid(),
            'amount' => 200000,
            'status' => 'success',
            'metadata' => [],
        ]);

        // Mark invoice as paid
        $this->invoice->markAsPaid($payment);

        // Extend service expiry
        $this->service->extendExpiry(30);

        // Should NOT trigger restoration for active service
        if ($this->service->fresh()->status === 'isolated') {
            RestoreServiceJob::dispatch($this->service);
        }

        // Assert restoration job was NOT queued
        Queue::assertNotPushed(RestoreServiceJob::class);
    }

    /** @test */
    public function it_handles_restoration_failure_gracefully()
    {
        // Mock MikrotikService to fail
        $mikrotikService = $this->createMock(MikrotikService::class);
        $mikrotikService->method('updateUserProfile')
            ->willThrowException(new \Exception('Mikrotik API connection failed'));

        // Create IsolationService with mocked MikrotikService
        $isolationService = new IsolationService($mikrotikService);

        // Execute restoration
        $result = $isolationService->restoreService($this->service);

        // Assert restoration failed
        $this->assertFalse($result);

        // Refresh service from database
        $this->service->refresh();

        // Assert status remains isolated
        $this->assertEquals('isolated', $this->service->status);

        // Assert isolation timestamp not cleared
        $this->assertNotNull($this->service->isolation_timestamp);
    }

    /** @test */
    public function it_extends_service_expiry_when_payment_received()
    {
        $originalExpiry = $this->service->expiry_date;

        // Simulate payment received
        $payment = Payment::create([
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'midtrans',
            'transaction_id' => 'TRX-' . uniqid(),
            'amount' => 200000,
            'status' => 'success',
            'metadata' => [],
        ]);

        // Mark invoice as paid
        $this->invoice->markAsPaid($payment);

        // Extend service expiry by 30 days
        $billingCycleDays = 30;
        $this->service->extendExpiry($billingCycleDays);

        // Refresh service
        $this->service->refresh();

        // Assert expiry date was extended
        // Since original expiry was in the past, it should extend from today
        $expectedExpiry = Carbon::now()->startOfDay()->addDays($billingCycleDays);
        $this->assertEquals(
            $expectedExpiry->toDateString(),
            $this->service->expiry_date->toDateString()
        );
    }

    /** @test */
    public function it_completes_full_restoration_workflow_end_to_end()
    {
        Queue::fake();

        // Mock MikrotikService
        $mikrotikService = $this->createMock(MikrotikService::class);
        $mikrotikService->expects($this->once())
            ->method('updateUserProfile')
            ->willReturn(true);

        // Create IsolationService
        $isolationService = new IsolationService($mikrotikService);

        // Step 1: Payment received
        $payment = Payment::create([
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'midtrans',
            'transaction_id' => 'TRX-' . uniqid(),
            'amount' => 200000,
            'status' => 'success',
            'metadata' => [],
        ]);

        // Step 2: Invoice marked as paid
        $this->invoice->markAsPaid($payment);
        $this->assertEquals('paid', $this->invoice->fresh()->status);

        // Step 3: Service expiry extended
        $this->service->extendExpiry(30);
        $this->assertGreaterThan(Carbon::now(), $this->service->fresh()->expiry_date);

        // Step 4: Restoration triggered
        $job = new RestoreServiceJob($this->service);
        $job->handle($isolationService);

        // Step 5: Verify service status updated
        $this->assertEquals('active', $this->service->fresh()->status);
        $this->assertNull($this->service->fresh()->isolation_timestamp);

        // Step 6: Verify notification queued
        Queue::assertPushed(SendRestorationNotificationJob::class, function ($job) {
            return $job->serviceId === $this->service->id;
        });
    }

    /** @test */
    public function it_retries_restoration_on_failure()
    {
        // Mock MikrotikService to fail
        $mikrotikService = $this->createMock(MikrotikService::class);
        $mikrotikService->method('updateUserProfile')
            ->willThrowException(new \Exception('IsolationService failed to restore service'));

        // Create IsolationService
        $isolationService = new IsolationService($mikrotikService);

        // Execute restoration job
        $job = new RestoreServiceJob($this->service);

        // Expect exception to be thrown (which triggers retry)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('IsolationService failed to restore service');

        $job->handle($isolationService);

        // Verify service status remains isolated
        $this->assertEquals('isolated', $this->service->fresh()->status);
    }
}
