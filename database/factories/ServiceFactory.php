<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Service::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'package_id' => Package::factory(),
            'mikrotik_id' => MikrotikRouter::factory(),
            'username_pppoe' => 'user_' . fake()->unique()->numerify('######'),
            'password_encrypted' => Crypt::encryptString('password123'),
            'ip_address' => fake()->localIpv4(),
            'mikrotik_user_id' => null,
            'status' => fake()->randomElement(['pending', 'active', 'isolated', 'suspended', 'terminated']),
            'activation_date' => now(),
            'expiry_date' => now()->addMonth(),
            'tenant_id' => null,
        ];
    }
}
