<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'name',
        'description',
        'price',
        'currency',
        'duration_days',
        'features',
        'metadata',
        'is_active',
        'subscription_type',
        'billing_interval',
        'billing_interval_count',
        'trial_period_days',
        'setup_fee',
        'max_billing_cycles',
        'prorate_on_change'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'features' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'prorate_on_change' => 'boolean'
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function generatedLinks(): HasMany
    {
        return $this->hasMany(GeneratedLink::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Check if this plan is recurring.
     */
    public function isRecurring(): bool
    {
        return $this->subscription_type === 'recurring';
    }

    /**
     * Check if this plan has a trial period.
     */
    public function hasTrial(): bool
    {
        return $this->trial_period_days > 0;
    }

    /**
     * Check if this plan has a setup fee.
     */
    public function hasSetupFee(): bool
    {
        return $this->setup_fee > 0;
    }

    /**
     * Get the total first payment amount (price + setup fee).
     */
    public function getFirstPaymentAmount(): float
    {
        return $this->price + ($this->setup_fee ?? 0);
    }

    /**
     * Get the billing interval in human readable format.
     */
    public function getBillingIntervalText(): string
    {
        if (!$this->isRecurring()) {
            return 'One-time payment';
        }

        $interval = $this->billing_interval;
        $count = $this->billing_interval_count;

        if ($count === 1) {
            return match($interval) {
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'yearly' => 'Yearly',
                default => ucfirst($interval)
            };
        }
        
        $intervalSingle = match($interval) {
            'daily' => 'day',
            'weekly' => 'week', 
            'monthly' => 'month',
            'quarterly' => 'quarter',
            'yearly' => 'year',
            default => $interval
        };

        return "Every {$count} " . str($intervalSingle)->plural($count);
    }

    /**
     * Calculate the next billing date from a given start date.
     */
    public function calculateNextBillingDate(\Carbon\Carbon $startDate): ?\Carbon\Carbon
    {
        if (!$this->isRecurring()) {
            return null;
        }

        $nextDate = clone $startDate;

        switch ($this->billing_interval) {
            case 'daily':
                return $nextDate->addDays($this->billing_interval_count);
            case 'weekly':
                return $nextDate->addWeeks($this->billing_interval_count);
            case 'monthly':
                return $nextDate->addMonths($this->billing_interval_count);
            case 'quarterly':
                return $nextDate->addMonths($this->billing_interval_count * 3);
            case 'yearly':
                return $nextDate->addYears($this->billing_interval_count);
        }

        return null;
    }

    /**
     * Calculate subscription duration in days based on billing interval
     */
    public function getSubscriptionDurationDays(): int
    {
        $count = $this->billing_interval_count ?? 1;
        
        switch ($this->billing_interval) {
            case 'daily':
                return $count;
            case 'weekly':
                return $count * 7;
            case 'monthly':
                return $count * 30; // Approximate for calculation
            case 'quarterly':
                return $count * 90; // 3 months * 30 days
            case 'yearly':
                return $count * 365; // 1 year * 365 days
            default:
                return 30; // Default fallback
        }
    }

    /**
     * Calculate expiry date from start date based on billing interval
     */
    public function calculateExpiryDate(\Carbon\Carbon $startDate): \Carbon\Carbon
    {
        $expiry = clone $startDate;
        $count = $this->billing_interval_count ?? 1;
        
        switch ($this->billing_interval) {
            case 'daily':
                return $expiry->addDays($count);
            case 'weekly':
                return $expiry->addWeeks($count);
            case 'monthly':
                return $expiry->addMonths($count);
            case 'quarterly':
                return $expiry->addMonths($count * 3);
            case 'yearly':
                return $expiry->addYears($count);
            default:
                return $expiry->addDays(30); // Default fallback
        }
    }

    /**
     * Calculate expiry date with promotional bonus (free month)
     */
    public function calculateExpiryDateWithBonus(\Carbon\Carbon $startDate, bool $hasPromotion = false): \Carbon\Carbon
    {
        $baseExpiry = $this->calculateExpiryDate($startDate);
        
        if ($hasPromotion) {
            // Add one month bonus for promoted payment methods
            return $baseExpiry->addMonth();
        }
        
        return $baseExpiry;
    }

    /**
     * Get formatted price with currency.
     */
    public function getFormattedPrice(): string
    {
        return number_format($this->price, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Get the plan description with billing info.
     */
    public function getFullDescription(): string
    {
        $description = $this->description;
        
        if ($this->isRecurring()) {
            $description .= " - Billed {$this->getBillingIntervalText()}";
        }

        if ($this->hasTrial()) {
            $description .= " - {$this->trial_period_days} day" . ($this->trial_period_days > 1 ? 's' : '') . " free trial";
        }

        return $description;
    }
}
