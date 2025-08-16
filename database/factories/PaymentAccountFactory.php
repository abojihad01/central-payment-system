<?php

namespace Database\Factories;

use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentAccountFactory extends Factory
{
    protected $model = PaymentAccount::class;

    public function definition()
    {
        return [
            'payment_gateway_id' => PaymentGateway::factory(),
            'account_id' => 'acc_' . $this->faker->uuid,
            'name' => $this->faker->company . ' Account',
            'description' => $this->faker->optional()->sentence,
            'is_active' => true,
            'is_sandbox' => $this->faker->boolean(30),
            'credentials' => [
                'api_key' => 'test_' . $this->faker->md5,
                'secret_key' => 'sk_test_' . $this->faker->md5,
                'webhook_secret' => 'whsec_' . $this->faker->md5
            ],
            'successful_transactions' => $this->faker->numberBetween(0, 1000),
            'failed_transactions' => $this->faker->numberBetween(0, 100),
            'total_amount' => $this->faker->randomFloat(2, 0, 50000),
            'last_used_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'settings' => [
                'auto_capture' => true,
                'currency' => 'USD',
                'statement_descriptor' => $this->faker->company
            ],
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => now()
        ];
    }
}