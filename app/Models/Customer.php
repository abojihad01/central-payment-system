<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'email',
        'phone',
        'first_name',
        'last_name',
        'country_code',
        'city',
        'address',
        'postal_code',
        'status',
        'risk_score',
        'risk_level',
        'total_subscriptions',
        'active_subscriptions',
        'lifetime_value',
        'total_spent',
        'successful_payments',
        'failed_payments',
        'chargebacks',
        'refunds',
        'preferences',
        'payment_methods',
        'subscription_history',
        'tags',
        'first_purchase_at',
        'last_purchase_at',
        'last_login_at',
        'acquisition_source',
        'notes',
        'marketing_consent',
        'email_verified',
        'email_verified_at'
    ];

    protected $casts = [
        'preferences' => 'array',
        'payment_methods' => 'array',
        'subscription_history' => 'array',
        'tags' => 'array',
        'first_purchase_at' => 'datetime',
        'last_purchase_at' => 'datetime',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'marketing_consent' => 'boolean',
        'email_verified' => 'boolean',
        'lifetime_value' => 'decimal:2',
        'total_spent' => 'decimal:2'
    ];

    /**
     * Customer events relationship
     */
    public function events(): HasMany
    {
        return $this->hasMany(CustomerEvent::class);
    }

    /**
     * Customer communications relationship
     */
    public function communications(): HasMany
    {
        return $this->hasMany(CustomerCommunication::class);
    }

    /**
     * Customer subscriptions relationship
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'customer_email', 'email');
    }

    /**
     * Customer payments relationship
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_email', 'email');
    }

    /**
     * Check if customer is high value
     */
    public function isHighValue(): bool
    {
        return $this->lifetime_value >= 1000;
    }

    /**
     * Check if customer is at risk
     */
    public function isAtRisk(): bool
    {
        return $this->risk_score >= 60;
    }

    /**
     * Check if customer is blocked
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name) ?: 'N/A';
    }

    /**
     * Scope for active customers
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for high value customers
     */
    public function scopeHighValue($query)
    {
        return $query->where('lifetime_value', '>', 1000);
    }

    /**
     * Scope for at risk customers
     */
    public function scopeAtRisk($query)
    {
        return $query->where('risk_score', '>', 50);
    }

    /**
     * Scope for recent customers
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}