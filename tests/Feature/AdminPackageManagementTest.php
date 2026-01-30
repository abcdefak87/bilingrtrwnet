<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPackageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user with package permissions
        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function admin_can_view_packages_list()
    {
        Package::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.packages.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.packages.index');
        $response->assertViewHas('packages');
    }

    /** @test */
    public function admin_can_search_packages_by_name()
    {
        Package::factory()->create(['name' => 'Paket Premium']);
        Package::factory()->create(['name' => 'Paket Basic']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.packages.index', ['search' => 'Premium']));

        $response->assertStatus(200);
        $response->assertSee('Paket Premium');
        $response->assertDontSee('Paket Basic');
    }

    /** @test */
    public function admin_can_filter_packages_by_type()
    {
        Package::factory()->create(['name' => 'Unlimited Package', 'type' => 'unlimited']);
        Package::factory()->create(['name' => 'FUP Package', 'type' => 'fup']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.packages.index', ['type' => 'fup']));

        $response->assertStatus(200);
        $response->assertSee('FUP Package');
        $response->assertDontSee('Unlimited Package');
    }

    /** @test */
    public function admin_can_filter_packages_by_status()
    {
        Package::factory()->create(['name' => 'Active Package', 'is_active' => true]);
        Package::factory()->create(['name' => 'Inactive Package', 'is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.packages.index', ['status' => '1']));

        $response->assertStatus(200);
        $response->assertSee('Active Package');
        $response->assertDontSee('Inactive Package');
    }

    /** @test */
    public function admin_can_view_create_package_form()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.packages.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.packages.create');
    }

    /** @test */
    public function admin_can_create_unlimited_package()
    {
        $packageData = [
            'name' => 'Paket Unlimited 10 Mbps',
            'speed' => '10 Mbps',
            'price' => 250000,
            'type' => 'unlimited',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), $packageData);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('packages', [
            'name' => 'Paket Unlimited 10 Mbps',
            'speed' => '10 Mbps',
            'price' => 250000,
            'type' => 'unlimited',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function admin_can_create_fup_package_with_configuration()
    {
        $packageData = [
            'name' => 'Paket FUP 20 Mbps',
            'speed' => '20 Mbps',
            'price' => 350000,
            'type' => 'fup',
            'fup_threshold' => 100,
            'fup_speed' => '2 Mbps',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), $packageData);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('packages', [
            'name' => 'Paket FUP 20 Mbps',
            'type' => 'fup',
            'fup_threshold' => 100,
            'fup_speed' => '2 Mbps',
        ]);
    }

    /** @test */
    public function creating_fup_package_requires_fup_configuration()
    {
        $packageData = [
            'name' => 'Paket FUP Incomplete',
            'speed' => '20 Mbps',
            'price' => 350000,
            'type' => 'fup',
            'is_active' => true,
            // Missing fup_threshold and fup_speed
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), $packageData);

        $response->assertSessionHasErrors(['fup_threshold', 'fup_speed']);
    }

    /** @test */
    public function package_creation_validates_required_fields()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), []);

        $response->assertSessionHasErrors(['name', 'speed', 'price', 'type']);
    }

    /** @test */
    public function package_creation_validates_price_is_numeric()
    {
        $packageData = [
            'name' => 'Test Package',
            'speed' => '10 Mbps',
            'price' => 'invalid',
            'type' => 'unlimited',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), $packageData);

        $response->assertSessionHasErrors(['price']);
    }

    /** @test */
    public function package_creation_validates_type_is_valid()
    {
        $packageData = [
            'name' => 'Test Package',
            'speed' => '10 Mbps',
            'price' => 250000,
            'type' => 'invalid_type',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.packages.store'), $packageData);

        $response->assertSessionHasErrors(['type']);
    }

    /** @test */
    public function admin_can_view_package_details()
    {
        $package = Package::factory()->create([
            'name' => 'Test Package',
            'type' => 'fup',
            'fup_threshold' => 100,
            'fup_speed' => '2 Mbps',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.packages.show', $package));

        $response->assertStatus(200);
        $response->assertViewIs('admin.packages.show');
        $response->assertSee('Test Package');
        $response->assertSee('100 GB');
        $response->assertSee('2 Mbps');
    }

    /** @test */
    public function admin_can_view_edit_package_form()
    {
        $package = Package::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.packages.edit', $package));

        $response->assertStatus(200);
        $response->assertViewIs('admin.packages.edit');
        $response->assertSee($package->name);
    }

    /** @test */
    public function admin_can_update_package()
    {
        $package = Package::factory()->create([
            'name' => 'Old Name',
            'price' => 200000,
            'type' => 'unlimited', // Use unlimited to avoid FUP validation
        ]);

        $updateData = [
            'name' => 'New Name',
            'speed' => $package->speed,
            'price' => 300000,
            'type' => 'unlimited',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.packages.update', $package), $updateData);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'name' => 'New Name',
            'price' => 300000,
        ]);
    }

    /** @test */
    public function admin_can_toggle_package_active_status()
    {
        $package = Package::factory()->create([
            'is_active' => true,
            'type' => 'unlimited', // Use unlimited to avoid FUP validation
        ]);

        $updateData = [
            'name' => $package->name,
            'speed' => $package->speed,
            'price' => $package->price,
            'type' => 'unlimited',
            'is_active' => false,
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.packages.update', $package), $updateData);

        $response->assertRedirect(route('admin.packages.index'));

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function admin_can_delete_package_without_services()
    {
        $package = Package::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.packages.destroy', $package));

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('packages', [
            'id' => $package->id,
        ]);
    }

    /** @test */
    public function admin_cannot_delete_package_with_active_services()
    {
        $package = Package::factory()->create();
        Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.packages.destroy', $package));

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
        ]);
    }

    /** @test */
    public function admin_cannot_delete_package_with_any_services()
    {
        $package = Package::factory()->create();
        $service = Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'terminated',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.packages.destroy', $package));

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');

        // Package should still exist
        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
        ]);
        
        // Service should still exist
        $this->assertDatabaseHas('services', [
            'id' => $service->id,
        ]);
    }

    /** @test */
    public function guest_cannot_access_package_management()
    {
        $package = Package::factory()->create();

        $this->get(route('admin.packages.index'))->assertRedirect(route('login'));
        $this->get(route('admin.packages.create'))->assertRedirect(route('login'));
        $this->get(route('admin.packages.show', $package))->assertRedirect(route('login'));
        $this->get(route('admin.packages.edit', $package))->assertRedirect(route('login'));
        $this->post(route('admin.packages.store'), [])->assertRedirect(route('login'));
        $this->put(route('admin.packages.update', $package), [])->assertRedirect(route('login'));
        $this->delete(route('admin.packages.destroy', $package))->assertRedirect(route('login'));
    }
}
