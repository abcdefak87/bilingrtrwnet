<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function it_displays_customer_registration_form_with_active_packages()
    {
        // Create active and inactive packages
        $activePackage = Package::factory()->create(['is_active' => true, 'name' => 'Active Package']);
        $inactivePackage = Package::factory()->create(['is_active' => false, 'name' => 'Inactive Package']);

        $response = $this->get(route('customer.register'));

        $response->assertStatus(200);
        $response->assertSee('Active Package');
        $response->assertDontSee('Inactive Package');
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->post(route('customer.register.store'), []);

        $response->assertSessionHasErrors([
            'name',
            'phone',
            'address',
            'ktp_number',
            'ktp_file',
            'package_id',
        ]);
    }

    /** @test */
    public function it_validates_ktp_file_type()
    {
        $package = Package::factory()->create(['is_active' => true]);
        $invalidFile = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->post(route('customer.register.store'), [
            'name' => 'John Doe',
            'phone' => '081234567890',
            'address' => 'Jl. Test No. 123',
            'ktp_number' => '1234567890123456',
            'ktp_file' => $invalidFile,
            'package_id' => $package->id,
        ]);

        $response->assertSessionHasErrors(['ktp_file']);
    }

    /** @test */
    public function it_validates_ktp_file_size()
    {
        $package = Package::factory()->create(['is_active' => true]);
        // Create a file larger than 2MB
        $largeFile = UploadedFile::fake()->create('ktp.jpg', 3000);

        $response = $this->post(route('customer.register.store'), [
            'name' => 'John Doe',
            'phone' => '081234567890',
            'address' => 'Jl. Test No. 123',
            'ktp_number' => '1234567890123456',
            'ktp_file' => $largeFile,
            'package_id' => $package->id,
        ]);

        $response->assertSessionHasErrors(['ktp_file']);
    }

    /** @test */
    public function it_validates_unique_phone_number()
    {
        $package = Package::factory()->create(['is_active' => true]);
        Customer::factory()->create(['phone' => '081234567890']);

        $response = $this->post(route('customer.register.store'), [
            'name' => 'John Doe',
            'phone' => '081234567890',
            'address' => 'Jl. Test No. 123',
            'ktp_number' => '1234567890123456',
            'ktp_file' => UploadedFile::fake()->image('ktp.jpg'),
            'package_id' => $package->id,
        ]);

        $response->assertSessionHasErrors(['phone']);
    }

    /** @test */
    public function it_validates_unique_ktp_number()
    {
        $package = Package::factory()->create(['is_active' => true]);
        Customer::factory()->create(['ktp_number' => '1234567890123456']);

        $response = $this->post(route('customer.register.store'), [
            'name' => 'John Doe',
            'phone' => '081234567890',
            'address' => 'Jl. Test No. 123',
            'ktp_number' => '1234567890123456',
            'ktp_file' => UploadedFile::fake()->image('ktp.jpg'),
            'package_id' => $package->id,
        ]);

        $response->assertSessionHasErrors(['ktp_number']);
    }

    /** @test */
    public function it_successfully_registers_customer_with_valid_data()
    {
        $package = Package::factory()->create(['is_active' => true]);
        $ktpFile = UploadedFile::fake()->image('ktp.jpg', 800, 600);

        $response = $this->post(route('customer.register.store'), [
            'name' => 'John Doe',
            'phone' => '081234567890',
            'address' => 'Jl. Test No. 123, Jakarta',
            'ktp_number' => '1234567890123456',
            'ktp_file' => $ktpFile,
            'package_id' => $package->id,
            'latitude' => -6.200000,
            'longitude' => 106.816666,
        ]);

        $response->assertRedirect(route('customer.registration.success'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'phone' => '081234567890',
            'address' => 'Jl. Test No. 123, Jakarta',
            'ktp_number' => '1234567890123456',
            'status' => 'pending_survey',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
        ]);

        // Verify KTP file was stored
        $customer = Customer::where('phone', '081234567890')->first();
        $this->assertNotNull($customer->ktp_path);
        Storage::disk('public')->assertExists($customer->ktp_path);
    }

    /** @test */
    public function it_stores_customer_with_pending_survey_status()
    {
        $package = Package::factory()->create(['is_active' => true]);

        $this->post(route('customer.register.store'), [
            'name' => 'Jane Doe',
            'phone' => '082345678901',
            'address' => 'Jl. Example No. 456',
            'ktp_number' => '6543210987654321',
            'ktp_file' => UploadedFile::fake()->image('ktp.jpg'),
            'package_id' => $package->id,
        ]);

        $customer = Customer::where('phone', '082345678901')->first();
        $this->assertEquals('pending_survey', $customer->status);
    }

    /** @test */
    public function it_accepts_jpg_png_and_pdf_file_formats()
    {
        $package = Package::factory()->create(['is_active' => true]);

        // Test JPG
        $jpgFile = UploadedFile::fake()->image('ktp.jpg');
        $response = $this->post(route('customer.register.store'), [
            'name' => 'Test JPG',
            'phone' => '081111111111',
            'address' => 'Test Address',
            'ktp_number' => '1111111111111111',
            'ktp_file' => $jpgFile,
            'package_id' => $package->id,
        ]);
        $response->assertRedirect(route('customer.registration.success'));

        // Test PNG
        $pngFile = UploadedFile::fake()->image('ktp.png');
        $response = $this->post(route('customer.register.store'), [
            'name' => 'Test PNG',
            'phone' => '082222222222',
            'address' => 'Test Address',
            'ktp_number' => '2222222222222222',
            'ktp_file' => $pngFile,
            'package_id' => $package->id,
        ]);
        $response->assertRedirect(route('customer.registration.success'));

        // Test PDF
        $pdfFile = UploadedFile::fake()->create('ktp.pdf', 500, 'application/pdf');
        $response = $this->post(route('customer.register.store'), [
            'name' => 'Test PDF',
            'phone' => '083333333333',
            'address' => 'Test Address',
            'ktp_number' => '3333333333333333',
            'ktp_file' => $pdfFile,
            'package_id' => $package->id,
        ]);
        $response->assertRedirect(route('customer.registration.success'));
    }

    /** @test */
    public function it_stores_selected_package_in_session()
    {
        $package = Package::factory()->create(['is_active' => true]);

        $this->post(route('customer.register.store'), [
            'name' => 'John Doe',
            'phone' => '084444444444',
            'address' => 'Test Address',
            'ktp_number' => '4444444444444444',
            'ktp_file' => UploadedFile::fake()->image('ktp.jpg'),
            'package_id' => $package->id,
        ]);

        $this->assertEquals($package->id, session('customer_package_id'));
    }

    /** @test */
    public function it_displays_registration_success_page()
    {
        $response = $this->get(route('customer.registration.success'));

        $response->assertStatus(200);
        $response->assertSee('Pendaftaran Berhasil!');
        $response->assertSee('Langkah Selanjutnya:');
    }

    /** @test */
    public function it_handles_optional_latitude_and_longitude()
    {
        $package = Package::factory()->create(['is_active' => true]);

        // Without coordinates
        $response = $this->post(route('customer.register.store'), [
            'name' => 'No Coords',
            'phone' => '085555555555',
            'address' => 'Test Address',
            'ktp_number' => '5555555555555555',
            'ktp_file' => UploadedFile::fake()->image('ktp.jpg'),
            'package_id' => $package->id,
        ]);

        $response->assertRedirect(route('customer.registration.success'));
        
        $customer = Customer::where('phone', '085555555555')->first();
        $this->assertNull($customer->latitude);
        $this->assertNull($customer->longitude);
    }
}
