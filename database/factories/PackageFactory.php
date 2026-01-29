<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Package::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $speeds = ['10Mbps', '20Mbps', '50Mbps', '100Mbps'];
        
        return [
            'name' => 'Package ' . fake()->randomElement($speeds),
            'speed' => fake()->randomElement($speeds),
            'price' => fake()->randomElement([100000, 150000, 200000, 300000, 500000]),
            'type' => fake()->randomElement(['unlimited', 'fup', 'quota']),
            'fup_threshold' => null,
            'fup_speed' => null,
            'is_active' => true,
        ];
    }
}
