<?php

namespace Database\Seeders;

use App\Models\PaymentSelectionConfig;
use Illuminate\Database\Seeder;

class PaymentSelectionConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'name' => 'global',
                'selection_strategy' => PaymentSelectionConfig::STRATEGY_LEAST_USED,
                'strategy_config' => [
                    'prefer_unused_accounts' => true,
                    'load_balance_threshold' => 0.7,
                ],
                'enable_fallback' => true,
                'max_fallback_attempts' => 3,
                'account_weights' => [],
                'account_priorities' => [],
                'exclude_failed_accounts' => true,
                'failed_account_cooldown_minutes' => 60,
                'enable_load_balancing' => true,
                'max_account_load_percentage' => 70.00,
                'is_active' => true,
                'description' => 'Global default configuration for all payment gateways. Uses least-used strategy with load balancing.',
            ],
            
            [
                'name' => 'stripe',
                'selection_strategy' => PaymentSelectionConfig::STRATEGY_LEAST_USED,
                'strategy_config' => [
                    'prefer_unused_accounts' => true,
                    'consider_success_rate' => true,
                    'min_success_rate' => 90,
                ],
                'enable_fallback' => true,
                'max_fallback_attempts' => 2,
                'account_weights' => [],
                'account_priorities' => [],
                'exclude_failed_accounts' => true,
                'failed_account_cooldown_minutes' => 30,
                'enable_load_balancing' => true,
                'max_account_load_percentage' => 60.00,
                'is_active' => true,
                'description' => 'Stripe-specific configuration optimized for high success rates and quick failover.',
            ],
            
            [
                'name' => 'paypal',
                'selection_strategy' => PaymentSelectionConfig::STRATEGY_ROUND_ROBIN,
                'strategy_config' => [
                    'reset_cycle_hours' => 24,
                    'consider_sandbox_separately' => true,
                ],
                'enable_fallback' => true,
                'max_fallback_attempts' => 3,
                'account_weights' => [],
                'account_priorities' => [],
                'exclude_failed_accounts' => false,
                'failed_account_cooldown_minutes' => 90,
                'enable_load_balancing' => true,
                'max_account_load_percentage' => 80.00,
                'is_active' => true,
                'description' => 'PayPal-specific configuration using round-robin for even distribution.',
            ],
        ];

        foreach ($configs as $config) {
            PaymentSelectionConfig::updateOrCreate(
                ['name' => $config['name']],
                $config
            );
        }
    }
}