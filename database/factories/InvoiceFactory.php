<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoiceDate = fake()->dateTimeBetween('-1 month', 'now');
        
        return [
            'service_id' => Service::factory(),
            'amount' => fake()->randomElement([100000, 150000, 200000, 300000]),
            'status' => fake()->randomElement(['unpaid', 'paid', 'overdue', 'cancelled']),
            'invoice_date' => $invoiceDate,
            'due_date' => (clone $invoiceDate)->modify('+7 days'),
            'payment_link' => fake()->url(),
            'paid_at' => null,
            'tenant_id' => null,
        ];
    }
}
