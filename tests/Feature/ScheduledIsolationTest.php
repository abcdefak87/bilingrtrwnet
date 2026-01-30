<?php

namespace Tests\Feature;

use App\Jobs\CheckOverdueInvoicesJob;
use App\Jobs\ProcessIsolationJob;
use App\Jobs\SendIsolationNotificationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use App\Services\IsolationService;
use App\Services\MikrotikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ScheduledIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set grace period for testing
        config(['billing.grace_period_days' => 3]);
    }

    public function test_scheduler_configuration_for_isolation_check(): void
    {
        // This test verifies that the scheduler is configured correctly
        // We check that the CheckOverdueInvoicesJob is scheduled

        $schedule = app()->make(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events());

        // Find the CheckOverdueInvoicesJob in scheduled events
        $isolationCheckEvent = $events->first(function ($event) {
            return str_contains($event->command ?? '', 'CheckOverdueInvoicesJob')
                || str_contains($event->description ?? '', 'check-overdue-invoices');
        });

        $this->assertNotNull($isolationCheckEvent, 'CheckOverdueInvoicesJob should be scheduled');
    }

    public function test_end_to_end_isolation_workflow_with_multiple_overdue_services(): void
    {
        // Arrange
        Queue::fake();

        // Mock MikrotikService to avoid actual API calls
        $mockMikrotikService = Mockery::mock(MikrotikService::class);
        $mockMikrotikService->shouldReceive('updateUserProfile')
            ->andReturn(true);
        $this->app->instance(MikrotikService::class, $mockMikrotikService);

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();

        // Create 5 services with different scenarios
        $services = [];

        // 1. Active service with overdue invoice (should be isolated)
        $customer1 = Customer::factory()->create();
        $service1 = Service::factory()->create([
            'customer_id' => $customer1->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'user_1',
        ]);
        Invoice::factory()->create([
            'service_id' => $service1->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);
        $services[] = $service1;

        // 2. Active service with overdue invoice (should be isolated)
        $customer2 = Customer::factory()->create();
        $service2 = Service::factory()->create([
            'customer_id' => $customer2->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'user_2',
        ]);
        Invoice::factory()->create([
            'service_id' => $service2->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(10),
        ]);
        $services[] = $service2;

        // 3. Active service with paid invoice (should NOT be isolated)
        $customer3 = Customer::factory()->create();
        $service3 = Service::factory()->create([
            'customer_id' => $customer3->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'user_3',
        ]);
        Invoice::factory()->create([
            'service_id' => $service3->id,
            'status' => 'paid',
            'due_date' => now()->subDays(5),
        ]);

        // 4. Already isolated service (should NOT be isolated again)
        $customer4 = Customer::factory()->create();
        $service4 = Service::factory()->create([
            'customer_id' => $customer4->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
            'mikrotik_user_id' => 'user_4',
        ]);
        Invoice::factory()->create([
            'service_id' => $service4->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // 5. Active service within grace period (should NOT be isolated)
        $customer5 = Customer::factory()->create();
        $service5 = Service::factory()->create([
            'customer_id' => $customer5->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'user_5',
        ]);
        Invoice::factory()->create([
            'service_id' => $service5->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(2), // Within grace period
        ]);

        // Act - Run the CheckOverdueInvoicesJob
        $job = new CheckOverdueInvoicesJob();
        $job->handle(app(IsolationService::class));

        // Assert - Only 2 services should have isolation jobs queued
        Queue::assertPushed(ProcessIsolationJob::class, 2);

        // Verify the correct services were queued
        Queue::assertPushed(ProcessIsolationJob::class, function ($job) use ($service1) {
            return $job->serviceId === $service1->id;
        });

        Queue::assertPushed(ProcessIsolationJob::class, function ($job) use ($service2) {
            return $job->serviceId === $service2->id;
        });
    }

    public function test_no_overdue_services_scenario(): void
    {
        // Arrange
        Queue::fake();

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();

        // Create services with no overdue invoices
        for ($i = 0; $i < 3; $i++) {
            $customer = Customer::factory()->create();
            $service = Service::factory()->create([
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'mikrotik_id' => $router->id,
                'status' => 'active',
                'mikrotik_user_id' => "user_{$i}",
            ]);

            // All invoices are paid
            Invoice::factory()->create([
                'service_id' => $service->id,
                'status' => 'paid',
                'due_date' => now()->subDays(5),
            ]);
        }

        // Act
        $job = new CheckOverdueInvoicesJob();
        $job->handle(app(IsolationService::class));

        // Assert - No isolation jobs should be queued
        Queue::assertNotPushed(ProcessIsolationJob::class);
    }

    public function test_process_isolation_job_updates_service_status(): void
    {
        // Arrange
        Queue::fake();

        // Mock MikrotikService to avoid actual API calls
        $mockMikrotikService = Mockery::mock(MikrotikService::class);
        $mockMikrotikService->shouldReceive('updateUserProfile')
            ->once()
            ->andReturn(true);
        $this->app->instance(MikrotikService::class, $mockMikrotikService);

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'test_user',
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // Act - Process the isolation job
        $job = new ProcessIsolationJob($service->id, $invoice->id);
        $job->handle(app(IsolationService::class));

        // Assert - Service status should be updated to isolated
        $service->refresh();
        $this->assertEquals('isolated', $service->status);
        $this->assertNotNull($service->isolation_timestamp);

        // Assert - Notification job should be queued
        Queue::assertPushed(SendIsolationNotificationJob::class, function ($job) use ($service) {
            return $job->serviceId === $service->id;
        });
    }

    public function test_isolation_workflow_with_mikrotik_api_failure(): void
    {
        // Arrange
        // Mock MikrotikService to simulate API failure
        $mockMikrotikService = Mockery::mock(MikrotikService::class);
        $mockMikrotikService->shouldReceive('updateUserProfile')
            ->once()
            ->andThrow(new \Exception('Mikrotik API connection failed'));
        $this->app->instance(MikrotikService::class, $mockMikrotikService);

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'test_user',
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // Act & Assert - Should throw exception (which triggers retry)
        $job = new ProcessIsolationJob($service->id, $invoice->id);

        $this->expectException(\Exception::class);
        $job->handle(app(IsolationService::class));

        // Service status should remain active (not isolated due to failure)
        $service->refresh();
        $this->assertEquals('active', $service->status);
    }

    public function test_complete_isolation_workflow_with_notification(): void
    {
        // Arrange
        Queue::fake();

        // Mock MikrotikService to avoid actual API calls
        $mockMikrotikService = Mockery::mock(MikrotikService::class);
        $mockMikrotikService->shouldReceive('updateUserProfile')
            ->once()
            ->with(
                Mockery::type(MikrotikRouter::class),
                'test_user_123',
                'Isolir'
            )
            ->andReturn(true);
        $this->app->instance(MikrotikService::class, $mockMikrotikService);

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create(['name' => 'Paket 10Mbps']);
        $customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'phone' => '081234567890',
        ]);

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'test_user_123',
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'amount' => 200000,
            'due_date' => now()->subDays(5),
            'payment_link' => 'https://payment.example.com/invoice-123',
        ]);

        // Act - Process the isolation job
        $job = new ProcessIsolationJob($service->id, $invoice->id);
        $job->handle(app(IsolationService::class));

        // Assert 1: Service status should be updated to isolated
        $service->refresh();
        $this->assertEquals('isolated', $service->status);
        $this->assertNotNull($service->isolation_timestamp);

        // Assert 2: Mikrotik API should have been called with correct parameters
        $mockMikrotikService->shouldHaveReceived('updateUserProfile');

        // Assert 3: Notification job should be queued with correct service ID
        Queue::assertPushed(SendIsolationNotificationJob::class, function ($job) use ($service) {
            return $job->serviceId === $service->id;
        });

        // Assert 4: Only one notification should be queued
        Queue::assertPushed(SendIsolationNotificationJob::class, 1);
    }

    public function test_notification_not_queued_when_isolation_fails(): void
    {
        // Arrange
        Queue::fake();

        // Mock MikrotikService to simulate API failure
        $mockMikrotikService = Mockery::mock(MikrotikService::class);
        $mockMikrotikService->shouldReceive('updateUserProfile')
            ->once()
            ->andThrow(new \Exception('Mikrotik API connection failed'));
        $this->app->instance(MikrotikService::class, $mockMikrotikService);

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'test_user',
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // Act - Process the isolation job (should fail)
        $job = new ProcessIsolationJob($service->id, $invoice->id);

        try {
            $job->handle(app(IsolationService::class));
        } catch (\Exception $e) {
            // Expected exception
        }

        // Assert: Notification should NOT be queued when isolation fails
        Queue::assertNotPushed(SendIsolationNotificationJob::class);

        // Service should remain active
        $service->refresh();
        $this->assertEquals('active', $service->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
