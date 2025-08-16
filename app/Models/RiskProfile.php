<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'ip_address',
        'country_code',
        'risk_score',
        'risk_level',
        'successful_payments',
        'failed_payments',
        'chargebacks',
        'total_amount',
        'payment_patterns',
        'device_fingerprints',
        'velocity_checks',
        'is_blocked',
        'blocked_until',
        'blocked_reason',
        'last_activity_at'
    ];

    protected $casts = [
        'payment_patterns' => 'array',
        'device_fingerprints' => 'array',
        'velocity_checks' => 'array',
        'is_blocked' => 'boolean',
        'blocked_until' => 'datetime',
        'last_activity_at' => 'datetime',
        'total_amount' => 'decimal:2'
    ];
}
