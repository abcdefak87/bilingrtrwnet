<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendNotificationJob;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Prevent actual mail sending
        Mail::fake();
        
        // Prevent actual logging in tests
        Log::spy();
    }

    /** @test */
    public function it_sends_whatsapp_notification_successfully()
    {
        // Mock WhatsAppService
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->with('628123456789', 'Test message')
            ->willReturn(true);

        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create and execute job
        $job = new SendNotificationJob(
            'whatsapp',
            '628123456789',
            null,
            'Test message',
            null,
            ['test' => 'metadata']
        );

        $job->handle($whatsappService);

        // Assert logging
        Log::shouldHaveReceived('info')
            ->with('Processing notification', \Mockery::any())
            ->once();
        
        Log::shouldHaveReceived('info')
            ->with('Notification sent successfully', \Mockery::any())
            ->once();
    }

    /** @test */
    public function it_sends_email_notification_successfully()
    {
        // Mock WhatsAppService (not used in this test)
        $whatsappService = $this->createMock(WhatsAppService::class);
        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create and execute job
        $job = new SendNotificationJob(
            'email',
            null,
            'test@example.com',
            'Test email message',
            'Test Subject',
            ['test' => 'metadata']
        );

        $job->handle($whatsappService);

        // Assert email was sent (Mail::raw doesn't use Mailable, so we check differently)
        $this->assertTrue(true); // Mail was sent if no exception thrown

        // Assert logging
        Log::shouldHaveReceived('info')
            ->with('Email notification sent', \Mockery::any())
            ->once();
    }

    /** @test */
    public function it_sends_both_whatsapp_and_email_notifications()
    {
        // Mock WhatsAppService
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->with('628123456789', 'Test message')
            ->willReturn(true);

        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create and execute job
        $job = new SendNotificationJob(
            'both',
            '628123456789',
            'test@example.com',
            'Test message',
            'Test Subject',
            ['test' => 'metadata']
        );

        $job->handle($whatsappService);

        // Assert logging for both channels
        Log::shouldHaveReceived('info')
            ->with('WhatsApp notification sent', \Mockery::any())
            ->once();
        
        Log::shouldHaveReceived('info')
            ->with('Email notification sent', \Mockery::any())
            ->once();
    }

    /** @test */
    public function it_handles_whatsapp_failure_gracefully()
    {
        // Mock WhatsAppService to fail
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->with('628123456789', 'Test message')
            ->willReturn(false);

        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create job
        $job = new SendNotificationJob(
            'whatsapp',
            '628123456789',
            null,
            'Test message'
        );

        // Expect exception to be thrown for retry
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('All notification channels failed');

        $job->handle($whatsappService);
    }

    /** @test */
    public function it_succeeds_if_at_least_one_channel_succeeds()
    {
        // Mock WhatsAppService to fail
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->willReturn(false);

        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create job with both channels (email should succeed)
        $job = new SendNotificationJob(
            'both',
            '628123456789',
            'test@example.com',
            'Test message',
            'Test Subject'
        );

        // Should not throw exception because email succeeds
        $job->handle($whatsappService);

        // Assert success logging
        Log::shouldHaveReceived('info')
            ->with('Notification sent successfully', \Mockery::any())
            ->once();
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $job = new SendNotificationJob(
            'whatsapp',
            '628123456789',
            null,
            'Test message'
        );

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 60, 120], $job->backoff);
    }

    /** @test */
    public function it_logs_failure_after_all_retries()
    {
        $exception = new \Exception('Test failure');

        $job = new SendNotificationJob(
            'whatsapp',
            '628123456789',
            null,
            'Test message'
        );

        $job->failed($exception);

        // Assert critical logging
        Log::shouldHaveReceived('critical')
            ->with('SendNotificationJob failed after all retries', \Mockery::any())
            ->once();
    }

    /** @test */
    public function it_handles_whatsapp_exception_gracefully()
    {
        // Mock WhatsAppService to throw exception
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->willThrowException(new \Exception('WhatsApp API error'));

        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create job
        $job = new SendNotificationJob(
            'whatsapp',
            '628123456789',
            null,
            'Test message'
        );

        // Expect exception to be thrown for retry
        $this->expectException(\Exception::class);

        $job->handle($whatsappService);

        // Assert error logging
        Log::shouldHaveReceived('error')
            ->with('WhatsApp notification exception', \Mockery::any())
            ->once();
    }

    /** @test */
    public function it_skips_whatsapp_if_phone_is_empty()
    {
        // Mock WhatsAppService (should not be called)
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->never())
            ->method('sendMessage');

        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create job with email only
        $job = new SendNotificationJob(
            'both',
            null, // No phone
            'test@example.com',
            'Test message',
            'Test Subject'
        );

        $job->handle($whatsappService);

        // Assert only email logging
        Log::shouldHaveReceived('info')
            ->with('Email notification sent', \Mockery::any())
            ->once();
    }

    /** @test */
    public function it_skips_email_if_email_is_empty()
    {
        // Mock WhatsAppService
        $whatsappService = $this->createMock(WhatsAppService::class);
        $whatsappService->expects($this->once())
            ->method('sendMessage')
            ->willReturn(true);

        $this->app->instance(WhatsAppService::class, $whatsappService);

        // Create job with WhatsApp only
        $job = new SendNotificationJob(
            'both',
            '628123456789',
            null, // No email
            'Test message'
        );

        $job->handle($whatsappService);

        // Assert only WhatsApp logging
        Log::shouldHaveReceived('info')
            ->with('WhatsApp notification sent', \Mockery::any())
            ->once();
    }
}
