<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessIsolationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use App\Services\IsolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessIsolationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_calls_isolation_service_isolate_method(): void
    {
        // Arrange
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

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // Mock IsolationService
        $mockIsolationService = Mockery::mock(IsolationService::class);
        $mockIsolationService->shouldReceive('isolateService')
            ->once()
            ->with(Mockery::on(function ($arg) use ($service) {
                return $arg->id === $service->id;
            }), Mockery::on(function ($arg) use ($invoice) {
                return $arg->id === $invoice->id;
            }))
            ->andReturn(true);

        $this->app->instance(IsolationService::class, $mockIsolationService);

        // Act
        $job = new ProcessIsolationJob($service->id, $invoice->id);
        $job->handle($mockIsolationService);

        // Assert - mock expectations verified automatically
    }

    public function test_job_handles_success_scenario(): void
    {
        // Arrange
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

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // Mock IsolationService to return success
        $mockIsolationService = Mockery::mock(IsolationService::class);
        $mockIsolationService->shouldReceive('isolateService')
            ->once()
            ->andReturn(true);

        // Act
        $job = new ProcessIsolationJob($service->id, $invoice->id);
        $job->handle($mockIsolationService);

        // Assert - verify success logging
        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->with('Isolation processed successfully', Mockery::on(function ($context) use ($service, $invoice) {
                return $context['service_id'] === $service->id
                    && $context['invoice_id'] === $invoice->id;
            }));
    }

    public function test_job_handles_failure_scenario_with_retry(): void
    {
        // Arrange
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

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // Mock IsolationService to return failure
        $mockIsolationService = Mockery::mock(IsolationService::class);
        $mockIsolationService->shouldReceive('isolateService')
            ->once()
            ->andReturn(false);

        // Act & Assert - should throw exception to trigger retry
        $job = new ProcessIsolationJob($service->id, $invoice->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Isolation service returned false');

        $job->handle($mockIsolationService);
    }

    public function test_job_has_correct_retry_configuration(): void
    {
        // Arrange
        $job = new ProcessIsolationJob(1, 1);

        // Assert
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([60, 120, 240], $job->backoff);
    }

    public function test_job_logs_failure_after_all_retries(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Log::spy();

        $exception = new \Exception('Test failure');

        $job = new ProcessIsolationJob(1, 1);

        // Act
        $job->failed($exception);

        // Assert
        \Illuminate\Support\Facades\Log::shouldHaveReceived('critical')
            ->with('ProcessIsolationJob failed after all retries', Mockery::on(function ($context) {
                return isset($context['service_id'])
                    && isset($context['invoice_id'])
                    && isset($context['error']);
            }));
    }

    public function test_job_logs_processing_attempt(): void
    {
        // Arrange
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

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
        ]);

        // Mock IsolationService
        $mockIsolationService = Mockery::mock(IsolationService::class);
        $mockIsolationService->shouldReceive('isolateService')
            ->once()
            ->andReturn(true);

        // Act
        $job = new ProcessIsolationJob($service->id, $invoice->id);
        $job->handle($mockIsolationService);

        // Assert
        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->with('Processing isolation job', Mockery::on(function ($context) use ($service, $invoice) {
                return $context['service_id'] === $service->id
                    && $context['invoice_id'] === $invoice->id
                    && isset($context['attempt']);
            }));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
