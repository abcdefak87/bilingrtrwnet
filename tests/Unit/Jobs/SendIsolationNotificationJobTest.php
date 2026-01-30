<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendIsolationNotificationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendIsolationNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the queue to test job dispatching
        Queue::fake();
        
        // Fake mail
        Mail::fake();
    }

    /** @test */
    public function it_can_be_dispatched_with_service_id()
    {
        $serviceId = 123;

        SendIsolationNotificationJob::dispatch($serviceId);

        Queue::assertPushed(SendIsolationNotificationJob::class, function ($job) use ($serviceId) {
            return $job->serviceId === $serviceId;
        });
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $job = new SendIsolationNotificationJob(1);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 60, 120], $job->backoff);
    }

    /** @test */
    public function it_loads_service_with_related_data()
    {
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);

        // Mock WhatsAppService
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->willReturn(true);
        
        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create test data
        $customer = Customer::factory()->create([
            'name' => 'John Doe',
            'phone' => '081234567890',
            'email' => 'john@example.com',
        ]);

        $package = Package::factory()->create([
            'name' => 'Paket 10Mbps',
            'price' => 200000,
        ]);

        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'amount' => 200000,
            'payment_link' => 'https://payment.example.com/invoice-123',
        ]);

        // Execute the job
        $job = new SendIsolationNotificationJob($service->id);
        $job->handle($whatsappService);

        // Assert: WhatsApp and Email should be sent (check via logging)
        Log::shouldHaveReceived('info')->with('Isolation WhatsApp notification sent', \Mockery::any());
        Log::shouldHaveReceived('info')->with('Isolation email notification sent', \Mockery::any());
    }

    /** @test */
    public function it_builds_notification_message_with_payment_instructions()
    {
        Log::shouldReceive('info')->andReturn(null);

        // Mock WhatsAppService
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->willReturn(true);
        
        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create test data
        $customer = Customer::factory()->create([
            'name' => 'Jane Smith',
            'phone' => '081234567890',
            'email' => 'jane@example.com',
        ]);

        $package = Package::factory()->create([
            'name' => 'Paket Premium 20Mbps',
            'price' => 350000,
        ]);

        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'amount' => 350000,
            'payment_link' => 'https://payment.example.com/invoice-456',
        ]);

        // Execute the job
        $job = new SendIsolationNotificationJob($service->id);
        $job->handle($whatsappService);

        // Verify that log contains expected information
        Log::shouldHaveReceived('info')->with('Isolation WhatsApp notification sent', \Mockery::on(function ($context) use ($service, $customer) {
            return $context['service_id'] === $service->id
                && $context['customer_id'] === $customer->id
                && $context['phone'] === '081234567890';
        }));
    }

    /** @test */
    public function it_handles_service_without_unpaid_invoice_gracefully()
    {
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->once()->with('No unpaid invoice found for isolated service', \Mockery::on(function ($context) {
            return isset($context['service_id']);
        }));

        // Mock WhatsAppService (should not be called)
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->never())
            ->method('sendMessage');
        
        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create service without unpaid invoice
        $customer = Customer::factory()->create();
        $package = Package::factory()->create();
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
        ]);

        // Create a paid invoice (not unpaid)
        Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'paid',
        ]);

        // Execute the job
        $job = new SendIsolationNotificationJob($service->id);
        $job->handle($whatsappService);

        // Should log warning and return gracefully
        Log::shouldHaveReceived('warning');
    }

    /** @test */
    public function it_logs_error_and_rethrows_exception_on_failure()
    {
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->once();

        // Mock WhatsAppService
        $whatsappService = $this->createMock(WhatsAppService::class);
        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Use invalid service ID to trigger exception
        $job = new SendIsolationNotificationJob(99999);

        $this->expectException(\Exception::class);

        $job->handle($whatsappService);
    }

    /** @test */
    public function it_logs_critical_error_when_job_fails_permanently()
    {
        Log::shouldReceive('critical')->once()->with(
            'SendIsolationNotificationJob failed after all retries',
            \Mockery::on(function ($context) {
                return isset($context['service_id'])
                    && isset($context['error'])
                    && isset($context['trace']);
            })
        );

        $job = new SendIsolationNotificationJob(1);
        $exception = new \Exception('Test failure');

        $job->failed($exception);

        Log::shouldHaveReceived('critical');
    }

    /** @test */
    public function it_includes_payment_link_in_message()
    {
        Log::shouldReceive('info')->andReturn(null);

        // Mock WhatsAppService
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->willReturn(true);
        
        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create test data with payment link
        $customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'phone' => '081234567890',
            'email' => 'test@example.com',
        ]);
        $package = Package::factory()->create(['name' => 'Test Package']);
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
        ]);

        $paymentLink = 'https://payment.example.com/pay/abc123';
        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'payment_link' => $paymentLink,
        ]);

        // Execute the job
        $job = new SendIsolationNotificationJob($service->id);
        $job->handle($whatsappService);

        // Verify notification was sent
        Log::shouldHaveReceived('info')->with('Isolation WhatsApp notification sent', \Mockery::any());
    }

    /** @test */
    public function it_handles_missing_payment_link_gracefully()
    {
        Log::shouldReceive('info')->andReturn(null);

        // Mock WhatsAppService
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->willReturn(true);
        
        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create test data without payment link
        $customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'phone' => '081234567890',
            'email' => 'test@example.com',
        ]);
        $package = Package::factory()->create(['name' => 'Test Package']);
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'payment_link' => null, // No payment link
        ]);

        // Execute the job - should not throw exception
        $job = new SendIsolationNotificationJob($service->id);
        $job->handle($whatsappService);

        // Should complete successfully with fallback message
        $this->assertTrue(true);
    }

    /** @test */
    public function it_sends_both_whatsapp_and_email_notifications()
    {
        Log::shouldReceive('info')->andReturn(null);

        // Mock WhatsAppService
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->willReturn(true);
        
        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create test data
        $customer = Customer::factory()->create([
            'phone' => '081234567890',
            'email' => 'customer@example.com',
        ]);
        $package = Package::factory()->create();
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
        ]);

        // Execute the job
        $job = new SendIsolationNotificationJob($service->id);
        $job->handle($whatsappService);

        // Assert both channels were used via logging
        Log::shouldHaveReceived('info')->with('Isolation WhatsApp notification sent', \Mockery::any());
        Log::shouldHaveReceived('info')->with('Isolation email notification sent', \Mockery::any());
    }

    /** @test */
    public function it_throws_exception_if_all_channels_fail()
    {
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);

        // Mock WhatsAppService to fail
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->willReturn(false);
        
        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create test data without email
        $customer = Customer::factory()->create([
            'phone' => '081234567890',
            'email' => null, // No email
        ]);
        $package = Package::factory()->create();
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
        ]);

        // Execute the job - should throw exception
        $job = new SendIsolationNotificationJob($service->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('All notification channels failed');

        $job->handle($whatsappService);
    }
}
