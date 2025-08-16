<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSelectionConfig extends Model
{
    protected $fillable = [
        'name',
        'selection_strategy',
        'strategy_config',
        'enable_fallback',
        'max_fallback_attempts',
        'account_weights',
        'account_priorities',
        'exclude_failed_accounts',
        'failed_account_cooldown_minutes',
        'enable_load_balancing',
        'max_account_load_percentage',
        'is_active',
        'description',
    ];

    protected $casts = [
        'strategy_config' => 'array',
        'account_weights' => 'array',
        'account_priorities' => 'array',
        'enable_fallback' => 'boolean',
        'exclude_failed_accounts' => 'boolean',
        'enable_load_balancing' => 'boolean',
        'is_active' => 'boolean',
        'max_account_load_percentage' => 'decimal:2',
    ];

    // Get configuration for specific gateway
    public static function getConfig(string $gateway = 'global'): ?self
    {
        return self::where('name', $gateway)
                   ->where('is_active', true)
                   ->first() ?? self::where('name', 'global')
                                   ->where('is_active', true)
                                   ->first();
    }

    // Selection strategies
    const STRATEGY_LEAST_USED = 'least_used';
    const STRATEGY_ROUND_ROBIN = 'round_robin';
    const STRATEGY_WEIGHTED = 'weighted';
    const STRATEGY_MANUAL = 'manual';
    const STRATEGY_RANDOM = 'random';

    public static function getAvailableStrategies(): array
    {
        return [
            self::STRATEGY_LEAST_USED => 'Least Used (Load Balancing)',
            self::STRATEGY_ROUND_ROBIN => 'Round Robin',
            self::STRATEGY_WEIGHTED => 'Weighted Distribution',
            self::STRATEGY_MANUAL => 'Manual Priority Order',
            self::STRATEGY_RANDOM => 'Random Selection',
        ];
    }
}