<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition()
    {
        return [
            'payment_id' => Payment::factory(),
            'invoice_number' => 'INV-' . $this->faker->unique()->numerify('######'),
            'total_amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'status' => $this->faker->randomElement(['paid', 'pending', 'failed', 'cancelled']),
            'type' => $this->faker->randomElement(['payment', 'renewal', 'upgrade', 'refund']),
            'issued_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'due_at' => $this->faker->dateTimeBetween('now', '+30 days'),
            'paid_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'items' => [
                [
                    'description' => 'IPTV Subscription',
                    'amount' => $this->faker->randomFloat(2, 10, 500),
                    'currency' => 'USD'
                ]
            ],
            'customer_email' => $this->faker->email,
            'customer_name' => $this->faker->name,
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => now()
        ];
    }
}