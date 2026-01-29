<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RBACTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test super admin has all permissions.
     */
    public function test_super_admin_has_all_permissions(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertTrue($user->hasPermission('users.create'));
        $this->assertTrue($user->hasPermission('customers.view'));
        $this->assertTrue($user->hasPermission('settings.update'));
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin());
    }

    /**
     * Test admin has operational permissions but not user management.
     */
    public function test_admin_has_operational_permissions(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($user->hasPermission('customers.view'));
        $this->assertTrue($user->hasPermission('customers.create'));
        $this->assertTrue($user->hasPermission('services.isolate'));
        $this->assertTrue($user->hasPermission('reports.financial'));
        $this->assertFalse($user->hasPermission('users.create'));
        $this->assertFalse($user->hasPermission('settings.update'));
        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue($user->isAdmin());
    }

    /**
     * Test technician has limited permissions.
     */
    public function test_technician_has_limited_permissions(): void
    {
        $user = User::factory()->create(['role' => 'technician']);

        $this->assertTrue($user->hasPermission('customers.view'));
        $this->assertTrue($user->hasPermission('tickets.view'));
        $this->assertTrue($user->hasPermission('tickets.update'));
        $this->assertFalse($user->hasPermission('customers.create'));
        $this->assertFalse($user->hasPermission('customers.delete'));
        $this->assertFalse($user->hasPermission('services.isolate'));
        $this->assertTrue($user->isTechnician());
        $this->assertFalse($user->isAdmin());
    }

    /**
     * Test customer has only own data permissions.
     */
    public function test_customer_has_own_data_permissions(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->assertTrue($user->hasPermission('profile.view'));
        $this->assertTrue($user->hasPermission('services.view_own'));
        $this->assertTrue($user->hasPermission('invoices.view_own'));
        $this->assertTrue($user->hasPermission('tickets.create_own'));
        $this->assertFalse($user->hasPermission('customers.view'));
        $this->assertFalse($user->hasPermission('services.view'));
        $this->assertTrue($user->isCustomer());
        $this->assertFalse($user->isAdmin());
    }

    /**
     * Test reseller has tenant-scoped permissions.
     */
    public function test_reseller_has_tenant_permissions(): void
    {
        $user = User::factory()->create([
            'role' => 'reseller',
            'tenant_id' => 1,
        ]);

        $this->assertTrue($user->hasPermission('customers.view'));
        $this->assertTrue($user->hasPermission('customers.create'));
        $this->assertTrue($user->hasPermission('services.isolate'));
        $this->assertTrue($user->hasPermission('reports.financial'));
        $this->assertFalse($user->hasPermission('users.create'));
        $this->assertFalse($user->hasPermission('settings.update'));
        $this->assertTrue($user->isReseller());
        $this->assertFalse($user->isAdmin());
    }

    /**
     * Test hasAnyPermission method.
     */
    public function test_has_any_permission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->assertTrue($admin->hasAnyPermission(['customers.view', 'users.create']));
        $this->assertFalse($customer->hasAnyPermission(['customers.view', 'services.view']));
        $this->assertTrue($customer->hasAnyPermission(['services.view_own', 'customers.view']));
    }

    /**
     * Test hasAllPermissions method.
     */
    public function test_has_all_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);

        $this->assertTrue($admin->hasAllPermissions(['customers.view', 'customers.create']));
        $this->assertFalse($technician->hasAllPermissions(['customers.view', 'customers.create']));
        $this->assertTrue($technician->hasAllPermissions(['customers.view', 'tickets.view']));
    }

    /**
     * Test hasRole method.
     */
    public function test_has_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertFalse($admin->hasRole('super_admin'));
        $this->assertFalse($admin->hasRole('customer'));
    }

    /**
     * Test hasAnyRole method.
     */
    public function test_has_any_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->assertTrue($admin->hasAnyRole(['admin', 'super_admin']));
        $this->assertFalse($admin->hasAnyRole(['customer', 'technician']));
        $this->assertTrue($customer->hasAnyRole(['customer', 'reseller']));
    }

    /**
     * Test getPermissions method returns correct array.
     */
    public function test_get_permissions_returns_array(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $permissions = $admin->getPermissions();

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        $this->assertContains('customers.view', $permissions);
        $this->assertContains('services.isolate', $permissions);
    }

    /**
     * Test role helper methods.
     */
    public function test_role_helper_methods(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = User::factory()->create(['role' => 'customer']);
        $reseller = User::factory()->create(['role' => 'reseller', 'tenant_id' => 1]);

        // Super Admin
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->isAdmin());
        $this->assertFalse($superAdmin->isTechnician());
        $this->assertFalse($superAdmin->isCustomer());
        $this->assertFalse($superAdmin->isReseller());

        // Admin
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isTechnician());
        $this->assertFalse($admin->isCustomer());
        $this->assertFalse($admin->isReseller());

        // Technician
        $this->assertFalse($technician->isSuperAdmin());
        $this->assertFalse($technician->isAdmin());
        $this->assertTrue($technician->isTechnician());
        $this->assertFalse($technician->isCustomer());
        $this->assertFalse($technician->isReseller());

        // Customer
        $this->assertFalse($customer->isSuperAdmin());
        $this->assertFalse($customer->isAdmin());
        $this->assertFalse($customer->isTechnician());
        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isReseller());

        // Reseller
        $this->assertFalse($reseller->isSuperAdmin());
        $this->assertFalse($reseller->isAdmin());
        $this->assertFalse($reseller->isTechnician());
        $this->assertFalse($reseller->isCustomer());
        $this->assertTrue($reseller->isReseller());
    }

    /**
     * Test permissions configuration is loaded correctly.
     */
    public function test_permissions_config_is_loaded(): void
    {
        $config = config('permissions.roles');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('super_admin', $config);
        $this->assertArrayHasKey('admin', $config);
        $this->assertArrayHasKey('technician', $config);
        $this->assertArrayHasKey('customer', $config);
        $this->assertArrayHasKey('reseller', $config);

        foreach ($config as $role => $data) {
            $this->assertArrayHasKey('description', $data);
            $this->assertArrayHasKey('permissions', $data);
            $this->assertIsArray($data['permissions']);
        }
    }
}
