<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CheckOverdueInvoicesJob;
use App\Jobs\ProcessIsolationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use App\Services\IsolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckOverdueInvoicesJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set grace period for testing
        config(['billing.grace_period_days' => 3]);
    }

    public function test_job_identifies_overdue_services(): void
    {
        // Arrange
        Queue::fake();

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();

        // Create an active service with overdue invoice
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'test_user_123',
        ]);

        // Create overdue invoice (due date > 3 days ago)
        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5), // 5 days overdue (past grace period)
        ]);

        // Act
        $job = new CheckOverdueInvoicesJob();
        $job->handle(app(IsolationService::class));

        // Assert
        Queue::assertPushed(ProcessIsolationJob::class, 1);
    }

    public function test_job_queues_process_isolation_job_for_each_overdue_service(): void
    {
        // Arrange
        Queue::fake();

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();

        // Create 3 overdue services
        for ($i = 0; $i < 3; $i++) {
            $customer = Customer::factory()->create();
            $service = Service::factory()->create([
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'mikrotik_id' => $router->id,
                'status' => 'active',
                'mikrotik_user_id' => "test_user_{$i}",
            ]);

            Invoice::factory()->create([
                'service_id' => $service->id,
                'status' => 'unpaid',
                'due_date' => now()->subDays(5),
            ]);
        }

        // Act
        $job = new CheckOverdueInvoicesJob();
        $job->handle(app(IsolationService::class));

        // Assert - should queue 3 isolation jobs
        Queue::assertPushed(ProcessIsolationJob::class, 3);
    }

    public function test_job_does_not_queue_for_paid_invoices(): void
    {
        // Arrange
        Queue::fake();

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'test_user_123',
        ]);

        // Create paid invoice (should not trigger isolation)
        Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'paid',
            'due_date' => now()->subDays(5),
        ]);

        // Act
        $job = new CheckOverdueInvoicesJob();
        $job->handle(app(IsolationService::class));

        // Assert - should not queue any isolation jobs
        Queue::assertNotPushed(ProcessIsolationJob::class);
    }

    public function test_job_does_not_queue_for_already_isolated_services(): void
    {
        // Arrange
        Queue::fake();

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();

        // Create service that is already isolated
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated', // Already isolated
            'mikrotik_user_id' => 'test_user_123',
        ]);

        Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // Act
        $job = new CheckOverdueInvoicesJob();
        $job->handle(app(IsolationService::class));

        // Assert - should not queue isolation job for already isolated service
        Queue::assertNotPushed(ProcessIsolationJob::class);
    }

    public function test_job_does_not_queue_for_invoices_within_grace_period(): void
    {
        // Arrange
        Queue::fake();

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'test_user_123',
        ]);

        // Create invoice that is overdue but within grace period (2 days overdue, grace is 3 days)
        Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(2),
        ]);

        // Act
        $job = new CheckOverdueInvoicesJob();
        $job->handle(app(IsolationService::class));

        // Assert - should not queue isolation job (still within grace period)
        Queue::assertNotPushed(ProcessIsolationJob::class);
    }

    public function test_job_logs_summary(): void
    {
        // Arrange
        Queue::fake();
        \Illuminate\Support\Facades\Log::spy();

        $router = MikrotikRouter::factory()->create();
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => 'test_user_123',
        ]);

        Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // Act
        $job = new CheckOverdueInvoicesJob();
        $job->handle(app(IsolationService::class));

        // Assert - verify logging
        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->with('Starting overdue invoice check');

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->with('Overdue invoice check completed', \Mockery::on(function ($context) {
                return isset($context['total_checked']) && isset($context['total_queued']);
            }));
    }
}
