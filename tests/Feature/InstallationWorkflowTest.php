<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use App\Services\ServiceProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InstallationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);

        // Create technician user
        $this->technician = User::factory()->create([
            'role' => 'technician',
            'email' => 'tech@test.com',
        ]);
    }

    /** @test */
    public function admin_can_view_installation_index()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.installations.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.installations.index');
    }

    /** @test */
    public function admin_can_assign_technician_to_pending_survey_customer()
    {
        // Create a customer with pending_survey status
        $customer = Customer::factory()->create([
            'status' => 'pending_survey',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.assign-technician', $customer), [
                'technician_id' => $this->technician->id,
                'notes' => 'Please conduct survey tomorrow',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify customer status updated to survey_scheduled
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'survey_scheduled',
        ]);
    }

    /** @test */
    public function cannot_assign_technician_to_customer_not_in_pending_survey()
    {
        // Create a customer with survey_scheduled status
        $customer = Customer::factory()->create([
            'status' => 'survey_scheduled',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.assign-technician', $customer), [
                'technician_id' => $this->technician->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify customer status unchanged
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'survey_scheduled',
        ]);
    }

    /** @test */
    public function cannot_assign_non_technician_user()
    {
        $customer = Customer::factory()->create([
            'status' => 'pending_survey',
        ]);

        $nonTechnician = User::factory()->create([
            'role' => 'customer',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.assign-technician', $customer), [
                'technician_id' => $nonTechnician->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify customer status unchanged
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'pending_survey',
        ]);
    }

    /** @test */
    public function technician_id_is_required_for_assignment()
    {
        $customer = Customer::factory()->create([
            'status' => 'pending_survey',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.assign-technician', $customer), [
                'technician_id' => '',
            ]);

        $response->assertSessionHasErrors('technician_id');
    }

    /** @test */
    public function can_update_status_to_survey_complete()
    {
        // Create a customer with survey_scheduled status
        $customer = Customer::factory()->create([
            'status' => 'survey_scheduled',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.update-status', $customer), [
                'status' => 'survey_complete',
                'notes' => 'Survey completed successfully. Location is suitable.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify customer status updated to survey_complete
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'survey_complete',
        ]);
    }

    /** @test */
    public function cannot_update_status_if_not_survey_scheduled()
    {
        // Create a customer with pending_survey status
        $customer = Customer::factory()->create([
            'status' => 'pending_survey',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.update-status', $customer), [
                'status' => 'survey_complete',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify customer status unchanged
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'pending_survey',
        ]);
    }

    /** @test */
    public function admin_can_view_approval_page_for_survey_complete_customer()
    {
        $customer = Customer::factory()->create([
            'status' => 'survey_complete',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.installations.approval', $customer));

        $response->assertStatus(200);
        $response->assertViewIs('admin.installations.approval');
        $response->assertViewHas('customer', $customer);
    }

    /** @test */
    public function cannot_view_approval_page_for_customer_not_survey_complete()
    {
        $customer = Customer::factory()->create([
            'status' => 'pending_survey',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.installations.approval', $customer));

        $response->assertRedirect(route('admin.installations.index'));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function admin_can_approve_installation()
    {
        $customer = Customer::factory()->create([
            'status' => 'survey_complete',
        ]);
        
        $package = Package::factory()->create(['is_active' => true]);
        $router = MikrotikRouter::factory()->create(['is_active' => true]);

        // Mock ServiceProvisioningService to avoid actual Mikrotik API calls
        $mockService = Mockery::mock(ServiceProvisioningService::class);
        
        // Create a mock service that will be returned
        $mockServiceRecord = Service::factory()->make([
            'id' => 1,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'username_pppoe' => 'pppoe_test_user',
            'status' => 'active',
        ]);
        
        $mockService->shouldReceive('provisionService')
            ->once()
            ->with(
                Mockery::on(fn($c) => $c->id === $customer->id),
                Mockery::on(fn($p) => $p->id === $package->id),
                Mockery::on(fn($r) => $r->id === $router->id)
            )
            ->andReturn([
                'service' => $mockServiceRecord,
                'success' => true,
                'credentials' => [
                    'username' => 'pppoe_test_user',
                    'password' => 'test_password',
                ],
            ]);
        
        $this->app->instance(ServiceProvisioningService::class, $mockService);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.approve', $customer), [
                'package_id' => $package->id,
                'mikrotik_id' => $router->id,
                'notes' => 'Approved for installation',
            ]);

        $response->assertRedirect(route('admin.installations.index'));
        $response->assertSessionHas('success');

        // Verify customer status updated to active (after successful provisioning)
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function cannot_approve_installation_if_not_survey_complete()
    {
        $customer = Customer::factory()->create([
            'status' => 'pending_survey',
        ]);
        
        $package = Package::factory()->create(['is_active' => true]);
        $router = MikrotikRouter::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.approve', $customer), [
                'package_id' => $package->id,
                'mikrotik_id' => $router->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify customer status unchanged
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'pending_survey',
        ]);
    }

    /** @test */
    public function admin_can_reject_installation()
    {
        $customer = Customer::factory()->create([
            'status' => 'survey_complete',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.reject', $customer), [
                'reason' => 'Location not suitable for fiber installation',
            ]);

        $response->assertRedirect(route('admin.installations.index'));
        $response->assertSessionHas('success');

        // Verify customer status reverted to pending_survey
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'pending_survey',
        ]);
    }

    /** @test */
    public function rejection_reason_is_required()
    {
        $customer = Customer::factory()->create([
            'status' => 'survey_complete',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.reject', $customer), [
                'reason' => '',
            ]);

        $response->assertSessionHasErrors('reason');
    }

    /** @test */
    public function cannot_reject_installation_if_not_survey_complete()
    {
        $customer = Customer::factory()->create([
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.installations.reject', $customer), [
                'reason' => 'Some reason',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify customer status unchanged
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function installation_workflow_follows_correct_status_transitions()
    {
        // Start with pending_survey
        $customer = Customer::factory()->create([
            'status' => 'pending_survey',
        ]);
        
        $package = Package::factory()->create(['is_active' => true]);
        $router = MikrotikRouter::factory()->create(['is_active' => true]);

        // Step 1: Assign technician (pending_survey → survey_scheduled)
        $this->actingAs($this->admin)
            ->post(route('admin.installations.assign-technician', $customer), [
                'technician_id' => $this->technician->id,
            ]);

        $customer->refresh();
        $this->assertEquals('survey_scheduled', $customer->status);

        // Step 2: Update status to survey_complete (survey_scheduled → survey_complete)
        $this->actingAs($this->admin)
            ->post(route('admin.installations.update-status', $customer), [
                'status' => 'survey_complete',
            ]);

        $customer->refresh();
        $this->assertEquals('survey_complete', $customer->status);

        // Mock ServiceProvisioningService for approval step
        $mockService = Mockery::mock(ServiceProvisioningService::class);
        
        $mockServiceRecord = Service::factory()->make([
            'id' => 1,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'username_pppoe' => 'pppoe_test_user',
            'status' => 'active',
        ]);
        
        $mockService->shouldReceive('provisionService')
            ->once()
            ->andReturn([
                'service' => $mockServiceRecord,
                'success' => true,
                'credentials' => [
                    'username' => 'pppoe_test_user',
                    'password' => 'test_password',
                ],
            ]);
        
        $this->app->instance(ServiceProvisioningService::class, $mockService);

        // Step 3: Approve installation (survey_complete → active)
        $this->actingAs($this->admin)
            ->post(route('admin.installations.approve', $customer), [
                'package_id' => $package->id,
                'mikrotik_id' => $router->id,
            ]);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
    }

    /** @test */
    public function rejected_installation_can_be_reprocessed()
    {
        // Create customer with survey_complete status
        $customer = Customer::factory()->create([
            'status' => 'survey_complete',
        ]);
        
        $package = Package::factory()->create(['is_active' => true]);
        $router = MikrotikRouter::factory()->create(['is_active' => true]);

        // Reject installation
        $this->actingAs($this->admin)
            ->post(route('admin.installations.reject', $customer), [
                'reason' => 'Initial rejection reason',
            ]);

        $customer->refresh();
        $this->assertEquals('pending_survey', $customer->status);

        // Reassign technician
        $this->actingAs($this->admin)
            ->post(route('admin.installations.assign-technician', $customer), [
                'technician_id' => $this->technician->id,
            ]);

        $customer->refresh();
        $this->assertEquals('survey_scheduled', $customer->status);

        // Complete survey again
        $this->actingAs($this->admin)
            ->post(route('admin.installations.update-status', $customer), [
                'status' => 'survey_complete',
            ]);

        $customer->refresh();
        $this->assertEquals('survey_complete', $customer->status);

        // Mock ServiceProvisioningService for approval
        $mockService = Mockery::mock(ServiceProvisioningService::class);
        
        $mockServiceRecord = Service::factory()->make([
            'id' => 1,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'mikrotik_id' => $router->id,
            'username_pppoe' => 'pppoe_test_user',
            'status' => 'active',
        ]);
        
        $mockService->shouldReceive('provisionService')
            ->once()
            ->andReturn([
                'service' => $mockServiceRecord,
                'success' => true,
                'credentials' => [
                    'username' => 'pppoe_test_user',
                    'password' => 'test_password',
                ],
            ]);
        
        $this->app->instance(ServiceProvisioningService::class, $mockService);

        // Approve this time
        $this->actingAs($this->admin)
            ->post(route('admin.installations.approve', $customer), [
                'package_id' => $package->id,
                'mikrotik_id' => $router->id,
            ]);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
    }
}
