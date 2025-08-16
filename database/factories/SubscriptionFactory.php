<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Website;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'subscription_id' => $this->faker->uuid,
            'payment_id' => Payment::factory(),
            'plan_id' => Plan::factory(),
            'website_id' => Website::factory(),
            'customer_email' => $this->faker->email,
            'customer_phone' => $this->faker->optional()->phoneNumber,
            'status' => $this->faker->randomElement(['active', 'expired', 'cancelled']),
            'starts_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'expires_at' => $this->faker->dateTimeBetween('now', '+30 days'),
            'plan_data' => [
                'name' => $this->faker->words(2, true),
                'price' => $this->faker->randomFloat(2, 10, 500),
                'features' => $this->faker->words(5)
            ],
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => now()
        ];
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'starts_at' => now()->subDays(10),
                'expires_at' => now()->addDays(20)
            ];
        });
    }

    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'expired',
                'expires_at' => now()->subDays(5)
            ];
        });
    }

    public function cancelled()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'cancelled'
            ];
        });
    }
}
