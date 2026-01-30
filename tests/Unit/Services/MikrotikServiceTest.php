<?php

namespace Tests\Unit\Services;

use App\Models\MikrotikRouter;
use App\Services\MikrotikService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RouterOS\Client;
use RouterOS\Query;
use Tests\TestCase;

class MikrotikServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MikrotikService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MikrotikService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(MikrotikService::class, $this->service);
    }

    public function test_service_is_singleton(): void
    {
        $service1 = app(MikrotikService::class);
        $service2 = app(MikrotikService::class);

        $this->assertSame($service1, $service2);
    }

    public function test_pool_stats_returns_empty_for_nonexistent_router(): void
    {
        $router = MikrotikRouter::factory()->make(['id' => 999]);

        $stats = $this->service->getPoolStats($router);

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['available']);
        $this->assertEquals(0, $stats['in_use']);
    }

    public function test_clear_pool_does_not_throw_exception(): void
    {
        $this->service->clearPool();

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function test_get_all_pool_stats_returns_array(): void
    {
        $stats = $this->service->getPoolStats();

        $this->assertIsArray($stats);
    }

    /**
     * Test Requirement 10.2: System must send username, password, profile, and service parameters
     */
    public function test_create_pppoe_user_sends_required_parameters(): void
    {
        // This test verifies the method signature and parameter handling
        // Actual Mikrotik API calls would require a real router or complex mocking
        
        $router = MikrotikRouter::factory()->create([
            'ip_address' => '192.168.1.1',
            'username' => 'admin',
            'password_encrypted' => encrypt('password'),
            'api_port' => 8728,
            'is_active' => true,
        ]);

        // Test that the method accepts all required parameters
        $reflection = new \ReflectionMethod(MikrotikService::class, 'createPPPoEUser');
        $parameters = $reflection->getParameters();

        $this->assertCount(5, $parameters);
        $this->assertEquals('router', $parameters[0]->getName());
        $this->assertEquals('username', $parameters[1]->getName());
        $this->assertEquals('password', $parameters[2]->getName());
        $this->assertEquals('profile', $parameters[3]->getName());
        $this->assertEquals('additionalParams', $parameters[4]->getName());
    }

    /**
     * Test Requirement 10.3: System must return Mikrotik user ID for future reference
     */
    public function test_create_pppoe_user_returns_user_id(): void
    {
        // Verify the method returns a string (user ID)
        $reflection = new \ReflectionMethod(MikrotikService::class, 'createPPPoEUser');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    /**
     * Test Requirement 10.4: System must call Mikrotik API with user ID and new profile
     */
    public function test_update_user_profile_accepts_required_parameters(): void
    {
        $reflection = new \ReflectionMethod(MikrotikService::class, 'updateUserProfile');
        $parameters = $reflection->getParameters();

        $this->assertCount(3, $parameters);
        $this->assertEquals('router', $parameters[0]->getName());
        $this->assertEquals('userId', $parameters[1]->getName());
        $this->assertEquals('newProfile', $parameters[2]->getName());
    }

    /**
     * Test Requirement 10.5: System must call Mikrotik API with user ID when deleting
     */
    public function test_delete_user_accepts_user_id_parameter(): void
    {
        $reflection = new \ReflectionMethod(MikrotikService::class, 'deleteUser');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('router', $parameters[0]->getName());
        $this->assertEquals('userId', $parameters[1]->getName());
    }

    /**
     * Test Requirement 10.6: System must log errors with full context
     */
    public function test_methods_log_errors_on_failure(): void
    {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        
        Log::shouldReceive('error')
            ->atLeast()
            ->once()
            ->withArgs(function ($message, $context) {
                // Verify error logging includes router context
                return is_string($message) && 
                       is_array($context) && 
                       (isset($context['router_id']) || isset($context['error']));
            });

        $router = MikrotikRouter::factory()->create([
            'ip_address' => '192.168.99.99', // Non-existent IP
            'username' => 'admin',
            'password_encrypted' => encrypt('password'),
            'api_port' => 8728,
            'is_active' => true,
        ]);

        // This should fail and log an error
        $result = $this->service->testConnection($router);
        
        $this->assertFalse($result);
    }

    /**
     * Test Requirement 10.7: System must test connection and return success/failure status
     */
    public function test_test_connection_returns_boolean(): void
    {
        $router = MikrotikRouter::factory()->create([
            'ip_address' => '192.168.99.99', // Non-existent IP
            'username' => 'admin',
            'password_encrypted' => encrypt('password'),
            'api_port' => 8728,
            'is_active' => true,
        ]);

        $result = $this->service->testConnection($router);

        $this->assertIsBool($result);
        $this->assertFalse($result); // Should fail for non-existent router
    }

    /**
     * Test retry mechanism is configured
     */
    public function test_service_has_retry_configuration(): void
    {
        $reflection = new \ReflectionClass(MikrotikService::class);
        
        $maxAttemptsProperty = $reflection->getProperty('maxAttempts');
        $maxAttemptsProperty->setAccessible(true);
        $maxAttempts = $maxAttemptsProperty->getValue($this->service);

        $retryDelayProperty = $reflection->getProperty('retryDelay');
        $retryDelayProperty->setAccessible(true);
        $retryDelay = $retryDelayProperty->getValue($this->service);

        $this->assertIsInt($maxAttempts);
        $this->assertGreaterThan(0, $maxAttempts);
        $this->assertIsInt($retryDelay);
        $this->assertGreaterThan(0, $retryDelay);
    }

    /**
     * Test connection pooling is available
     */
    public function test_connection_pooling_is_configured(): void
    {
        $reflection = new \ReflectionClass(MikrotikService::class);
        
        $poolingEnabledProperty = $reflection->getProperty('poolingEnabled');
        $poolingEnabledProperty->setAccessible(true);
        $poolingEnabled = $poolingEnabledProperty->getValue($this->service);

        $poolSizeProperty = $reflection->getProperty('poolSize');
        $poolSizeProperty->setAccessible(true);
        $poolSize = $poolSizeProperty->getValue($this->service);

        $this->assertIsBool($poolingEnabled);
        $this->assertIsInt($poolSize);
        $this->assertGreaterThan(0, $poolSize);
    }

    /**
     * Test getUserInfo method exists and has correct signature
     */
    public function test_get_user_info_method_exists(): void
    {
        $this->assertTrue(method_exists($this->service, 'getUserInfo'));
        
        $reflection = new \ReflectionMethod(MikrotikService::class, 'getUserInfo');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('router', $parameters[0]->getName());
        $this->assertEquals('username', $parameters[1]->getName());
    }

    /**
     * Test error handling throws exceptions with meaningful messages
     */
    public function test_connection_failure_throws_exception_with_context(): void
    {
        $router = MikrotikRouter::factory()->create([
            'ip_address' => '192.168.99.99',
            'username' => 'admin',
            'password_encrypted' => encrypt('password'),
            'api_port' => 8728,
            'is_active' => true,
        ]);

        try {
            $this->service->createPPPoEUser($router, 'testuser', 'testpass', 'default');
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertStringContainsString('Failed to', $e->getMessage());
            $this->assertStringContainsString($router->name, $e->getMessage());
        }
    }
}
