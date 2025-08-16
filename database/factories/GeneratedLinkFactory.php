<?php

namespace Database\Factories;

use App\Models\GeneratedLink;
use App\Models\Website;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GeneratedLinkFactory extends Factory
{
    protected $model = GeneratedLink::class;

    public function definition()
    {
        return [
            'website_id' => Website::factory(),
            'plan_id' => Plan::factory(),
            'token' => Str::random(32),
            'success_url' => $this->faker->url . '/success',
            'failure_url' => $this->faker->url . '/failure',
            'price' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'single_use' => $this->faker->boolean(30),
            'is_used' => false,
            'is_active' => true,
            'created_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'updated_at' => now()
        ];
    }
}