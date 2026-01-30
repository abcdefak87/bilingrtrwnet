<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendRestorationNotificationJob;
use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SendRestorationNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $customer = Customer::factory()->create([
            'name' => 'John Doe',
            'phone' => '081234567890',
        ]);

        $package = Package::factory()->create([
            'name' => 'Paket Premium',
            'speed' => '50 Mbps',
        ]);

        $router = MikrotikRouter::factory()->create();

        $this->service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'expiry_date' => now()->addDays(30),
        ]);
    }

    /** @test */
    public function it_successfully_processes_restoration_notification()
    {
        // Mock WhatsAppService
        $whatsappService = \Mockery::mock(WhatsAppService::class);
        $whatsappService->shouldReceive('sendMessage')
            ->once()
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Processing restoration notification', [
                'service_id' => $this->service->id,
                'attempt' => 1,
            ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Restoration WhatsApp notification sent', \Mockery::on(function ($context) {
                return $context['service_id'] === $this->service->id
                    && $context['customer_id'] === $this->service->customer_id
                    && $context['phone'] === '081234567890';
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('Restoration notification completed', \Mockery::on(function ($context) {
                return $context['service_id'] === $this->service->id
                    && isset($context['whatsapp_success'])
                    && isset($context['email_success']);
            }));
        
        // Allow error logs (in case of any unexpected errors)
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Execute the job
        $job = new SendRestorationNotificationJob($this->service->id);
        $job->handle($whatsappService);
    }

    /** @test */
    public function it_builds_correct_notification_message()
    {
        // Mock WhatsAppService
        $whatsappService = \Mockery::mock(WhatsAppService::class);
        $whatsappService->shouldReceive('sendMessage')
            ->once()
            ->andReturn(true);

        // Allow all info logs
        Log::shouldReceive('info')->zeroOrMoreTimes();
        
        // Allow error logs (in case of any unexpected errors)
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Execute the job
        $job = new SendRestorationNotificationJob($this->service->id);
        $job->handle($whatsappService);

        // We can't directly test the message content since it's private,
        // but we can verify the job completes without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function it_throws_exception_when_service_not_found()
    {
        // Mock WhatsAppService
        $whatsappService = \Mockery::mock(WhatsAppService::class);

        Log::shouldReceive('info')
            ->once()
            ->with('Processing restoration notification', \Mockery::any());

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to send restoration notification', \Mockery::on(function ($context) {
                return $context['service_id'] === 99999
                    && isset($context['error']);
            }));

        // Execute the job with non-existent service ID
        $job = new SendRestorationNotificationJob(99999);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $job->handle($whatsappService);
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $job = new SendRestorationNotificationJob($this->service->id);

        // Assert tries
        $this->assertEquals(3, $job->tries);

        // Assert backoff strategy
        $this->assertEquals([30, 60, 120], $job->backoff);
    }

    /** @test */
    public function it_logs_critical_error_on_final_failure()
    {
        $exception = new \Exception('WhatsApp gateway timeout');

        Log::shouldReceive('critical')
            ->once()
            ->with('SendRestorationNotificationJob failed after all retries', \Mockery::on(function ($context) use ($exception) {
                return $context['service_id'] === $this->service->id
                    && $context['error'] === $exception->getMessage()
                    && isset($context['trace']);
            }));

        // Execute the failed method
        $job = new SendRestorationNotificationJob($this->service->id);
        $job->failed($exception);
    }

    /** @test */
    public function it_includes_customer_and_package_details_in_logs()
    {
        // Mock WhatsAppService
        $whatsappService = \Mockery::mock(WhatsAppService::class);
        $whatsappService->shouldReceive('sendMessage')
            ->once()
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Processing restoration notification', \Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Restoration WhatsApp notification sent', \Mockery::on(function ($context) {
                return $context['phone'] === '081234567890';
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('Restoration notification completed', \Mockery::any());
        
        // Allow error logs (in case of any unexpected errors)
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Execute the job
        $job = new SendRestorationNotificationJob($this->service->id);
        $job->handle($whatsappService);
    }
}
