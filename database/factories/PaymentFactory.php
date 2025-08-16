<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\GeneratedLink;
use App\Models\PaymentAccount;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition()
    {
        $statuses = ['pending', 'completed', 'failed', 'cancelled'];
        $gateways = ['stripe', 'paypal'];
        $amounts = [29.99, 49.99, 99.99, 149.99, 199.99];
        
        $status = $this->faker->randomElement($statuses);
        $gateway = $this->faker->randomElement($gateways);
        
        return [
            'generated_link_id' => GeneratedLink::factory(),
            'payment_account_id' => PaymentAccount::factory(),
            'subscription_id' => null,
            'payment_gateway' => $gateway,
            'gateway_payment_id' => $gateway === 'stripe' 
                ? 'pi_test_' . $this->faker->uuid 
                : 'PAY-' . $this->faker->uuid,
            'gateway_session_id' => $gateway === 'stripe' 
                ? 'cs_test_' . $this->faker->uuid 
                : 'EC-' . $this->faker->uuid,
            'amount' => $this->faker->randomElement($amounts),
            'currency' => 'USD',
            'status' => $status,
            'customer_email' => $this->faker->email,
            'customer_name' => $this->faker->name,
            'customer_phone' => $this->faker->optional()->phoneNumber,
            'type' => 'payment',
            'is_renewal' => false,
            'retry_count' => $this->faker->numberBetween(0, 3),
            'retry_log' => $this->faker->optional()->randomElement([
                null,
                [['attempt' => 1, 'status' => 'failed', 'timestamp' => now()->subMinutes(5)]]
            ]),
            'gateway_response' => [
                'transaction_id' => $this->faker->md5,
                'gateway_fee' => $this->faker->randomFloat(2, 1, 10),
                'gateway_status' => $status
            ],
            'paid_at' => $status === 'completed' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'confirmed_at' => $status === 'completed' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'notes' => $this->faker->optional()->sentence,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    public function completed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'confirmed_at' => $this->faker->dateTimeBetween('-30 days', 'now')
            ];
        });
    }

    public function failed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'failed',
                'gateway_response' => [
                    'transaction_id' => $this->faker->md5,
                    'gateway_fee' => 0,
                    'gateway_status' => 'failed',
                    'failure_reason' => $this->faker->randomElement([
                        'insufficient_funds',
                        'card_declined',
                        'expired_card',
                        'network_error'
                    ])
                ]
            ];
        });
    }

    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'confirmed_at' => null
            ];
        });
    }
}