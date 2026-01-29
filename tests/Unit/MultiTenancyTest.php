<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Models\MikrotikRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test multi-tenancy support.
 * 
 * Validates Requirements: 18.1, 18.2, 18.3
 */
class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $resellerA;
    protected User $resellerB;
    protected Customer $customerA;
    protected Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin (no tenant)
        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);

        // Create reseller A (tenant 1)
        $this->resellerA = User::factory()->create([
            'role' => 'reseller',
            'tenant_id' => 1,
        ]);

        // Create reseller B (tenant 2)
        $this->resellerB = User::factory()->create([
            'role' => 'reseller',
            'tenant_id' => 2,
        ]);
    }

    /**
     * Test that customers are automatically assigned tenant_id when created by reseller.
     * 
     * Validates: Requirement 18.3
     */
    public function test_customer_inherits_tenant_from_authenticated_user(): void
    {
        // Act as reseller A
        $this->actingAs($this->resellerA);

        // Create customer without explicitly setting tenant_id
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '081234567890',
            'address' => 'Test Address',
            'ktp_number' => '1234567890123456',
            'status' => 'pending_survey',
        ]);

        // Assert tenant_id is automatically assigned
        $this->assertEquals(1, $customer->tenant_id);
        $this->assertTrue($customer->belongsToTenant(1));
    }

    /**
     * Test that resellers can only see their own tenant's customers.
     * 
     * Validates: Requirement 18.2
     */
    public function test_reseller_can_only_see_own_tenant_customers(): void
    {
        // Create customers for different tenants
        $customerA = Customer::factory()->create(['tenant_id' => 1]);
        $customerB = Customer::factory()->create(['tenant_id' => 2]);
        $customerNoTenant = Customer::factory()->create(['tenant_id' => null]);

        // Act as reseller A
        $this->actingAs($this->resellerA);
        $customersForA = Customer::all();

        // Should only see tenant 1 customers
        $this->assertCount(1, $customersForA);
        $this->assertTrue($customersForA->contains($customerA));
        $this->assertFalse($customersForA->contains($customerB));
        $this->assertFalse($customersForA->contains($customerNoTenant));

        // Act as reseller B
        $this->actingAs($this->resellerB);
        $customersForB = Customer::all();

        // Should only see tenant 2 customers
        $this->assertCount(1, $customersForB);
        $this->assertFalse($customersForB->contains($customerA));
        $this->assertTrue($customersForB->contains($customerB));
        $this->assertFalse($customersForB->contains($customerNoTenant));
    }

    /**
     * Test that super admin can see all customers across all tenants.
     * 
     * Validates: Requirement 18.4
     */
    public function test_super_admin_can_see_all_tenants_data(): void
    {
        // Create customers for different tenants
        $customerA = Customer::factory()->create(['tenant_id' => 1]);
        $customerB = Customer::factory()->create(['tenant_id' => 2]);
        $customerNoTenant = Customer::factory()->create(['tenant_id' => null]);

        // Act as super admin
        $this->actingAs($this->superAdmin);
        $allCustomers = Customer::all();

        // Should see all customers
        $this->assertCount(3, $allCustomers);
        $this->assertTrue($allCustomers->contains($customerA));
        $this->assertTrue($allCustomers->contains($customerB));
        $this->assertTrue($allCustomers->contains($customerNoTenant));
    }

    /**
     * Test that services inherit tenant_id from parent customer.
     * 
     * Validates: Requirement 18.3
     */
    public function test_service_inherits_tenant_from_customer(): void
    {
        // Create customer with tenant
        $customer = Customer::factory()->create(['tenant_id' => 1]);
        $package = Package::factory()->create();
        $router = MikrotikRouter::factory()->create();

        // Create service without explicitly setting tenant_id
        $service = Service::create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'username_pppoe' => 'test_user',
            'password_encrypted' => 'encrypted_password',
            'status' => 'pending',
        ]);

        // Assert tenant_id is inherited from customer
        $this->assertEquals(1, $service->tenant_id);
    }

    /**
     * Test that invoices inherit tenant_id from parent service.
     * 
     * Validates: Requirement 18.3
     */
    public function test_invoice_inherits_tenant_from_service(): void
    {
        // Create customer, service with tenant
        $customer = Customer::factory()->create(['tenant_id' => 1]);
        $package = Package::factory()->create();
        $router = MikrotikRouter::factory()->create();
        
        $service = Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'tenant_id' => 1,
        ]);

        // Create invoice without explicitly setting tenant_id
        $invoice = Invoice::create([
            'service_id' => $service->id,
            'amount' => 100000,
            'status' => 'unpaid',
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
        ]);

        // Assert tenant_id is inherited from service
        $this->assertEquals(1, $invoice->tenant_id);
    }

    /**
     * Test that tickets inherit tenant_id from parent customer.
     * 
     * Validates: Requirement 18.3
     */
    public function test_ticket_inherits_tenant_from_customer(): void
    {
        // Create customer with tenant
        $customer = Customer::factory()->create(['tenant_id' => 1]);

        // Create ticket without explicitly setting tenant_id
        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'subject' => 'Test Ticket',
            'description' => 'Test Description',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        // Assert tenant_id is inherited from customer
        $this->assertEquals(1, $ticket->tenant_id);
    }

    /**
     * Test that resellers can only see services for their tenant.
     * 
     * Validates: Requirement 18.2, 18.6
     */
    public function test_reseller_can_only_see_own_tenant_services(): void
    {
        // Create services for different tenants
        $customerA = Customer::factory()->create(['tenant_id' => 1]);
        $customerB = Customer::factory()->create(['tenant_id' => 2]);
        $package = Package::factory()->create();
        $router = MikrotikRouter::factory()->create();

        $serviceA = Service::factory()->create([
            'customer_id' => $customerA->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'tenant_id' => 1,
        ]);

        $serviceB = Service::factory()->create([
            'customer_id' => $customerB->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'tenant_id' => 2,
        ]);

        // Act as reseller A
        $this->actingAs($this->resellerA);
        $servicesForA = Service::all();

        // Should only see tenant 1 services
        $this->assertCount(1, $servicesForA);
        $this->assertTrue($servicesForA->contains($serviceA));
        $this->assertFalse($servicesForA->contains($serviceB));
    }

    /**
     * Test that resellers can only see invoices for their tenant.
     * 
     * Validates: Requirement 18.5
     */
    public function test_reseller_can_only_see_own_tenant_invoices(): void
    {
        // Create invoices for different tenants
        $customerA = Customer::factory()->create(['tenant_id' => 1]);
        $customerB = Customer::factory()->create(['tenant_id' => 2]);
        $package = Package::factory()->create();
        $router = MikrotikRouter::factory()->create();

        $serviceA = Service::factory()->create([
            'customer_id' => $customerA->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'tenant_id' => 1,
        ]);

        $serviceB = Service::factory()->create([
            'customer_id' => $customerB->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'tenant_id' => 2,
        ]);

        $invoiceA = Invoice::factory()->create([
            'service_id' => $serviceA->id,
            'tenant_id' => 1,
        ]);

        $invoiceB = Invoice::factory()->create([
            'service_id' => $serviceB->id,
            'tenant_id' => 2,
        ]);

        // Act as reseller A
        $this->actingAs($this->resellerA);
        $invoicesForA = Invoice::all();

        // Should only see tenant 1 invoices
        $this->assertCount(1, $invoicesForA);
        $this->assertTrue($invoicesForA->contains($invoiceA));
        $this->assertFalse($invoicesForA->contains($invoiceB));
    }

    /**
     * Test that resellers can only see tickets for their tenant.
     * 
     * Validates: Requirement 18.2, 18.6
     */
    public function test_reseller_can_only_see_own_tenant_tickets(): void
    {
        // Create tickets for different tenants
        $customerA = Customer::factory()->create(['tenant_id' => 1]);
        $customerB = Customer::factory()->create(['tenant_id' => 2]);

        $ticketA = Ticket::factory()->create([
            'customer_id' => $customerA->id,
            'tenant_id' => 1,
        ]);

        $ticketB = Ticket::factory()->create([
            'customer_id' => $customerB->id,
            'tenant_id' => 2,
        ]);

        // Act as reseller A
        $this->actingAs($this->resellerA);
        $ticketsForA = Ticket::all();

        // Should only see tenant 1 tickets
        $this->assertCount(1, $ticketsForA);
        $this->assertTrue($ticketsForA->contains($ticketA));
        $this->assertFalse($ticketsForA->contains($ticketB));
    }

    /**
     * Test that withoutTenantScope allows bypassing tenant filtering.
     * 
     * Validates: Requirement 18.4
     */
    public function test_can_bypass_tenant_scope_when_needed(): void
    {
        // Create customers for different tenants
        $customerA = Customer::factory()->create(['tenant_id' => 1]);
        $customerB = Customer::factory()->create(['tenant_id' => 2]);

        // Act as reseller A
        $this->actingAs($this->resellerA);

        // Normal query - should only see tenant 1
        $normalQuery = Customer::all();
        $this->assertCount(1, $normalQuery);

        // Query without tenant scope - should see all
        $withoutScope = Customer::withoutTenantScope()->get();
        $this->assertCount(2, $withoutScope);
    }

    /**
     * Test that forTenant scope filters by specific tenant.
     */
    public function test_for_tenant_scope_filters_correctly(): void
    {
        // Create customers for different tenants
        $customerA = Customer::factory()->create(['tenant_id' => 1]);
        $customerB = Customer::factory()->create(['tenant_id' => 2]);

        // Act as super admin (no automatic filtering)
        $this->actingAs($this->superAdmin);

        // Query for specific tenant
        $tenant1Customers = Customer::forTenant(1)->get();
        $this->assertCount(1, $tenant1Customers);
        $this->assertTrue($tenant1Customers->contains($customerA));

        $tenant2Customers = Customer::forTenant(2)->get();
        $this->assertCount(1, $tenant2Customers);
        $this->assertTrue($tenant2Customers->contains($customerB));
    }

    /**
     * Test that cross-tenant data access is prevented.
     * 
     * Validates: Requirement 18.6
     */
    public function test_cross_tenant_data_access_is_prevented(): void
    {
        // Create customer for tenant 1
        $customerA = Customer::factory()->create(['tenant_id' => 1]);

        // Act as reseller B (tenant 2)
        $this->actingAs($this->resellerB);

        // Try to find customer from tenant 1
        $customer = Customer::find($customerA->id);

        // Should not be able to access
        $this->assertNull($customer);
    }
}
