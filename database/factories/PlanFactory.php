<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition()
    {
        $prices = [29.99, 49.99, 99.99, 149.99, 199.99];
        $durations = [7, 30, 90, 365];
        
        return [
            'website_id' => \App\Models\Website::factory(),
            'name' => $this->faker->randomElement([
                'خطة أساسية',
                'خطة متقدمة', 
                'خطة مميزة',
                'خطة احترافية'
            ]),
            'description' => $this->faker->paragraph,
            'price' => $this->faker->randomElement($prices),
            'currency' => 'USD',
            'duration_days' => $this->faker->randomElement($durations),
            'features' => [
                'channels' => $this->faker->numberBetween(100, 5000),
                'quality' => $this->faker->randomElement(['HD', '4K', 'SD']),
                'devices' => $this->faker->numberBetween(1, 5),
                'support' => $this->faker->boolean(70)
            ],
            'is_active' => true,
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => now()
        ];
    }
}