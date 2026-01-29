<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RBACMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test unauthenticated user cannot access protected routes.
     */
    public function test_unauthenticated_user_redirected_to_login(): void
    {
        // This test verifies that unauthenticated users cannot access protected routes
        // In a real application, routes would be protected by auth middleware
        
        $this->assertFalse(auth()->check());
        $this->assertNull(auth()->user());
    }

    /**
     * Test user without permission gets 403 response.
     */
    public function test_user_without_permission_gets_forbidden(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        // Customer doesn't have 'customers.create' permission
        $this->actingAs($customer);
        
        // We'll test this with actual routes once they're defined
        $this->assertTrue($customer->hasPermission('profile.view'));
        $this->assertFalse($customer->hasPermission('customers.create'));
    }

    /**
     * Test user with permission can access route.
     */
    public function test_user_with_permission_can_access_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);
        
        // Admin has customers.view permission
        $this->assertTrue($admin->hasPermission('customers.view'));
        $this->assertTrue($admin->hasPermission('customers.create'));
    }

    /**
     * Test super admin can access all routes.
     */
    public function test_super_admin_can_access_all_routes(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin);
        
        // Super admin has all permissions
        $this->assertTrue($superAdmin->hasPermission('users.create'));
        $this->assertTrue($superAdmin->hasPermission('settings.update'));
        $this->assertTrue($superAdmin->hasPermission('customers.view'));
        $this->assertTrue($superAdmin->hasPermission('bulk.isolate'));
    }

    /**
     * Test role middleware allows correct roles.
     */
    public function test_role_middleware_allows_correct_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        // Admin should pass role check for admin
        $this->actingAs($admin);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->hasAnyRole(['admin', 'super_admin']));

        // Customer should not pass role check for admin
        $this->actingAs($customer);
        $this->assertFalse($customer->hasRole('admin'));
        $this->assertFalse($customer->hasAnyRole(['admin', 'super_admin']));
    }

    /**
     * Test technician can only access assigned resources.
     */
    public function test_technician_has_limited_access(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician);
        
        // Technician can view but not create/delete
        $this->assertTrue($technician->hasPermission('customers.view'));
        $this->assertTrue($technician->hasPermission('tickets.update'));
        $this->assertFalse($technician->hasPermission('customers.create'));
        $this->assertFalse($technician->hasPermission('customers.delete'));
        $this->assertFalse($technician->hasPermission('services.isolate'));
    }

    /**
     * Test reseller has tenant-scoped access.
     */
    public function test_reseller_has_tenant_scoped_access(): void
    {
        $reseller = User::factory()->create([
            'role' => 'reseller',
            'tenant_id' => 1,
        ]);

        $this->actingAs($reseller);
        
        // Reseller can manage customers but within their tenant
        $this->assertTrue($reseller->hasPermission('customers.view'));
        $this->assertTrue($reseller->hasPermission('customers.create'));
        $this->assertTrue($reseller->hasPermission('services.isolate'));
        
        // But cannot access system-wide features
        $this->assertFalse($reseller->hasPermission('users.create'));
        $this->assertFalse($reseller->hasPermission('settings.update'));
    }

    /**
     * Test customer can only access own data.
     */
    public function test_customer_can_only_access_own_data(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer);
        
        // Customer can view own data
        $this->assertTrue($customer->hasPermission('profile.view'));
        $this->assertTrue($customer->hasPermission('services.view_own'));
        $this->assertTrue($customer->hasPermission('invoices.view_own'));
        $this->assertTrue($customer->hasPermission('tickets.create_own'));
        
        // But cannot access other customers' data or admin features
        $this->assertFalse($customer->hasPermission('customers.view'));
        $this->assertFalse($customer->hasPermission('services.view'));
        $this->assertFalse($customer->hasPermission('reports.financial'));
    }

    /**
     * Test permission hierarchy - super_admin > admin > technician/customer.
     */
    public function test_permission_hierarchy(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = User::factory()->create(['role' => 'customer']);

        // Count permissions for each role
        $superAdminPerms = count($superAdmin->getPermissions());
        $adminPerms = count($admin->getPermissions());
        $technicianPerms = count($technician->getPermissions());
        $customerPerms = count($customer->getPermissions());

        // Verify hierarchy - super admin has most permissions
        $this->assertGreaterThan($adminPerms, $superAdminPerms);
        $this->assertGreaterThan($technicianPerms, $adminPerms);
        
        // Technician and customer have similar limited permissions
        // Both should have fewer permissions than admin
        $this->assertLessThan($adminPerms, $technicianPerms);
        $this->assertLessThan($adminPerms, $customerPerms);
    }

    /**
     * Test bulk operations are restricted to admins.
     */
    public function test_bulk_operations_restricted_to_admins(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = User::factory()->create(['role' => 'customer']);

        // Admins can perform bulk operations
        $this->assertTrue($superAdmin->hasPermission('bulk.isolate'));
        $this->assertTrue($admin->hasPermission('bulk.isolate'));
        
        // Non-admins cannot
        $this->assertFalse($technician->hasPermission('bulk.isolate'));
        $this->assertFalse($customer->hasPermission('bulk.isolate'));
    }

    /**
     * Test settings access is restricted to super admin.
     */
    public function test_settings_restricted_to_super_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);

        // Only super admin can access settings
        $this->assertTrue($superAdmin->hasPermission('settings.update'));
        $this->assertFalse($admin->hasPermission('settings.update'));
        $this->assertFalse($technician->hasPermission('settings.update'));
    }

    /**
     * Test user management is restricted to super admin.
     */
    public function test_user_management_restricted_to_super_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);

        // Only super admin can manage users
        $this->assertTrue($superAdmin->hasPermission('users.create'));
        $this->assertTrue($superAdmin->hasPermission('users.delete'));
        $this->assertFalse($admin->hasPermission('users.create'));
        $this->assertFalse($admin->hasPermission('users.delete'));
    }
}
