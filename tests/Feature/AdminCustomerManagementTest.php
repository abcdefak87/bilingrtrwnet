<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin user with proper permissions
        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function admin_can_view_customer_list()
    {
        Customer::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.customers.index');
        $response->assertViewHas('customers');
    }

    /** @test */
    public function admin_can_search_customers_by_name()
    {
        Customer::factory()->create(['name' => 'John Doe']);
        Customer::factory()->create(['name' => 'Jane Smith']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['search' => 'John']));

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertDontSee('Jane Smith');
    }

    /** @test */
    public function admin_can_search_customers_by_phone()
    {
        Customer::factory()->create(['phone' => '081234567890', 'name' => 'Customer A']);
        Customer::factory()->create(['phone' => '089876543210', 'name' => 'Customer B']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['search' => '081234']));

        $response->assertStatus(200);
        $response->assertSee('Customer A');
        $response->assertDontSee('Customer B');
    }

    /** @test */
    public function admin_can_search_customers_by_address()
    {
        Customer::factory()->create(['address' => 'Jl. Merdeka No. 1', 'name' => 'Customer A']);
        Customer::factory()->create(['address' => 'Jl. Sudirman No. 2', 'name' => 'Customer B']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['search' => 'Merdeka']));

        $response->assertStatus(200);
        $response->assertSee('Customer A');
        $response->assertDontSee('Customer B');
    }

    /** @test */
    public function admin_can_search_customers_by_username_pppoe()
    {
        $package = Package::factory()->create();
        $customer1 = Customer::factory()->create(['name' => 'Customer A']);
        $customer2 = Customer::factory()->create(['name' => 'Customer B']);
        
        Service::factory()->create([
            'customer_id' => $customer1->id,
            'package_id' => $package->id,
            'username_pppoe' => 'user123',
        ]);
        
        Service::factory()->create([
            'customer_id' => $customer2->id,
            'package_id' => $package->id,
            'username_pppoe' => 'user456',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['search' => 'user123']));

        $response->assertStatus(200);
        $response->assertSee('Customer A');
        $response->assertDontSee('Customer B');
    }

    /** @test */
    public function admin_can_filter_customers_by_status()
    {
        Customer::factory()->create(['status' => 'active', 'name' => 'Active Customer']);
        Customer::factory()->create(['status' => 'pending_survey', 'name' => 'Pending Customer']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['status' => 'active']));

        $response->assertStatus(200);
        $response->assertSee('Active Customer');
        $response->assertDontSee('Pending Customer');
    }

    /** @test */
    public function admin_can_view_customer_profile()
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $customer));

        $response->assertStatus(200);
        $response->assertViewIs('admin.customers.show');
        $response->assertViewHas('customer');
        $response->assertViewHas('stats');
        $response->assertSee($customer->name);
    }

    /** @test */
    public function customer_profile_displays_related_services()
    {
        $package = Package::factory()->create(['name' => 'Premium Package']);
        $customer = Customer::factory()->create();
        Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'username_pppoe' => 'testuser123',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $customer));

        $response->assertStatus(200);
        $response->assertSee('testuser123');
        $response->assertSee('Premium Package');
    }

    /** @test */
    public function admin_can_view_edit_form()
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.customers.edit', $customer));

        $response->assertStatus(200);
        $response->assertViewIs('admin.customers.edit');
        $response->assertViewHas('customer');
        $response->assertSee($customer->name);
    }

    /** @test */
    public function admin_can_update_customer()
    {
        $customer = Customer::factory()->create([
            'name' => 'Old Name',
            'phone' => '081234567890',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.customers.update', $customer), [
                'name' => 'New Name',
                'phone' => '089876543210',
                'address' => 'New Address',
                'ktp_number' => $customer->ktp_number,
                'status' => 'active',
            ]);

        $response->assertRedirect(route('admin.customers.show', $customer));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'New Name',
            'phone' => '089876543210',
            'address' => 'New Address',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function admin_can_update_customer_with_new_ktp_file()
    {
        Storage::fake('public');

        $customer = Customer::factory()->create([
            'ktp_path' => 'ktp/old-ktp.jpg',
        ]);

        $newKtpFile = UploadedFile::fake()->image('new-ktp.jpg');

        $response = $this->actingAs($this->admin)
            ->put(route('admin.customers.update', $customer), [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'ktp_number' => $customer->ktp_number,
                'status' => $customer->status,
                'ktp_file' => $newKtpFile,
            ]);

        $response->assertRedirect(route('admin.customers.show', $customer));

        $customer->refresh();
        $this->assertNotEquals('ktp/old-ktp.jpg', $customer->ktp_path);
        Storage::disk('public')->assertExists($customer->ktp_path);
    }

    /** @test */
    public function admin_cannot_update_customer_with_duplicate_phone()
    {
        $customer1 = Customer::factory()->create(['phone' => '081234567890']);
        $customer2 = Customer::factory()->create(['phone' => '089876543210']);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.customers.update', $customer2), [
                'name' => $customer2->name,
                'phone' => '081234567890', // Duplicate phone
                'address' => $customer2->address,
                'ktp_number' => $customer2->ktp_number,
                'status' => $customer2->status,
            ]);

        $response->assertSessionHasErrors('phone');
    }

    /** @test */
    public function admin_can_delete_customer_without_active_services()
    {
        Storage::fake('public');

        $customer = Customer::factory()->create([
            'ktp_path' => 'ktp/test-ktp.jpg',
        ]);

        Storage::disk('public')->put('ktp/test-ktp.jpg', 'fake content');

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.customers.destroy', $customer));

        $response->assertRedirect(route('admin.customers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }

    /** @test */
    public function admin_cannot_delete_customer_with_active_services()
    {
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();
        Service::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.customers.destroy', $customer));

        $response->assertRedirect(route('admin.customers.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
        ]);
    }

    /** @test */
    public function guest_cannot_access_admin_customer_routes()
    {
        $customer = Customer::factory()->create();

        $this->get(route('admin.customers.index'))
            ->assertRedirect(route('login'));

        $this->get(route('admin.customers.show', $customer))
            ->assertRedirect(route('login'));

        $this->get(route('admin.customers.edit', $customer))
            ->assertRedirect(route('login'));

        $this->put(route('admin.customers.update', $customer), [])
            ->assertRedirect(route('login'));

        $this->delete(route('admin.customers.destroy', $customer))
            ->assertRedirect(route('login'));
    }
}
