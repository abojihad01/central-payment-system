<?php

namespace Database\Factories;

use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    public function definition()
    {
        return [
            'type' => $this->faker->randomElement([
                'payment_completed',
                'payment_failed', 
                'subscription_activated',
                'subscription_expiring',
                'subscription_expired',
                'subscription_cancelled'
            ]),
            'recipient_email' => $this->faker->email,
            'recipient_phone' => $this->faker->optional()->phoneNumber,
            'channel' => $this->faker->randomElement(['mail', 'sms', 'push', 'database']),
            'status' => $this->faker->randomElement(['sent', 'failed', 'pending', 'throttled']),
            'retry_count' => $this->faker->numberBetween(0, 3),
            'sent_at' => $this->faker->optional()->dateTimeBetween('-7 days', 'now'),
            'failed_at' => $this->faker->optional()->dateTimeBetween('-7 days', 'now'),
            'error_message' => $this->faker->optional()->sentence,
            'data' => [
                'subject' => $this->faker->sentence,
                'message' => $this->faker->paragraph
            ],
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => now()
        ];
    }
}