<?php

namespace Database\Factories;

use App\Models\Refund;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition()
    {
        return [
            'payment_id' => Payment::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 200),
            'currency' => 'USD',
            'reason' => $this->faker->randomElement([
                'customer_request',
                'duplicate_payment',
                'service_not_delivered',
                'technical_issue'
            ]),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed']),
            'gateway_refund_id' => 're_' . $this->faker->md5,
            'processed_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'notes' => $this->faker->optional()->sentence,
            'created_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'updated_at' => now()
        ];
    }
}