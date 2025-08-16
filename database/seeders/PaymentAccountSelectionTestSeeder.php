<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountSelection;
use App\Models\PaymentGateway;
use App\Models\GeneratedLink;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentAccountSelectionTestSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create test gateways and accounts
        $stripeGateway = PaymentGateway::firstOrCreate([
            'name' => 'stripe'
        ], [
            'display_name' => 'Stripe',
            'is_active' => true,
            'priority' => 1,
            'supported_currencies' => ['USD', 'EUR', 'GBP'],
            'supported_countries' => ['US', 'GB', 'CA'],
        ]);

        $paypalGateway = PaymentGateway::firstOrCreate([
            'name' => 'paypal'
        ], [
            'display_name' => 'PayPal',
            'is_active' => true,
            'priority' => 2,
            'supported_currencies' => ['USD', 'EUR', 'GBP'],
            'supported_countries' => ['US', 'GB', 'CA'],
        ]);

        // Get or create test payment accounts
        $stripeAccount1 = PaymentAccount::firstOrCreate([
            'payment_gateway_id' => $stripeGateway->id,
            'account_id' => 'test_stripe_1'
        ], [
            'name' => 'Stripe Account 1',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'publishable_key' => 'pk_test_' . str_repeat('x', 50),
                'secret_key' => 'sk_test_' . str_repeat('x', 50),
            ],
            'successful_transactions' => 45,
            'failed_transactions' => 5,
            'total_amount' => 12500.00,
            'last_used_at' => now()->subHours(2),
        ]);

        $stripeAccount2 = PaymentAccount::firstOrCreate([
            'payment_gateway_id' => $stripeGateway->id,
            'account_id' => 'test_stripe_2'
        ], [
            'name' => 'Stripe Account 2',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'publishable_key' => 'pk_test_' . str_repeat('y', 50),
                'secret_key' => 'sk_test_' . str_repeat('y', 50),
            ],
            'successful_transactions' => 23,
            'failed_transactions' => 2,
            'total_amount' => 8750.00,
            'last_used_at' => now()->subHours(4),
        ]);

        $paypalAccount1 = PaymentAccount::firstOrCreate([
            'payment_gateway_id' => $paypalGateway->id,
            'account_id' => 'test_paypal_1'
        ], [
            'name' => 'PayPal Account 1',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'client_id' => 'test_client_' . str_repeat('a', 40),
                'client_secret' => 'test_secret_' . str_repeat('b', 40),
            ],
            'successful_transactions' => 38,
            'failed_transactions' => 7,
            'total_amount' => 15800.00,
            'last_used_at' => now()->subHours(1),
        ]);

        $paypalAccount2 = PaymentAccount::firstOrCreate([
            'payment_gateway_id' => $paypalGateway->id,
            'account_id' => 'test_paypal_2'
        ], [
            'name' => 'PayPal Account 2',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => [
                'client_id' => 'test_client_' . str_repeat('c', 40),
                'client_secret' => 'test_secret_' . str_repeat('d', 40),
            ],
            'successful_transactions' => 12,
            'failed_transactions' => 1,
            'total_amount' => 4200.00,
            'last_used_at' => now()->subHours(6),
        ]);

        // Get or create a test generated link
        $testLink = GeneratedLink::first();
        if (!$testLink) {
            $testLink = GeneratedLink::create([
                'website_id' => 1,
                'plan_id' => 1,
                'token' => 'test_' . str_repeat('token', 10),
                'expires_at' => now()->addMonth(),
                'success_url' => 'https://example.com/success',
                'failure_url' => 'https://example.com/failure',
                'currency' => 'USD',
                'price' => 29.99,
                'max_uses' => 100,
                'used_count' => 0,
            ]);
        }

        // Generate test payments and account selections
        $accounts = [$stripeAccount1, $stripeAccount2, $paypalAccount1, $paypalAccount2];
        $strategies = ['least_used', 'round_robin', 'weighted', 'manual', 'random'];
        $statuses = ['completed', 'pending', 'failed', 'cancelled'];

        for ($i = 0; $i < 50; $i++) {
            $selectedAccount = $accounts[array_rand($accounts)];
            $strategy = $strategies[array_rand($strategies)];
            $status = $statuses[array_rand($statuses)];
            
            // Create payment
            $payment = Payment::create([
                'generated_link_id' => $testLink->id,
                'payment_account_id' => $selectedAccount->id,
                'payment_gateway' => $selectedAccount->gateway->name,
                'gateway_payment_id' => 'test_' . uniqid(),
                'gateway_session_id' => 'sess_' . uniqid(),
                'amount' => rand(1000, 50000) / 100, // $10-$500
                'currency' => 'USD',
                'status' => $status,
                'customer_email' => 'test' . $i . '@example.com',
                'customer_phone' => '+1555' . sprintf('%07d', $i),
                'gateway_response' => [
                    'test_data' => true,
                    'created_at' => now()->toISOString(),
                ],
                'created_at' => now()->subHours(rand(1, 72)), // Random time in last 3 days
            ]);

            // Create account selection record
            $availableAccounts = array_filter($accounts, function($acc) use ($selectedAccount) {
                return $acc->gateway->name === $selectedAccount->gateway->name;
            });

            $selectionReasons = [
                'least_used' => 'Account with lowest transaction count selected',
                'round_robin' => 'Next account in rotation sequence',
                'weighted' => 'Selected based on configured weight distribution',
                'manual' => 'Selected based on admin-defined priority',
                'random' => 'Randomly selected from available accounts',
            ];

            PaymentAccountSelection::create([
                'payment_id' => $payment->id,
                'payment_account_id' => $selectedAccount->id,
                'gateway_name' => $selectedAccount->gateway->name,
                'selection_method' => $strategy,
                'selection_criteria' => [
                    'currency' => 'USD',
                    'country' => 'US',
                    'total_accounts_available' => count($availableAccounts),
                    'production_only' => false,
                ],
                'available_accounts' => array_map(function($acc) {
                    return [
                        'account_id' => $acc->account_id,
                        'name' => $acc->name,
                        'successful_transactions' => $acc->successful_transactions,
                        'failed_transactions' => $acc->failed_transactions,
                        'total_amount' => $acc->total_amount,
                        'last_used_at' => $acc->last_used_at?->toISOString(),
                    ];
                }, $availableAccounts),
                'account_stats' => [
                    'selected_account' => [
                        'successful_transactions' => $selectedAccount->successful_transactions,
                        'failed_transactions' => $selectedAccount->failed_transactions,
                        'total_amount' => $selectedAccount->total_amount,
                        'success_rate' => $selectedAccount->successful_transactions > 0 
                            ? ($selectedAccount->successful_transactions / ($selectedAccount->successful_transactions + $selectedAccount->failed_transactions)) * 100 
                            : 100,
                    ]
                ],
                'selection_reason' => $selectionReasons[$strategy] ?? 'Account selected using ' . $strategy . ' strategy',
                'selection_priority' => rand(1, count($availableAccounts)),
                'was_fallback' => rand(0, 100) < 10, // 10% chance of being fallback
                'previous_account_id' => rand(0, 100) < 10 ? 'prev_' . uniqid() : null,
                'selection_time_ms' => rand(5, 150) / 10, // 0.5ms to 15ms
                'created_at' => $payment->created_at,
                'updated_at' => $payment->created_at,
            ]);
        }

        $this->command->info('Created 50 test payment account selections');
    }
}