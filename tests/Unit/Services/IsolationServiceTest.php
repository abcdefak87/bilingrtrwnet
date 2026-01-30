<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use App\Services\IsolationService;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class IsolationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected IsolationService $isolationService;
    protected MikrotikService $mockMikrotikService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock MikrotikService
        $this->mockMikrotikService = Mockery::mock(MikrotikService::class);
        $this->isolationService = new IsolationService($this->mockMikrotikService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_identifies_overdue_services_correctly()
    {
        // Set grace period to 3 days
        config(['billing.grace_period_days' => 3]);

        $customer = Customer::factory()->create();
        $package = Package::factory()->create(['price' => 100000]);
        $router = MikrotikRouter::factory()->create();

        // Create an active service
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => '*1',
        ]);

        // Create an overdue invoice (due date was 5 days ago, beyond grace period)
        $overdueInvoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => Carbon::today()->subDays(5),
            'amount' => 100000,
        ]);

        // Check overdue services
        $overdueServices = $this->isolationService->checkOverdueServices();

        $this->assertCount(1, $overdueServices);
        $this->assertEquals($service->id, $overdueServices->first()->id);
    }

    /** @test */
    public function it_does_not_identify_services_within_grace_period()
    {
        // Set grace period to 3 days
        config(['billing.grace_period_days' => 3]);

        $customer = Customer::factory()->create();
        $package = Package::factory()->create(['price' => 100000]);
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => '*1',
        ]);

        // Create invoice due 2 days ago (still within grace period)
        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => Carbon::today()->subDays(2),
            'amount' => 100000,
        ]);

        $overdueServices = $this->isolationService->checkOverdueServices();

        $this->assertCount(0, $overdueServices);
    }

    /** @test */
    public function it_does_not_identify_paid_invoices()
    {
        config(['billing.grace_period_days' => 3]);

        $customer = Customer::factory()->create();
        $package = Package::factory()->create(['price' => 100000]);
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => '*1',
        ]);

        // Create paid invoice (even though due date passed)
        $paidInvoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'paid',
            'due_date' => Carbon::today()->subDays(5),
            'amount' => 100000,
            'paid_at' => Carbon::now(),
        ]);

        $overdueServices = $this->isolationService->checkOverdueServices();

        $this->assertCount(0, $overdueServices);
    }

    /** @test */
    public function it_does_not_identify_already_isolated_services()
    {
        config(['billing.grace_period_days' => 3]);

        $customer = Customer::factory()->create();
        $package = Package::factory()->create(['price' => 100000]);
        $router = MikrotikRouter::factory()->create();

        // Create service that is already isolated
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
            'mikrotik_user_id' => '*1',
        ]);

        $overdueInvoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => Carbon::today()->subDays(5),
            'amount' => 100000,
        ]);

        $overdueServices = $this->isolationService->checkOverdueServices();

        $this->assertCount(0, $overdueServices);
    }

    /** @test */
    public function it_isolates_service_successfully()
    {
        config(['mikrotik.profiles.isolation' => 'Isolir']);

        $customer = Customer::factory()->create();
        $package = Package::factory()->create(['price' => 100000]);
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => '*1',
        ]);

        // Load the router relationship
        $service->load('mikrotikRouter');

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => Carbon::today()->subDays(5),
        ]);

        // Mock Mikrotik API call
        $this->mockMikrotikService
            ->shouldReceive('updateUserProfile')
            ->once()
            ->with(Mockery::on(function ($arg) use ($router) {
                return $arg->id === $router->id;
            }), '*1', 'Isolir')
            ->andReturn(true);

        $result = $this->isolationService->isolateService($service, $invoice);

        $this->assertTrue($result);

        // Refresh service from database
        $service->refresh();

        $this->assertEquals('isolated', $service->status);
        $this->assertNotNull($service->isolation_timestamp);
    }

    /** @test */
    public function it_handles_isolation_failure_gracefully()
    {
        $customer = Customer::factory()->create();
        $package = Package::factory()->create(['price' => 100000]);
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'active',
            'mikrotik_user_id' => '*1',
        ]);

        // Load the router relationship
        $service->load('mikrotikRouter');

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => Carbon::today()->subDays(5),
        ]);

        // Mock Mikrotik API call to throw exception
        $this->mockMikrotikService
            ->shouldReceive('updateUserProfile')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $result = $this->isolationService->isolateService($service, $invoice);

        $this->assertFalse($result);

        // Service status should remain unchanged
        $service->refresh();
        $this->assertEquals('active', $service->status);
    }

    /** @test */
    public function it_does_not_isolate_service_without_mikrotik_data()
    {
        $customer = Customer::factory()->create();
        $package = Package::factory()->create(['price' => 100000]);

        // Service without mikrotik_user_id
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'active',
            'mikrotik_user_id' => null,
        ]);

        $invoice = Invoice::factory()->create([
            'service_id' => $service->id,
            'status' => 'unpaid',
            'due_date' => Carbon::today()->subDays(5),
        ]);

        // Should not call Mikrotik API
        $this->mockMikrotikService
            ->shouldReceive('updateUserProfile')
            ->never();

        $result = $this->isolationService->isolateService($service, $invoice);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_restores_service_successfully()
    {
        config(['mikrotik.profiles.default_prefix' => 'Package-']);

        $customer = Customer::factory()->create();
        $package = Package::factory()->create([
            'name' => '10Mbps',
            'price' => 100000,
        ]);
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
            'mikrotik_user_id' => '*1',
            'isolation_timestamp' => Carbon::now()->subDays(2),
        ]);

        // Load relationships
        $service->load(['mikrotikRouter', 'package']);

        // Mock Mikrotik API call to restore profile
        $this->mockMikrotikService
            ->shouldReceive('updateUserProfile')
            ->once()
            ->with(Mockery::on(function ($arg) use ($router) {
                return $arg->id === $router->id;
            }), '*1', 'Package-10Mbps')
            ->andReturn(true);

        $result = $this->isolationService->restoreService($service);

        $this->assertTrue($result);

        // Refresh service from database
        $service->refresh();

        $this->assertEquals('active', $service->status);
        $this->assertNull($service->isolation_timestamp);
    }

    /** @test */
    public function it_handles_restoration_failure_gracefully()
    {
        $customer = Customer::factory()->create();
        $package = Package::factory()->create(['name' => '10Mbps']);
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'status' => 'isolated',
            'mikrotik_user_id' => '*1',
            'isolation_timestamp' => Carbon::now()->subDays(2),
        ]);

        // Load relationships
        $service->load(['mikrotikRouter', 'package']);

        // Mock Mikrotik API call to throw exception
        $this->mockMikrotikService
            ->shouldReceive('updateUserProfile')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $result = $this->isolationService->restoreService($service);

        $this->assertFalse($result);

        // Service status should remain unchanged
        $service->refresh();
        $this->assertEquals('isolated', $service->status);
        $this->assertNotNull($service->isolation_timestamp);
    }

    /** @test */
    public function it_does_not_restore_service_without_required_data()
    {
        $customer = Customer::factory()->create();
        $package = Package::factory()->create();

        // Service without mikrotik_user_id
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'isolated',
            'mikrotik_user_id' => null,
        ]);

        // Should not call Mikrotik API
        $this->mockMikrotikService
            ->shouldReceive('updateUserProfile')
            ->never();

        $result = $this->isolationService->restoreService($service);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_checks_if_service_is_isolated()
    {
        $service = Service::factory()->create(['status' => 'isolated']);
        $this->assertTrue($this->isolationService->isIsolated($service));

        $activeService = Service::factory()->create(['status' => 'active']);
        $this->assertFalse($this->isolationService->isIsolated($activeService));
    }

    /** @test */
    public function it_checks_if_service_can_be_isolated()
    {
        $router = MikrotikRouter::factory()->create();

        // Service that can be isolated
        $service = Service::factory()->create([
            'status' => 'active',
            'mikrotik_user_id' => '*1',
            'mikrotik_id' => $router->id,
        ]);
        $this->assertTrue($this->isolationService->canBeIsolated($service));

        // Service already isolated
        $isolatedService = Service::factory()->create([
            'status' => 'isolated',
            'mikrotik_user_id' => '*1',
            'mikrotik_id' => $router->id,
        ]);
        $this->assertFalse($this->isolationService->canBeIsolated($isolatedService));

        // Service without mikrotik_user_id
        $serviceWithoutMikrotik = Service::factory()->create([
            'status' => 'active',
            'mikrotik_user_id' => null,
        ]);
        $this->assertFalse($this->isolationService->canBeIsolated($serviceWithoutMikrotik));
    }

    /** @test */
    public function it_returns_isolation_history()
    {
        $service = Service::factory()->create([
            'status' => 'isolated',
            'isolation_timestamp' => Carbon::now()->subDays(2),
        ]);

        $history = $this->isolationService->getIsolationHistory($service);

        $this->assertCount(1, $history);
        $this->assertEquals($service->id, $history->first()['service_id']);
        $this->assertTrue($history->first()['is_currently_isolated']);
    }

    /** @test */
    public function it_returns_empty_history_for_never_isolated_service()
    {
        $service = Service::factory()->create([
            'status' => 'active',
            'isolation_timestamp' => null,
        ]);

        $history = $this->isolationService->getIsolationHistory($service);

        $this->assertCount(0, $history);
    }

    /** @test */
    public function service_model_has_is_isolated_method()
    {
        $isolatedService = Service::factory()->create(['status' => 'isolated']);
        $this->assertTrue($isolatedService->isIsolated());

        $activeService = Service::factory()->create(['status' => 'active']);
        $this->assertFalse($activeService->isIsolated());
    }

    /** @test */
    public function service_model_has_can_be_isolated_method()
    {
        $router = MikrotikRouter::factory()->create();

        $service = Service::factory()->create([
            'status' => 'active',
            'mikrotik_user_id' => '*1',
            'mikrotik_id' => $router->id,
        ]);
        $this->assertTrue($service->canBeIsolated());

        $isolatedService = Service::factory()->create([
            'status' => 'isolated',
            'mikrotik_user_id' => '*1',
            'mikrotik_id' => $router->id,
        ]);
        $this->assertFalse($isolatedService->canBeIsolated());
    }
}
