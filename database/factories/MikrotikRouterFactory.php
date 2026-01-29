<?php

namespace Database\Factories;

use App\Models\MikrotikRouter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MikrotikRouter>
 */
class MikrotikRouterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = MikrotikRouter::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Router ' . fake()->city(),
            'ip_address' => fake()->localIpv4(),
            'username' => 'admin',
            'password_encrypted' => Crypt::encryptString('password'),
            'api_port' => 8728,
            'snmp_community' => 'public',
            'is_active' => true,
        ];
    }
}
