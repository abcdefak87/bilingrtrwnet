<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendBulkNotificationJob;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SendBulkNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Prevent actual mail sending
        Mail::fake();
        
        // Prevent actual logging
        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Create a testable job that doesn't sleep
     */
    protected function createTestableJob(string $channel, array $recipients, int $batchSize = 50): SendBulkNotificationJob
    {
        return new class($channel, $recipients, $batchSize) extends SendBulkNotificationJob {
            protected function sendBulkWhatsApp(WhatsAppService $whatsappService): array
            {
                $allResults = [];
                $batches = array_chunk($this->recipients, $this->batchSize);
                $totalBatches = count($batches);

                Log::info('Processing WhatsApp bulk notification in batches', [
                    'total_recipients' => count($this->recipients),
                    'batch_size' => $this->batchSize,
                    'total_batches' => $totalBatches,
                ]);

                foreach ($batches as $batchIndex => $batch) {
                    $batchNumber = $batchIndex + 1;

                    Log::info("Processing batch {$batchNumber}/{$totalBatches}", [
                        'batch_size' => count($batch),
                    ]);

                    try {
                        $batchResults = $whatsappService->sendBulk($batch);
                        $allResults = array_merge($allResults, $batchResults);

                        $batchSuccess = count(array_filter($batchResults, fn($r) => $r['success'] ?? false));
                        $batchTotal = count($batchResults);

                        Log::info("Batch {$batchNumber}/{$totalBatches} completed", [
                            'success' => $batchSuccess,
                            'total' => $batchTotal,
                        ]);

                        // Skip sleep in tests
                        if ($batchNumber < $totalBatches) {
                            Log::info("Waiting 60 seconds before next batch to respect rate limiting");
                        }

                    } catch (\Exception $e) {
                        Log::error("Batch {$batchNumber}/{$totalBatches} failed", [
                            'error' => $e->getMessage(),
                        ]);

                        foreach ($batch as $recipient) {
                            $allResults[] = [
                                'phone' => $recipient['phone'] ?? 'unknown',
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                }

                return $allResults;
            }
            
            protected function sendBulkEmail(): array
            {
                $allResults = [];
                $batches = array_chunk($this->recipients, $this->batchSize);
                $totalBatches = count($batches);

                Log::info('Processing Email bulk notification in batches', [
                    'total_recipients' => count($this->recipients),
                    'batch_size' => $this->batchSize,
                    'total_batches' => $totalBatches,
                ]);

                foreach ($batches as $batchIndex => $batch) {
                    $batchNumber = $batchIndex + 1;

                    Log::info("Processing email batch {$batchNumber}/{$totalBatches}", [
                        'batch_size' => count($batch),
                    ]);

                    foreach ($batch as $recipient) {
                        try {
                            $email = $recipient['email'] ?? '';
                            $message = $recipient['message'] ?? '';
                            $subject = $recipient['subject'] ?? 'Notifikasi dari ISP Billing System';

                            if (empty($email) || empty($message)) {
                                $allResults[] = [
                                    'email' => $email,
                                    'success' => false,
                                    'error' => 'Missing email or message',
                                ];
                                continue;
                            }

                            \Illuminate\Support\Facades\Mail::raw($message, function ($mail) use ($email, $subject) {
                                $mail->to($email)->subject($subject);
                            });

                            $allResults[] = [
                                'email' => $email,
                                'success' => true,
                            ];

                        } catch (\Exception $e) {
                            $allResults[] = [
                                'email' => $recipient['email'] ?? 'unknown',
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }

                    // Skip sleep in tests
                }

                return $allResults;
            }
        };
    }

    /** @test */
    public function it_processes_whatsapp_bulk_notification_in_single_batch()
    {
        // Arrange
        $recipients = [
            ['phone' => '628123456789', 'message' => 'Test message 1'],
            ['phone' => '628987654321', 'message' => 'Test message 2'],
            ['phone' => '628111222333', 'message' => 'Test message 3'],
        ];

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        $mockWhatsAppService->shouldReceive('sendBulk')
            ->once()
            ->with($recipients)
            ->andReturn([
                ['phone' => '628123456789', 'success' => true],
                ['phone' => '628987654321', 'success' => true],
                ['phone' => '628111222333', 'success' => true],
            ]);

        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        $job = $this->createTestableJob('whatsapp', $recipients, 50);

        // Act
        $job->handle($mockWhatsAppService);

        // Assert
        $this->assertTrue(true); // Job completed without exception
        
        Log::shouldHaveReceived('info')
            ->with('Processing bulk notification', Mockery::any())
            ->once();
        
        Log::shouldHaveReceived('info')
            ->with('Bulk notification completed', Mockery::any())
            ->once();
    }

    /** @test */
    public function it_processes_whatsapp_bulk_notification_in_multiple_batches()
    {
        // Arrange - Create 75 recipients (will be split into 2 batches of 50 and 25)
        $recipients = [];
        for ($i = 1; $i <= 75; $i++) {
            $recipients[] = [
                'phone' => '62812345' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'message' => "Test message {$i}",
            ];
        }

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        
        // First batch (50 recipients)
        $mockWhatsAppService->shouldReceive('sendBulk')
            ->once()
            ->with(Mockery::on(function ($batch) {
                return count($batch) === 50;
            }))
            ->andReturn(array_fill(0, 50, ['phone' => '628xxx', 'success' => true]));

        // Second batch (25 recipients)
        $mockWhatsAppService->shouldReceive('sendBulk')
            ->once()
            ->with(Mockery::on(function ($batch) {
                return count($batch) === 25;
            }))
            ->andReturn(array_fill(0, 25, ['phone' => '628xxx', 'success' => true]));

        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        $job = $this->createTestableJob('whatsapp', $recipients, 50);

        // Act
        $job->handle($mockWhatsAppService);

        // Assert
        $this->assertTrue(true); // Job completed without exception
        
        Log::shouldHaveReceived('info')
            ->with('Processing WhatsApp bulk notification in batches', Mockery::on(function ($context) {
                return $context['total_batches'] === 2;
            }))
            ->once();
    }

    /** @test */
    public function it_respects_batch_size_configuration()
    {
        // Arrange - Create 30 recipients with batch size of 10
        $recipients = [];
        for ($i = 1; $i <= 30; $i++) {
            $recipients[] = [
                'phone' => '62812345' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'message' => "Test message {$i}",
            ];
        }

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        
        // Should be called 3 times (30 recipients / 10 batch size)
        $mockWhatsAppService->shouldReceive('sendBulk')
            ->times(3)
            ->with(Mockery::on(function ($batch) {
                return count($batch) === 10;
            }))
            ->andReturn(array_fill(0, 10, ['phone' => '628xxx', 'success' => true]));

        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        $job = $this->createTestableJob('whatsapp', $recipients, 10);

        // Act
        $job->handle($mockWhatsAppService);

        // Assert
        $this->assertTrue(true); // Job completed without exception
        
        Log::shouldHaveReceived('info')
            ->with('Processing WhatsApp bulk notification in batches', Mockery::on(function ($context) {
                return $context['total_batches'] === 3 && $context['batch_size'] === 10;
            }))
            ->once();
    }

    /** @test */
    public function it_handles_partial_batch_failures()
    {
        // Arrange
        $recipients = [
            ['phone' => '628123456789', 'message' => 'Test message 1'],
            ['phone' => '628987654321', 'message' => 'Test message 2'],
            ['phone' => '628111222333', 'message' => 'Test message 3'],
        ];

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        $mockWhatsAppService->shouldReceive('sendBulk')
            ->once()
            ->andReturn([
                ['phone' => '628123456789', 'success' => true],
                ['phone' => '628987654321', 'success' => false, 'error' => 'Invalid number'],
                ['phone' => '628111222333', 'success' => true],
            ]);

        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        $job = $this->createTestableJob('whatsapp', $recipients, 50);

        // Act
        $job->handle($mockWhatsAppService);

        // Assert - Should complete successfully even with partial failures
        $this->assertTrue(true); // Job completed without exception
        
        Log::shouldHaveReceived('info')
            ->with('Bulk notification completed', Mockery::on(function ($context) {
                return $context['success'] === 2 && $context['failed'] === 1;
            }))
            ->once();
    }

    /** @test */
    public function it_throws_exception_when_success_rate_is_too_low()
    {
        // Arrange
        $recipients = [
            ['phone' => '628123456789', 'message' => 'Test message 1'],
            ['phone' => '628987654321', 'message' => 'Test message 2'],
            ['phone' => '628111222333', 'message' => 'Test message 3'],
            ['phone' => '628444555666', 'message' => 'Test message 4'],
        ];

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        $mockWhatsAppService->shouldReceive('sendBulk')
            ->once()
            ->andReturn([
                ['phone' => '628123456789', 'success' => true],
                ['phone' => '628987654321', 'success' => false],
                ['phone' => '628111222333', 'success' => false],
                ['phone' => '628444555666', 'success' => false],
            ]);

        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        $job = $this->createTestableJob('whatsapp', $recipients, 50);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('success rate too low');
        
        $job->handle($mockWhatsAppService);
    }

    /** @test */
    public function it_processes_email_bulk_notification()
    {
        // Arrange
        $recipients = [
            ['email' => 'user1@example.com', 'message' => 'Test message 1', 'subject' => 'Subject 1'],
            ['email' => 'user2@example.com', 'message' => 'Test message 2', 'subject' => 'Subject 2'],
            ['email' => 'user3@example.com', 'message' => 'Test message 3', 'subject' => 'Subject 3'],
        ];

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        $job = $this->createTestableJob('email', $recipients, 50);

        // Act
        $job->handle($mockWhatsAppService);

        // Assert - Check that emails were sent (Mail::raw doesn't create Mailable instances)
        $this->assertTrue(true); // Job completed without exception
        
        Log::shouldHaveReceived('info')
            ->with('Bulk notification completed', Mockery::on(function ($context) {
                return $context['success'] === 3 && $context['total'] === 3;
            }))
            ->once();
    }

    /** @test */
    public function it_handles_missing_recipient_data()
    {
        // Arrange
        $recipients = [
            ['phone' => '628123456789', 'message' => 'Test message 1'],
            ['phone' => '', 'message' => 'Test message 2'], // Missing phone
            ['phone' => '628111222333', 'message' => ''], // Missing message
        ];

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        $mockWhatsAppService->shouldReceive('sendBulk')
            ->once()
            ->andReturn([
                ['phone' => '628123456789', 'success' => true],
                ['phone' => '', 'success' => false, 'error' => 'Missing phone or message'],
                ['phone' => '628111222333', 'success' => false, 'error' => 'Missing phone or message'],
            ]);

        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        $job = $this->createTestableJob('whatsapp', $recipients, 50);

        // Act & Assert - Should throw exception due to low success rate
        $this->expectException(\Exception::class);
        $job->handle($mockWhatsAppService);
    }

    /** @test */
    public function it_throws_exception_for_unsupported_channel()
    {
        // Arrange
        $recipients = [
            ['phone' => '628123456789', 'message' => 'Test message 1'],
        ];

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        $job = $this->createTestableJob('sms', $recipients, 50); // Unsupported channel

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported channel: sms');
        
        $job->handle($mockWhatsAppService);
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        // Arrange
        $recipients = [
            ['phone' => '628123456789', 'message' => 'Test message 1'],
        ];

        $job = new SendBulkNotificationJob('whatsapp', $recipients);

        // Assert
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 60, 120], $job->backoff);
    }

    /** @test */
    public function it_logs_batch_processing_information()
    {
        // Arrange
        $recipients = [];
        for ($i = 1; $i <= 60; $i++) {
            $recipients[] = [
                'phone' => '62812345' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'message' => "Test message {$i}",
            ];
        }

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        $mockWhatsAppService->shouldReceive('sendBulk')
            ->twice()
            ->andReturn(
                array_fill(0, 50, ['phone' => '628xxx', 'success' => true]),
                array_fill(0, 10, ['phone' => '628xxx', 'success' => true])
            );

        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        $job = $this->createTestableJob('whatsapp', $recipients, 50);

        // Act
        $job->handle($mockWhatsAppService);

        // Assert
        $this->assertTrue(true); // Job completed without exception
        
        Log::shouldHaveReceived('info')
            ->with('Processing batch 1/2', Mockery::any())
            ->once();
        
        Log::shouldHaveReceived('info')
            ->with('Batch 1/2 completed', Mockery::any())
            ->once();
        
        Log::shouldHaveReceived('info')
            ->with('Processing batch 2/2', Mockery::any())
            ->once();
        
        Log::shouldHaveReceived('info')
            ->with('Batch 2/2 completed', Mockery::any())
            ->once();
    }

    /** @test */
    public function it_validates_requirement_7_6_batch_size_of_50()
    {
        // Arrange - Create 100 recipients to test default batch size
        $recipients = [];
        for ($i = 1; $i <= 100; $i++) {
            $recipients[] = [
                'phone' => '62812345' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'message' => "Test message {$i}",
            ];
        }

        $mockWhatsAppService = Mockery::mock(WhatsAppService::class);
        
        // Should be called 2 times (100 recipients / 50 batch size)
        $mockWhatsAppService->shouldReceive('sendBulk')
            ->twice()
            ->with(Mockery::on(function ($batch) {
                return count($batch) === 50;
            }))
            ->andReturn(array_fill(0, 50, ['phone' => '628xxx', 'success' => true]));

        $this->app->instance(WhatsAppService::class, $mockWhatsAppService);

        // Use default batch size (50)
        $job = $this->createTestableJob('whatsapp', $recipients);

        // Act
        $job->handle($mockWhatsAppService);

        // Assert - Validates Requirement 7.6: Bulk notifications processed in batches of 50
        $this->assertTrue(true); // Job completed without exception
        
        Log::shouldHaveReceived('info')
            ->with('Processing WhatsApp bulk notification in batches', Mockery::on(function ($context) {
                return $context['batch_size'] === 50 && $context['total_batches'] === 2;
            }))
            ->once();
    }
}
