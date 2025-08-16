<?php

namespace Database\Factories;

use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebsiteFactory extends Factory
{
    protected $model = Website::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company . ' IPTV',
            'domain' => $this->faker->domainName,
            'language' => $this->faker->randomElement(['ar', 'en', 'fr']),
            'logo' => $this->faker->optional()->imageUrl(),
            'success_url' => $this->faker->url . '/success',
            'failure_url' => $this->faker->url . '/failure',
            'is_active' => true,
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => now()
        ];
    }
}