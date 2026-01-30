<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RestoreServiceJob;
use App\Jobs\SendRestorationNotificationJob;
use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use App\Services\IsolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RestoreServiceJobTest extends TestCase
{
    use RefreshDatabase;

    protected Service $service;
    protected IsolationService $isolationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $customer = Customer::factory()->create();
        $package = Package::factory()->create();
        $router = MikrotikRouter::factory()->create();

        $this->service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
            'mikrotik_user_id' => 'test_user_123',
        ]);

        $this->isolationService = $this->createMock(IsolationService::class);
    }

    /** @test */
    public function it_successfully_restores_service_and_queues_notification()
    {
        Queue::fake();

        // Mock IsolationService to return success
        $this->isolationService
            ->expects($this->once())
            ->method('restoreService')
            ->with($this->service)
            ->willReturn(true);

        // Execute the job
        $job = new RestoreServiceJob($this->service);
        $job->handle($this->isolationService);

        // Assert notification was queued
        Queue::assertPushed(SendRestorationNotificationJob::class, function ($job) {
            return $job->serviceId === $this->service->id;
        });
    }

    /** @test */
    public function it_throws_exception_when_isolation_service_fails()
    {
        Queue::fake();

        // Mock IsolationService to return failure
        $this->isolationService
            ->expects($this->once())
            ->method('restoreService')
            ->with($this->service)
            ->willReturn(false);

        // Execute the job and expect exception
        $job = new RestoreServiceJob($this->service);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('IsolationService failed to restore service');

        $job->handle($this->isolationService);

        // Assert notification was NOT queued
        Queue::assertNotPushed(SendRestorationNotificationJob::class);
    }

    /** @test */
    public function it_logs_restoration_process()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Starting service restoration', [
                'service_id' => $this->service->id,
                'customer_id' => $this->service->customer_id,
                'current_status' => 'isolated',
            ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Service restoration completed successfully', [
                'service_id' => $this->service->id,
                'customer_id' => $this->service->customer_id,
            ]);

        // Also expect info log from SendRestorationNotificationJob dispatch
        Log::shouldReceive('info')->zeroOrMoreTimes();
        
        // Allow error logs (in case of any unexpected errors)
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Mock IsolationService to return success
        $this->isolationService
            ->expects($this->once())
            ->method('restoreService')
            ->willReturn(true);

        // Execute the job
        $job = new RestoreServiceJob($this->service);
        $job->handle($this->isolationService);
    }

    /** @test */
    public function it_logs_error_and_rethrows_exception_on_failure()
    {
        $errorMessage = 'Mikrotik API connection failed';

        Log::shouldReceive('info')
            ->once()
            ->with('Starting service restoration', \Mockery::any());

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to restore service', \Mockery::on(function ($context) use ($errorMessage) {
                return $context['service_id'] === $this->service->id
                    && $context['error'] === $errorMessage;
            }));

        // Mock IsolationService to throw exception
        $this->isolationService
            ->expects($this->once())
            ->method('restoreService')
            ->willThrowException(new \Exception($errorMessage));

        // Execute the job and expect exception
        $job = new RestoreServiceJob($this->service);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($errorMessage);

        $job->handle($this->isolationService);
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $job = new RestoreServiceJob($this->service);

        // Assert tries
        $this->assertEquals(3, $job->tries);

        // Assert backoff strategy
        $backoff = $job->backoff();
        $this->assertEquals([60, 300, 900], $backoff);
    }
}
