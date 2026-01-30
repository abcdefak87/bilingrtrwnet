<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use App\Services\MikrotikService;
use App\Services\ServiceProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ServiceProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Customer $customer;

    protected Package $package;

    protected MikrotikRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // Create test data
        $this->customer = Customer::factory()->create([
            'status' => 'survey_complete',
        ]);

        $this->package = Package::factory()->create([
            'name' => 'Paket 10Mbps',
            'speed' => '10Mbps',
            'price' => 200000,
            'is_active' => true,
        ]);

        $this->router = MikrotikRouter::factory()->create([
            'name' => 'Router Test',
            'ip_address' => '192.168.1.1',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_generates_unique_pppoe_credentials()
    {
        $service = app(ServiceProvisioningService::class);

        $credentials1 = $service->generateCredentials();
        $credentials2 = $service->generateCredentials();

        // Usernames should be unique
        $this->assertNotEquals($credentials1['username'], $credentials2['username']);

        // Passwords should be unique
        $this->assertNotEquals($credentials1['password'], $credentials2['password']);

        // Username should follow format: pppoe_YYYYMMDD_RANDOM
        $this->assertMatchesRegularExpression('/^pppoe_\d{8}_[A-Z0-9]{6}$/', $credentials1['username']);

        // Password should be 12 characters
        $this->assertEquals(12, strlen($credentials1['password']));
    }

    /** @test */
    public function it_creates_service_record_with_encrypted_password()
    {
        $service = app(ServiceProvisioningService::class);

        $result = $service->createService($this->customer, $this->package, $this->router);

        $this->assertInstanceOf(Service::class, $result);
        $this->assertEquals($this->customer->id, $result->customer_id);
        $this->assertEquals($this->package->id, $result->package_id);
        $this->assertEquals($this->router->id, $result->mikrotik_id);
        $this->assertEquals('pending', $result->status);
        $this->assertNotNull($result->username_pppoe);
        $this->assertNotNull($result->password_encrypted);

        // Verify password is encrypted in database
        $rawPassword = $result->getAttributes()['password_encrypted'];
        $this->assertNotEquals($result->password_encrypted, $rawPassword);

        // Verify password can be decrypted
        $decrypted = Crypt::decryptString($rawPassword);
        $this->assertEquals($result->password_encrypted, $decrypted);
    }

    /** @test */
    public function it_provisions_service_to_mikrotik_successfully()
    {
        // Mock MikrotikService
        $mikrotikMock = Mockery::mock(MikrotikService::class);
        $mikrotikMock->shouldReceive('createPPPoEUser')
            ->once()
            ->with(
                Mockery::type(MikrotikRouter::class),
                Mockery::type('string'), // username
                Mockery::type('string'), // password
                Mockery::type('string')  // profile
            )
            ->andReturn('*1234'); // Mikrotik user ID

        $this->app->instance(MikrotikService::class, $mikrotikMock);

        $service = app(ServiceProvisioningService::class);

        // Create service record
        $serviceRecord = $service->createService($this->customer, $this->package, $this->router);

        // Provision to Mikrotik
        $success = $service->provisionToRouter($serviceRecord);

        $this->assertTrue($success);

        // Verify service status updated
        $serviceRecord->refresh();
        $this->assertEquals('active', $serviceRecord->status);
        $this->assertEquals('*1234', $serviceRecord->mikrotik_user_id);
    }

    /** @test */
    public function it_marks_service_as_provisioning_failed_on_mikrotik_error()
    {
        // Mock MikrotikService to throw exception
        $mikrotikMock = Mockery::mock(MikrotikService::class);
        $mikrotikMock->shouldReceive('createPPPoEUser')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $this->app->instance(MikrotikService::class, $mikrotikMock);

        $service = app(ServiceProvisioningService::class);

        // Create service record
        $serviceRecord = $service->createService($this->customer, $this->package, $this->router);

        // Provision to Mikrotik (should fail)
        $success = $service->provisionToRouter($serviceRecord);

        $this->assertFalse($success);

        // Verify service status marked as failed
        $serviceRecord->refresh();
        $this->assertEquals('provisioning_failed', $serviceRecord->status);
        $this->assertNull($serviceRecord->mikrotik_user_id);
    }

    /** @test */
    public function it_provisions_complete_service_workflow()
    {
        // Mock MikrotikService
        $mikrotikMock = Mockery::mock(MikrotikService::class);
        $mikrotikMock->shouldReceive('createPPPoEUser')
            ->once()
            ->andReturn('*5678');

        $this->app->instance(MikrotikService::class, $mikrotikMock);

        $service = app(ServiceProvisioningService::class);

        $result = $service->provisionService($this->customer, $this->package, $this->router);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Service::class, $result['service']);
        $this->assertEquals('active', $result['service']->status);
        $this->assertArrayHasKey('username', $result['credentials']);
        $this->assertArrayHasKey('password', $result['credentials']);
    }

    /** @test */
    public function admin_can_approve_installation_with_service_provisioning()
    {
        // Mock MikrotikService
        $mikrotikMock = Mockery::mock(MikrotikService::class);
        $mikrotikMock->shouldReceive('createPPPoEUser')
            ->once()
            ->andReturn('*9999');

        $this->app->instance(MikrotikService::class, $mikrotikMock);

        $response = $this->actingAs($this->admin)->post(
            route('admin.installations.approve', $this->customer),
            [
                'package_id' => $this->package->id,
                'mikrotik_id' => $this->router->id,
                'notes' => 'Approved for installation',
            ]
        );

        $response->assertRedirect(route('admin.installations.index'));
        $response->assertSessionHas('success');

        // Verify customer status updated
        $this->customer->refresh();
        $this->assertEquals('active', $this->customer->status);

        // Verify service created
        $service = Service::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($service);
        $this->assertEquals('active', $service->status);
        $this->assertEquals($this->package->id, $service->package_id);
        $this->assertEquals($this->router->id, $service->mikrotik_id);
        $this->assertEquals('*9999', $service->mikrotik_user_id);
    }

    /** @test */
    public function admin_cannot_approve_installation_without_package()
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.installations.approve', $this->customer),
            [
                'mikrotik_id' => $this->router->id,
                'notes' => 'Approved',
            ]
        );

        $response->assertSessionHasErrors('package_id');
    }

    /** @test */
    public function admin_cannot_approve_installation_without_router()
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.installations.approve', $this->customer),
            [
                'package_id' => $this->package->id,
                'notes' => 'Approved',
            ]
        );

        $response->assertSessionHasErrors('mikrotik_id');
    }

    /** @test */
    public function admin_cannot_approve_installation_with_inactive_package()
    {
        $inactivePackage = Package::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.installations.approve', $this->customer),
            [
                'package_id' => $inactivePackage->id,
                'mikrotik_id' => $this->router->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test */
    public function admin_cannot_approve_installation_with_inactive_router()
    {
        $inactiveRouter = MikrotikRouter::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.installations.approve', $this->customer),
            [
                'package_id' => $this->package->id,
                'mikrotik_id' => $inactiveRouter->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test */
    public function it_handles_provisioning_failure_gracefully()
    {
        // Mock MikrotikService to fail
        $mikrotikMock = Mockery::mock(MikrotikService::class);
        $mikrotikMock->shouldReceive('createPPPoEUser')
            ->once()
            ->andThrow(new \Exception('Router unreachable'));

        $this->app->instance(MikrotikService::class, $mikrotikMock);

        $response = $this->actingAs($this->admin)->post(
            route('admin.installations.approve', $this->customer),
            [
                'package_id' => $this->package->id,
                'mikrotik_id' => $this->router->id,
            ]
        );

        $response->assertRedirect(route('admin.installations.index'));
        $response->assertSessionHas('warning');

        // Verify service created but marked as failed
        $service = Service::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($service);
        $this->assertEquals('provisioning_failed', $service->status);
    }

    /** @test */
    public function it_isolates_service_by_updating_mikrotik_profile()
    {
        // Create active service
        $serviceRecord = Service::factory()->create([
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'mikrotik_id' => $this->router->id,
            'status' => 'active',
            'mikrotik_user_id' => '*1111',
        ]);

        // Mock MikrotikService
        $mikrotikMock = Mockery::mock(MikrotikService::class);
        $mikrotikMock->shouldReceive('updateUserProfile')
            ->once()
            ->with(
                Mockery::type(MikrotikRouter::class),
                '*1111',
                'Isolir'
            )
            ->andReturn(true);

        $this->app->instance(MikrotikService::class, $mikrotikMock);

        $service = app(ServiceProvisioningService::class);
        $success = $service->isolateService($serviceRecord);

        $this->assertTrue($success);

        // Verify service status updated
        $serviceRecord->refresh();
        $this->assertEquals('isolated', $serviceRecord->status);
    }

    /** @test */
    public function it_restores_service_by_updating_mikrotik_profile()
    {
        // Create isolated service
        $serviceRecord = Service::factory()->create([
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'mikrotik_id' => $this->router->id,
            'status' => 'isolated',
            'mikrotik_user_id' => '*2222',
        ]);

        // Mock MikrotikService
        $mikrotikMock = Mockery::mock(MikrotikService::class);
        $mikrotikMock->shouldReceive('updateUserProfile')
            ->once()
            ->with(
                Mockery::type(MikrotikRouter::class),
                '*2222',
                Mockery::type('string') // profile name
            )
            ->andReturn(true);

        $this->app->instance(MikrotikService::class, $mikrotikMock);

        $service = app(ServiceProvisioningService::class);
        $success = $service->restoreService($serviceRecord);

        $this->assertTrue($success);

        // Verify service status updated
        $serviceRecord->refresh();
        $this->assertEquals('active', $serviceRecord->status);
    }

    /** @test */
    public function it_terminates_service_by_deleting_from_mikrotik()
    {
        // Create active service
        $serviceRecord = Service::factory()->create([
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'mikrotik_id' => $this->router->id,
            'status' => 'active',
            'mikrotik_user_id' => '*3333',
        ]);

        // Mock MikrotikService
        $mikrotikMock = Mockery::mock(MikrotikService::class);
        $mikrotikMock->shouldReceive('deleteUser')
            ->once()
            ->with(
                Mockery::type(MikrotikRouter::class),
                '*3333'
            )
            ->andReturn(true);

        $this->app->instance(MikrotikService::class, $mikrotikMock);

        $service = app(ServiceProvisioningService::class);
        $success = $service->terminateService($serviceRecord);

        $this->assertTrue($success);

        // Verify service status updated
        $serviceRecord->refresh();
        $this->assertEquals('terminated', $serviceRecord->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
