<?php

namespace Tests\Unit\Services;

use App\Services\WhatsAppService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    protected WhatsAppService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup config untuk testing
        Config::set('whatsapp.gateway', 'fonnte');
        Config::set('whatsapp.fonnte', [
            'api_key' => 'test-api-key',
            'base_url' => 'https://api.fonnte.com',
            'timeout' => 30,
        ]);
        Config::set('whatsapp.retry', [
            'max_attempts' => 3,
            'delay' => 1, // Reduced for testing
            'multiplier' => 2,
        ]);
        Config::set('whatsapp.rate_limit', [
            'enabled' => true,
            'max_per_minute' => 50,
        ]);

        Cache::flush();
    }

    /** @test */
    public function it_can_send_whatsapp_message_successfully()
    {
        // Mock successful response
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => true, 'message' => 'Message sent'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        $result = $service->sendMessage('08123456789', 'Test message');

        $this->assertTrue($result);
    }

    /** @test */
    public function it_normalizes_phone_numbers_correctly()
    {
        $service = new WhatsAppService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normalizePhoneNumber');
        $method->setAccessible(true);

        // Test various formats
        $this->assertEquals('628123456789', $method->invoke($service, '08123456789'));
        $this->assertEquals('628123456789', $method->invoke($service, '+628123456789'));
        $this->assertEquals('628123456789', $method->invoke($service, '628123456789'));
        $this->assertEquals('628123456789', $method->invoke($service, '8123456789'));
        $this->assertEquals('628123456789', $method->invoke($service, '0812-3456-789'));
    }

    /** @test */
    public function it_validates_phone_numbers_correctly()
    {
        $service = new WhatsAppService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validatePhoneNumber');
        $method->setAccessible(true);

        // Valid numbers
        $this->assertTrue($method->invoke($service, '628123456789'));
        $this->assertTrue($method->invoke($service, '6281234567890'));

        // Invalid numbers
        $this->assertFalse($method->invoke($service, '08123456789')); // Not starting with 628
        $this->assertFalse($method->invoke($service, '6271234567')); // Too short
        $this->assertFalse($method->invoke($service, '628')); // Too short
        $this->assertFalse($method->invoke($service, '62712345678901234')); // Too long
    }

    /** @test */
    public function it_retries_on_failure()
    {
        // Mock: first two attempts fail, third succeeds
        $mock = new MockHandler([
            new RequestException('Connection timeout', new Request('POST', '/send')),
            new Response(500, [], json_encode(['status' => false])),
            new Response(200, [], json_encode(['status' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        $result = $service->sendMessage('08123456789', 'Test message');

        $this->assertTrue($result);
    }

    /** @test */
    public function it_fails_after_max_retries()
    {
        // Mock: all attempts fail
        $mock = new MockHandler([
            new RequestException('Connection timeout', new Request('POST', '/send')),
            new RequestException('Connection timeout', new Request('POST', '/send')),
            new RequestException('Connection timeout', new Request('POST', '/send')),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        $result = $service->sendMessage('08123456789', 'Test message');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_rejects_invalid_phone_numbers()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        // Invalid phone number (too short)
        $result = $service->sendMessage('123', 'Test message');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_respects_rate_limiting()
    {
        Config::set('whatsapp.rate_limit.max_per_minute', 2);

        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => true])),
            new Response(200, [], json_encode(['status' => true])),
            new Response(200, [], json_encode(['status' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        // First two should succeed
        $this->assertTrue($service->sendMessage('08123456789', 'Message 1'));
        $this->assertTrue($service->sendMessage('08123456789', 'Message 2'));

        // Third should fail due to rate limit
        $this->assertFalse($service->sendMessage('08123456789', 'Message 3'));
    }

    /** @test */
    public function it_can_send_bulk_messages()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => true])),
            new Response(200, [], json_encode(['status' => true])),
            new Response(200, [], json_encode(['status' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        $recipients = [
            ['phone' => '08123456789', 'message' => 'Message 1'],
            ['phone' => '08123456790', 'message' => 'Message 2'],
            ['phone' => '08123456791', 'message' => 'Message 3'],
        ];

        $results = $service->sendBulk($recipients);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
    }

    /** @test */
    public function it_handles_bulk_messages_with_missing_data()
    {
        $service = new WhatsAppService();

        $recipients = [
            ['phone' => '08123456789'], // Missing message
            ['message' => 'Message 2'], // Missing phone
            ['phone' => '08123456791', 'message' => 'Message 3'],
        ];

        $results = $service->sendBulk($recipients);

        $this->assertCount(3, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertEquals('Missing phone or message', $results[0]['error']);
        $this->assertFalse($results[1]['success']);
        $this->assertEquals('Missing phone or message', $results[1]['error']);
    }

    /** @test */
    public function it_can_test_connection()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => 'ok'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        $result = $service->testConnection();

        $this->assertTrue($result['success']);
        $this->assertEquals('fonnte', $result['gateway']);
        $this->assertEquals('Connection successful', $result['message']);
    }

    /** @test */
    public function it_handles_connection_test_failure()
    {
        $mock = new MockHandler([
            new RequestException('Connection refused', new Request('GET', '/')),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        $result = $service->testConnection();

        $this->assertFalse($result['success']);
        $this->assertEquals('fonnte', $result['gateway']);
        $this->assertStringContainsString('Connection failed', $result['message']);
    }

    /** @test */
    public function it_uses_exponential_backoff_for_retries()
    {
        Config::set('whatsapp.retry.delay', 1);
        Config::set('whatsapp.retry.multiplier', 2);

        $mock = new MockHandler([
            new RequestException('Timeout', new Request('POST', '/send')),
            new RequestException('Timeout', new Request('POST', '/send')),
            new Response(200, [], json_encode(['status' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        $startTime = microtime(true);
        $result = $service->sendMessage('08123456789', 'Test message');
        $endTime = microtime(true);

        $this->assertTrue($result);
        
        // Should take at least 3 seconds (1s + 2s delays)
        $duration = $endTime - $startTime;
        $this->assertGreaterThanOrEqual(3, $duration);
    }

    /** @test */
    public function it_bypasses_rate_limiting_when_disabled()
    {
        Config::set('whatsapp.rate_limit.enabled', false);
        Config::set('whatsapp.rate_limit.max_per_minute', 1);

        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => true])),
            new Response(200, [], json_encode(['status' => true])),
            new Response(200, [], json_encode(['status' => true])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = $this->getMockedService($client);

        // All should succeed even though limit is 1
        $this->assertTrue($service->sendMessage('08123456789', 'Message 1'));
        $this->assertTrue($service->sendMessage('08123456789', 'Message 2'));
        $this->assertTrue($service->sendMessage('08123456789', 'Message 3'));
    }

    /**
     * Helper method to create service with mocked HTTP client
     */
    protected function getMockedService(Client $client): WhatsAppService
    {
        $service = new WhatsAppService();
        
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        return $service;
    }
}
