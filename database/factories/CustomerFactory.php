<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('08##########'),
            'address' => fake()->address(),
            'ktp_number' => fake()->unique()->numerify('################'),
            'ktp_path' => null,
            'latitude' => fake()->latitude(-10, 6),
            'longitude' => fake()->longitude(95, 141),
            'status' => fake()->randomElement([
                'pending_survey',
                'survey_scheduled',
                'survey_complete',
                'approved',
                'active',
                'suspended',
                'terminated'
            ]),
            'tenant_id' => null,
        ];
    }
}
