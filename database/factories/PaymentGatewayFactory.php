<?php

namespace Database\Factories;

use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    public function definition()
    {
        $gateways = ['stripe', 'paypal', 'razorpay', 'square'];
        $name = $this->faker->randomElement($gateways);
        
        return [
            'name' => $name,
            'display_name' => ucfirst($name),
            'is_active' => true,
            'priority' => $this->faker->numberBetween(1, 10),
            'supported_currencies' => ['USD', 'EUR', 'SAR'],
            'supported_countries' => ['US', 'SA', 'AE', 'UK'],
            'configuration' => [
                'supports_webhooks' => true,
                'supports_refunds' => true,
                'processing_fee' => $this->faker->randomFloat(2, 2.5, 5.0)
            ],
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => now()
        ];
    }

    /**
     * Create gateway using firstOrCreate to avoid duplicates.
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $definition = $this->definition();
        $attributes = array_merge($definition, $attributes);
        
        // Use firstOrCreate to avoid duplicate gateway names
        return PaymentGateway::firstOrCreate(
            ['name' => $attributes['name']],
            $attributes
        );
    }
}