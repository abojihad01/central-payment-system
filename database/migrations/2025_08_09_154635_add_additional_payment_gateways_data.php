<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\PaymentGateway;
use App\Models\PaymentAccount;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create Klarna gateway (or get existing)
        $klarnaGateway = PaymentGateway::firstOrCreate(
            ['name' => 'klarna'],
            [
                'display_name' => 'Klarna',
                'description' => 'Buy now, pay later with Klarna',
                'is_active' => true,
                'priority' => 85, // Lower than Stripe/PayPal but still high
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'SEK', 'NOK', 'DKK'],
                'supported_countries' => ['US', 'GB', 'DE', 'AT', 'NL', 'BE', 'FI', 'SE', 'NO', 'DK'],
                'configuration' => [
                    'supports_recurring' => false, // Klarna typically doesn't support recurring
                    'min_amount' => 1.00,
                    'max_amount' => 10000.00,
                    'requires_customer_info' => true,
                    'supports_refunds' => true
                ]
            ]
        );

        // Create Apple Pay gateway (or get existing)
        $applePayGateway = PaymentGateway::firstOrCreate(
            ['name' => 'apple_pay'],
            [
                'display_name' => 'Apple Pay',
                'description' => 'Pay with Touch ID, Face ID, or passcode',
                'is_active' => true,
                'priority' => 95, // High priority for mobile users
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK'],
                'supported_countries' => ['US', 'CA', 'GB', 'AU', 'FR', 'DE', 'IT', 'ES', 'NL', 'SE', 'NO', 'DK', 'FI'],
                'configuration' => [
                    'supports_recurring' => true,
                    'min_amount' => 0.50,
                    'max_amount' => 100000.00,
                    'requires_customer_info' => false,
                    'supports_refunds' => true,
                    'device_requirements' => ['iOS', 'macOS', 'Safari on macOS']
                ]
            ]
        );

        // Create Google Pay gateway (or get existing)
        $googlePayGateway = PaymentGateway::firstOrCreate(
            ['name' => 'google_pay'],
            [
                'display_name' => 'Google Pay',
                'description' => 'Fast, simple checkout with Google Pay',
                'is_active' => true,
                'priority' => 95, // High priority for mobile users
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'BRL', 'JPY', 'INR', 'SEK', 'NOK', 'DKK'],
                'supported_countries' => ['US', 'CA', 'GB', 'AU', 'FR', 'DE', 'IT', 'ES', 'NL', 'SE', 'NO', 'DK', 'FI', 'BR', 'IN', 'JP'],
                'configuration' => [
                    'supports_recurring' => true,
                    'min_amount' => 0.50,
                    'max_amount' => 100000.00,
                    'requires_customer_info' => false,
                    'supports_refunds' => true,
                    'device_requirements' => ['Android', 'Chrome']
                ]
            ]
        );

        // Create sample accounts for each gateway (these would be configured by admin)
        
        // Klarna test account (or get existing)
        PaymentAccount::firstOrCreate(
            ['account_id' => 'klarna_test_001'],
            [
                'payment_gateway_id' => $klarnaGateway->id,
                'name' => 'Klarna Test Account',
                'description' => 'Klarna test environment account',
                'is_active' => true,
                'is_sandbox' => true,
                'credentials' => [
                    'username' => 'K123456_abcd12345',
                    'password' => 'sharedSecret123',
                    'endpoint' => 'https://api.playground.klarna.com',
                    'webhook_secret' => 'test_webhook_secret'
                ],
                'successful_transactions' => 0,
                'failed_transactions' => 0
            ]
        );

        // Apple Pay test account (processed through Stripe) - or get existing
        PaymentAccount::firstOrCreate(
            ['account_id' => 'apple_pay_stripe_001'],
            [
                'payment_gateway_id' => $applePayGateway->id,
                'name' => 'Apple Pay via Stripe',
                'description' => 'Apple Pay payments processed through Stripe',
                'is_active' => true,
                'is_sandbox' => true,
                'credentials' => [
                    'stripe_publishable_key' => 'pk_test_51234567890abcdef',
                    'stripe_secret_key' => 'sk_test_51234567890abcdef',
                    'merchant_id' => 'merchant.com.yourapp.payments',
                    'merchant_display_name' => 'Your App',
                    'apple_pay_domain_verification' => true
                ],
                'successful_transactions' => 0,
                'failed_transactions' => 0
            ]
        );

        // Google Pay test account (processed through Stripe) - or get existing
        PaymentAccount::firstOrCreate(
            ['account_id' => 'google_pay_stripe_001'],
            [
                'payment_gateway_id' => $googlePayGateway->id,
                'name' => 'Google Pay via Stripe',
                'description' => 'Google Pay payments processed through Stripe',
                'is_active' => true,
                'is_sandbox' => true,
                'credentials' => [
                    'stripe_publishable_key' => 'pk_test_51234567890abcdef',
                    'stripe_secret_key' => 'sk_test_51234567890abcdef',
                    'google_pay_merchant_id' => '12345678901234567890',
                    'google_pay_merchant_name' => 'Your App'
                ],
                'successful_transactions' => 0,
                'failed_transactions' => 0
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the gateways and their accounts
        $gatewayNames = ['klarna', 'apple_pay', 'google_pay'];
        
        foreach ($gatewayNames as $gatewayName) {
            $gateway = PaymentGateway::where('name', $gatewayName)->first();
            if ($gateway) {
                // Delete associated accounts first
                $gateway->paymentAccounts()->delete();
                // Delete the gateway
                $gateway->delete();
            }
        }
    }
};