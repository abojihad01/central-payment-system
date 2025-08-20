<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'subscription_id',
        'gateway_subscription_id',
        'payment_id',
        'website_id',
        'user_id',
        'plan_id',
        'customer_email',
        'customer_phone',
        'status',
        'starts_at',
        'expires_at',
        'plan_data',
        'billing_cycle_count',
        'next_billing_date',
        'last_billing_date',
        'is_trial',
        'trial_ends_at',
        'cancelled_at',
        'cancellation_reason',
        'cancellation_type',
        'cancellation_notes',
        'cancel_at_period_end',
        'will_cancel_at_period_end',
        'grace_period_ends_at',
        'grace_period_days',
        'failed_payment_count',
        'paused_at',
        'pause_reason',
        'resumed_at',
        'plan_changes_history',
        'scheduled_plan_change',
        'plan_change_type',
        'reactivated_at',
        'expired_at',
        'transferred_at',
        'previous_customer_email',
        'transfer_reason'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'next_billing_date' => 'datetime',
        'last_billing_date' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'reactivated_at' => 'datetime',
        'expired_at' => 'datetime',
        'transferred_at' => 'datetime',
        'plan_data' => 'array',
        'plan_changes_history' => 'array',
        'is_trial' => 'boolean',
        'cancel_at_period_end' => 'boolean',
        'will_cancel_at_period_end' => 'boolean'
    ];

    protected static function booted()
    {
        static::creating(function ($subscription) {
            $subscription->subscription_id = (string) Str::uuid();
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription events.
     */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial']) && 
               (!$this->expires_at || $this->expires_at->isFuture()) &&
               !$this->paused_at;
    }

    /**
     * Check if subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || $this->cancelled_at;
    }

    /**
     * Check if subscription is in trial period.
     */
    public function isOnTrial(): bool
    {
        return $this->is_trial && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused' || $this->paused_at;
    }

    /**
     * Check if subscription is recurring.
     */
    public function isRecurring(): bool
    {
        return $this->plan && $this->plan->isRecurring();
    }

    /**
     * Check if subscription has upcoming billing.
     */
    public function hasUpcomingBilling(): bool
    {
        return $this->next_billing_date && $this->next_billing_date->isFuture();
    }

    /**
     * Get days until next billing.
     */
    public function daysUntilNextBilling(): ?int
    {
        if (!$this->next_billing_date) {
            return null;
        }

        return Carbon::now()->diffInDays($this->next_billing_date, false);
    }

    /**
     * Get days until trial ends.
     */
    public function daysUntilTrialEnds(): ?int
    {
        if (!$this->trial_ends_at) {
            return null;
        }

        return Carbon::now()->diffInDays($this->trial_ends_at, false);
    }

    /**
     * Check if subscription is in grace period.
     */
    public function isInGracePeriod(): bool
    {
        return $this->grace_period_ends_at && $this->grace_period_ends_at->isFuture();
    }






    /**
     * Record a failed payment.
     */
    public function recordFailedPayment(): void
    {
        $this->increment('failed_payment_count');
        
        // Set grace period if this is the first failure
        if ($this->failed_payment_count === 1) {
            $this->update([
                'status' => 'past_due',
                'grace_period_ends_at' => Carbon::now()->addDays(3) // 3-day grace period
            ]);

            SubscriptionEvent::createEvent(
                $this->id,
                'grace_period_started',
                'Grace period started due to payment failure'
            );
        }

        SubscriptionEvent::createEvent(
            $this->id,
            'payment_failed',
            "Payment failed (attempt #{$this->failed_payment_count})"
        );
    }

    /**
     * Record a successful payment.
     */
    public function recordSuccessfulPayment(): void
    {
        $wasInGracePeriod = $this->isInGracePeriod();
        
        $this->update([
            'status' => 'active',
            'failed_payment_count' => 0,
            'grace_period_ends_at' => null
        ]);

        if ($wasInGracePeriod) {
            SubscriptionEvent::createEvent(
                $this->id,
                'grace_period_ended',
                'Grace period ended - payment successful'
            );
        }

        SubscriptionEvent::createEvent(
            $this->id,
            'payment_succeeded',
            'Payment processed successfully'
        );
    }

    /**
     * Create subscription from successful payment.
     */
    public static function createFromPayment(Payment $payment)
    {
        $generatedLink = $payment->generatedLink;
        $plan = $generatedLink->plan;
        $website = $generatedLink->website;
        
        $subscription = static::create([
            'payment_id' => $payment->id,
            'plan_id' => $plan->id,
            'website_id' => $website->id,
            'customer_email' => $payment->customer_email,
            'customer_phone' => $payment->customer_phone,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays($plan->duration_days),
            'next_billing_date' => now()->addDays($plan->duration_days),
            'billing_cycle_count' => 1,
            'plan_data' => [
                'name' => $plan->name,
                'price' => $plan->price,
                'duration_days' => $plan->duration_days,
                'currency' => $plan->currency
            ]
        ]);

        $payment->update(['subscription_id' => $subscription->id]);

        return $subscription;
    }

    /**
     * Process subscription renewal.
     */
    public function processRenewal(Payment $renewalPayment)
    {
        $currentExpiry = $this->expires_at;
        $newExpiry = $currentExpiry->addDays($this->plan->duration_days);
        $newNextBilling = $newExpiry->copy()->addDays($this->plan->duration_days);
        
        $this->update([
            'expires_at' => $newExpiry,
            'next_billing_date' => $newNextBilling,
            'billing_cycle_count' => $this->billing_cycle_count + 1,
            'last_billing_date' => now()
        ]);

        $renewalPayment->update([
            'subscription_id' => $this->id,
            'is_renewal' => true
        ]);

        \Notification::send($this, new \App\Notifications\SubscriptionRenewed($this, $renewalPayment));
    }

    /**
     * Cancel subscription with different options.
     */
    public function cancel($reasonOrData = null, bool $atPeriodEnd = false)
    {
        // Support both old and new signatures
        if (is_array($reasonOrData)) {
            $data = $reasonOrData;
            $reason = $data['reason'] ?? null;
            $cancellationType = $data['cancellation_type'] ?? 'immediate';
            $notes = $data['notes'] ?? null;
        } else {
            // Legacy signature: cancel(string $reason, bool $atPeriodEnd)
            $reason = $reasonOrData;
            $cancellationType = $atPeriodEnd ? 'at_period_end' : 'immediate';
            $notes = null;
        }
        
        if ($cancellationType === 'immediate') {
            $this->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'expires_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_type' => 'immediate',
                'cancellation_notes' => $notes ?: ($reason ? "Customer requested: {$reason}" : 'Customer requested cancellation')
            ]);
        } else {
            $this->update([
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_type' => 'at_period_end',
                'cancellation_notes' => $notes,
                'will_cancel_at_period_end' => true
            ]);
        }

        // Log the cancellation event
        SubscriptionEvent::createEvent(
            $this->id,
            'cancelled',
            $reason ? "Subscription cancelled: {$reason}" : 'Subscription cancelled',
            [
                'reason' => $reason,
                'cancellation_type' => $cancellationType,
                'cancelled_by' => 'system'
            ]
        );

        \Notification::send($this, new \App\Notifications\SubscriptionCancelled($this));

        return true;
    }

    /**
     * Pause subscription.
     */
    public function pause($reasonOrData = null)
    {
        if ($this->status === 'paused') {
            return false;
        }

        // Support both old and new signatures
        if (is_array($reasonOrData)) {
            $reason = $reasonOrData['reason'] ?? null;
        } else {
            // Legacy signature: pause(string $reason)
            $reason = $reasonOrData;
        }

        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
            'pause_reason' => $reason
        ]);

        return true;
    }

    /**
     * Resume paused subscription.
     */
    public function resume()
    {
        if ($this->status !== 'paused') {
            return false;
        }

        // Calculate days paused (ensure we get a positive integer)
        $pausedDays = max(0, now()->diffInDays($this->paused_at));
        
        // Extend expiration date by the number of days paused
        $newExpirationDate = $this->expires_at->copy()->addDays($pausedDays);
        
        $this->update([
            'status' => 'active',
            'resumed_at' => now(),
            'expires_at' => $newExpirationDate
        ]);

        return true;
    }

    /**
     * Upgrade subscription plan.
     */
    public function upgradePlan($newPlanOrId, bool $prorate = true)
    {
        // Support both Plan object and ID
        if ($newPlanOrId instanceof Plan) {
            $newPlan = $newPlanOrId;
        } else {
            $newPlan = Plan::findOrFail($newPlanOrId);
        }

        $oldPlan = $this->plan;
        $proratedAmount = 0;

        if ($prorate) {
            $remainingDays = now()->diffInDays($this->expires_at);
            $dailyRate = $newPlan->price / $newPlan->duration_days;
            $proratedAmount = round($dailyRate * $remainingDays, 2);

            Payment::create([
                'subscription_id' => $this->id,
                'generated_link_id' => $this->payment->generated_link_id ?? 1, // Use original payment's link or default
                'amount' => $proratedAmount,
                'currency' => $this->plan->currency ?? 'USD',
                'type' => 'upgrade',
                'status' => 'completed',
                'customer_email' => $this->customer_email,
                'payment_gateway' => 'system',
                'confirmed_at' => now()
            ]);
        }

        $this->update(['plan_id' => $newPlan->id]);

        // Log the upgrade event
        SubscriptionEvent::createEvent(
            $this->id,
            'plan_upgraded',
            "Plan upgraded from '{$oldPlan->name}' to '{$newPlan->name}'",
            [
                'old_plan_id' => $oldPlan->id,
                'new_plan_id' => $newPlan->id,
                'prorated_amount' => $proratedAmount
            ]
        );

        return [
            'success' => true,
            'prorated_amount' => $proratedAmount,
            'old_plan' => $oldPlan->name,
            'new_plan' => $newPlan->name
        ];
    }

    /**
     * Downgrade subscription plan.
     */
    public function downgradePlan(Plan $newPlan, bool $immediate = false)
    {
        if ($immediate) {
            $this->update(['plan_id' => $newPlan->id]);
        } else {
            $this->update([
                'scheduled_plan_change' => $newPlan->id,
                'plan_change_type' => 'downgrade'
            ]);
        }

        return [
            'success' => true,
            'immediate' => $immediate
        ];
    }

    /**
     * Reactivate expired subscription.
     */
    public function reactivate(Payment $payment)
    {
        $this->update([
            'status' => 'active',
            'reactivated_at' => now(),
            'expired_at' => null,
            'expires_at' => now()->addDays($this->plan->duration_days)
        ]);

        $payment->update(['subscription_id' => $this->id]);

        return true;
    }

    /**
     * Transfer subscription to another customer.
     */
    public function transferToCustomer(string $newEmail, array $data = [])
    {
        $oldEmail = $this->customer_email;
        
        $this->update([
            'customer_email' => $newEmail,
            'transferred_at' => now(),
            'previous_customer_email' => $oldEmail,
            'transfer_reason' => $data['reason'] ?? null
        ]);

        return true;
    }

    /**
     * Check if trial period is expired.
     */
    public function isTrialExpired(): bool
    {
        return $this->is_trial && $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Add billing cycle to subscription.
     */
    public function addBillingCycle()
    {
        $this->increment('billing_cycle_count');
        
        // Calculate next billing date from current next_billing_date if it exists
        $currentNextBilling = $this->next_billing_date ? $this->next_billing_date->copy() : now();
        
        // Use plan's billing interval instead of duration_days for recurring subscriptions
        if ($this->plan && $this->plan->isRecurring()) {
            $newNextBilling = $this->plan->calculateNextBillingDate($currentNextBilling);
        } else {
            // Fallback to monthly for testing
            $newNextBilling = $currentNextBilling->addMonth();
        }
        
        $this->update([
            'last_billing_date' => $currentNextBilling,
            'next_billing_date' => $newNextBilling,
            'expires_at' => $newNextBilling
        ]);

        return $this;
    }

    /**
     * Send subscription activation notification.
     */
    public function sendActivationNotification()
    {
        $this->notify(new \App\Notifications\SubscriptionActivated($this));
        
        // Log the notification
        \App\Models\NotificationLog::create([
            'type' => 'subscription_activated',
            'recipient_email' => $this->customer_email,
            'recipient_type' => 'subscription',
            'recipient_id' => $this->id,
            'channel' => 'mail',
            'status' => 'sent',
            'data' => ['subscription_id' => $this->id],
            'sent_at' => now()
        ]);
        
        // Also notify admins if configured
        if ($adminUsers = \App\Models\User::where('role', 'admin')->get()) {
            \Notification::send($adminUsers, new \App\Notifications\SubscriptionActivated($this));
        }
    }

    /**
     * Send subscription expiring notification.
     */
    public function sendExpiringNotification()
    {
        $this->notify(new \App\Notifications\SubscriptionExpiring($this));
        
        // Log the notification
        \App\Models\NotificationLog::create([
            'type' => 'subscription_expiring',
            'recipient_email' => $this->customer_email,
            'recipient_type' => 'subscription',
            'recipient_id' => $this->id,
            'channel' => 'mail',
            'status' => 'sent',
            'data' => ['subscription_id' => $this->id],
            'sent_at' => now()
        ]);
    }

    public function routeNotificationFor($driver)
    {
        if ($driver === 'mail') {
            return $this->customer_email;
        }
        
        if ($driver === 'database') {
            return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable');
        }

        return null;
    }
}
