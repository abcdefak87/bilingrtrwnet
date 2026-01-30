<?php

namespace Tests\Feature;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPackageDisplayTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_displays_only_active_packages_on_public_listing()
    {
        // Create active and inactive packages
        $activePackage = Package::factory()->create([
            'name' => 'Active Package',
            'is_active' => true,
        ]);

        $inactivePackage = Package::factory()->create([
            'name' => 'Inactive Package',
            'is_active' => false,
        ]);

        // Visit public packages page
        $response = $this->get(route('packages.index'));

        // Assert active package is shown
        $response->assertStatus(200);
        $response->assertSee('Active Package');
        
        // Assert inactive package is not shown
        $response->assertDontSee('Inactive Package');
    }

    /** @test */
    public function it_displays_package_details_with_pricing()
    {
        $package = Package::factory()->create([
            'name' => 'Premium Package',
            'speed' => '100 Mbps',
            'price' => 250000,
            'type' => 'unlimited',
            'is_active' => true,
        ]);

        $response = $this->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertSee('Premium Package');
        $response->assertSee('100 Mbps');
        $response->assertSee('250.000'); // Formatted price
    }

    /** @test */
    public function it_displays_fup_details_when_package_has_fup()
    {
        $package = Package::factory()->create([
            'name' => 'FUP Package',
            'speed' => '50 Mbps',
            'price' => 150000,
            'type' => 'fup',
            'fup_threshold' => 100,
            'fup_speed' => '5 Mbps',
            'is_active' => true,
        ]);

        $response = $this->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertSee('FUP Package');
        $response->assertSee('100GB');
        $response->assertSee('5 Mbps');
    }

    /** @test */
    public function it_shows_package_detail_page_for_active_package()
    {
        $package = Package::factory()->create([
            'name' => 'Detail Package',
            'speed' => '75 Mbps',
            'price' => 200000,
            'type' => 'unlimited',
            'is_active' => true,
        ]);

        $response = $this->get(route('packages.show', $package));

        $response->assertStatus(200);
        $response->assertSee('Detail Package');
        $response->assertSee('75 Mbps');
        $response->assertSee('200.000');
        $response->assertSee('Spesifikasi Paket');
    }

    /** @test */
    public function it_returns_404_for_inactive_package_detail()
    {
        $package = Package::factory()->create([
            'name' => 'Inactive Package',
            'is_active' => false,
        ]);

        $response = $this->get(route('packages.show', $package));

        $response->assertStatus(404);
    }

    /** @test */
    public function it_shows_register_link_with_package_id_parameter()
    {
        $package = Package::factory()->create([
            'name' => 'Test Package',
            'is_active' => true,
        ]);

        $response = $this->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertSee(route('customer.register') . '?package_id=' . $package->id);
    }

    /** @test */
    public function it_displays_package_type_badges()
    {
        Package::factory()->create([
            'name' => 'Unlimited Package',
            'type' => 'unlimited',
            'is_active' => true,
        ]);

        Package::factory()->create([
            'name' => 'FUP Package',
            'type' => 'fup',
            'is_active' => true,
        ]);

        Package::factory()->create([
            'name' => 'Quota Package',
            'type' => 'quota',
            'is_active' => true,
        ]);

        $response = $this->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertSee('Unlimited');
        $response->assertSee('FUP');
        $response->assertSee('Quota');
    }

    /** @test */
    public function it_shows_empty_state_when_no_active_packages()
    {
        // Create only inactive packages
        Package::factory()->create(['is_active' => false]);

        $response = $this->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertSee('Belum ada paket yang tersedia saat ini');
    }

    /** @test */
    public function it_orders_packages_by_price_ascending()
    {
        $expensive = Package::factory()->create([
            'name' => 'Expensive',
            'price' => 500000,
            'is_active' => true,
        ]);

        $cheap = Package::factory()->create([
            'name' => 'Cheap',
            'price' => 100000,
            'is_active' => true,
        ]);

        $medium = Package::factory()->create([
            'name' => 'Medium',
            'price' => 250000,
            'is_active' => true,
        ]);

        $response = $this->get(route('packages.index'));

        $response->assertStatus(200);
        
        // Get the response content
        $content = $response->getContent();
        
        // Check that cheap appears before medium, and medium before expensive
        $cheapPos = strpos($content, 'Cheap');
        $mediumPos = strpos($content, 'Medium');
        $expensivePos = strpos($content, 'Expensive');
        
        $this->assertLessThan($mediumPos, $cheapPos);
        $this->assertLessThan($expensivePos, $mediumPos);
    }
}
