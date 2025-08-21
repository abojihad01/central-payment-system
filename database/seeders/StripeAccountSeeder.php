<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Models\PaymentAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class StripeAccountSeeder extends Seeder
{
    public function run(): void
    {
        // التأكد من وجود بوابة Stripe
        $stripeGateway = PaymentGateway::firstOrCreate(
            ['name' => 'stripe'],
            [
                'display_name' => 'Stripe',
                'description' => 'Stripe Payment Gateway - Credit Cards & Digital Wallets',
                'is_active' => true,
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'AED', 'SAR'],
                'supported_countries' => ['US', 'GB', 'AE', 'SA', 'EU']
            ]
        );

        // إضافة حساب Stripe Sandbox
        $stripeAccount = PaymentAccount::updateOrCreate(
            [
                'account_id' => 'stripe_sandbox_001'
            ],
            [
                'payment_gateway_id' => $stripeGateway->id,
                'name' => 'Stripe Sandbox Account',
                'description' => 'Test account for Stripe payments in sandbox mode',
                'credentials' => [
                    'publishable_key' => env('STRIPE_TEST_PUBLISHABLE_KEY', ''),
                    'secret_key' => Crypt::encryptString(env('STRIPE_TEST_SECRET_KEY', ''))
                ],
                'is_active' => true,
                'is_sandbox' => true,
                'settings' => [
                    'payment_methods' => ['card', 'apple_pay', 'google_pay'],
                    'capture_method' => 'automatic',
                    'statement_descriptor' => 'IPTV Service'
                ]
            ]
        );

        $this->command->info('✅ Stripe Sandbox account added successfully!');
        $this->command->info('Account ID: ' . $stripeAccount->account_id);
        $this->command->info('Gateway: ' . $stripeGateway->display_name);
        $this->command->info('Mode: Sandbox (Test)');
    }
}
