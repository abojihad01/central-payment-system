<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Plan;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionService
{
    /**
     * Create a new subscription from data array.
     */
    public function createSubscription($paymentOrData, ...$args): Subscription {
        // Support both Payment object and array signatures
        if (is_array($paymentOrData)) {
            return $this->createSubscriptionFromArray($paymentOrData);
        } elseif ($paymentOrData instanceof Payment) {
            return $this->createSubscriptionFromPayment($paymentOrData, ...$args);
        } else {
            throw new \InvalidArgumentException('First argument must be Payment object or data array');
        }
    }

    /**
     * Create subscription from array data.
     */
    public function createSubscriptionFromArray(array $data): Subscription {
        $plan = Plan::findOrFail($data['plan_id']);
        $now = Carbon::now();
        
        // Determine subscription status and dates
        // Check both data array and plan for trial period
        $trialDays = $data['trial_period_days'] ?? $plan->trial_period_days ?? 0;
        $isTrialPeriod = $trialDays > 0;
        $status = $isTrialPeriod ? 'trial' : 'active';
        $startsAt = $now;
        $trialEndsAt = $isTrialPeriod ? $now->copy()->addDays($trialDays) : null;
        
        $expiresAt = $isTrialPeriod 
            ? $trialEndsAt 
            : $now->copy()->addDays($plan->duration_days);
        
        // Create subscription
        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'website_id' => $data['website_id'] ?? 1, // Default website
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'status' => $status,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'trial_ends_at' => $trialEndsAt,
            'is_trial' => $isTrialPeriod,
            'billing_cycle_count' => 1,
            'next_billing_date' => $expiresAt,
            'plan_data' => [
                'name' => $plan->name,
                'price' => $plan->price,
                'currency' => $plan->currency ?? 'USD',
                'duration_days' => $plan->duration_days,
            ]
        ]);

        // Log creation event
        if ($isTrialPeriod) {
            SubscriptionEvent::createEvent(
                $subscription->id,
                'trial_started',
                "Trial period started ({$trialDays} days)",
                ['trial_ends_at' => $trialEndsAt]
            );
        } else {
            SubscriptionEvent::createEvent(
                $subscription->id,
                'created',
                "Subscription created for plan: {$plan->name}",
                ['plan_id' => $plan->id]
            );
        }

        return $subscription;
    }

    /**
     * Create subscription from Payment object.
     */
    public function createSubscriptionFromPayment(
        Payment $payment,
        int $websiteId,
        int $planId,
        string $customerEmail,
        ?string $customerPhone = null,
        ?string $gatewaySubscriptionId = null
    ): Subscription {
        $plan = Plan::findOrFail($planId);
        $now = Carbon::now();
        
        // Determine subscription status and dates
        $isTrialPeriod = $plan->hasTrial();
        $status = $isTrialPeriod ? 'trial' : 'active';
        $startsAt = $now;
        $trialEndsAt = $isTrialPeriod ? $now->copy()->addDays($plan->trial_period_days) : null;
        
        // Calculate next billing date
        $nextBillingDate = null;
        if ($plan->isRecurring()) {
            $nextBillingDate = $isTrialPeriod 
                ? $trialEndsAt 
                : $plan->calculateNextBillingDate($now);
        }

        // Calculate expiration date
        $expiresAt = null;
        if (!$plan->isRecurring()) {
            // One-time payment expires after duration
            $expiresAt = $plan->duration_days ? $now->copy()->addDays($plan->duration_days) : null;
        } elseif ($plan->max_billing_cycles) {
            // Recurring with max cycles - calculate final expiration
            $cycleStart = $nextBillingDate ?? $now;
            for ($i = 0; $i < $plan->max_billing_cycles - 1; $i++) {
                $cycleStart = $plan->calculateNextBillingDate($cycleStart);
            }
            $expiresAt = $cycleStart;
        }

        // Create subscription
        $subscription = Subscription::create([
            'payment_id' => $payment->id,
            'website_id' => $websiteId,
            'plan_id' => $planId,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'gateway_subscription_id' => $gatewaySubscriptionId,
            'status' => $status,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'is_trial' => $isTrialPeriod,
            'trial_ends_at' => $trialEndsAt,
            'next_billing_date' => $nextBillingDate,
            'billing_cycle_count' => 1,
            'plan_data' => [
                'name' => $plan->name,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'features' => $plan->features,
                'billing_interval' => $plan->billing_interval,
                'billing_interval_count' => $plan->billing_interval_count,
            ]
        ]);

        // Log creation event
        SubscriptionEvent::createEvent(
            $subscription->id,
            'created',
            "Subscription created for plan: {$plan->name}",
            [
                'plan_id' => $plan->id,
                'payment_id' => $payment->id,
                'is_trial' => $isTrialPeriod,
                'trial_days' => $plan->trial_period_days,
                'first_payment_amount' => $plan->getFirstPaymentAmount()
            ],
            $payment->id,
            $plan->id
        );

        if ($isTrialPeriod) {
            SubscriptionEvent::createEvent(
                $subscription->id,
                'trial_started',
                "Trial period started ({$plan->trial_period_days} days)",
                ['trial_ends_at' => $trialEndsAt]
            );
        } else {
            SubscriptionEvent::createEvent(
                $subscription->id,
                'activated',
                'Subscription activated'
            );
        }

        return $subscription;
    }

    /**
     * Process trial end for subscriptions.
     */
    public function processTrialEnds(): int
    {
        $endingTrials = Subscription::where('is_trial', true)
            ->where('trial_ends_at', '<=', Carbon::now())
            ->where('status', 'trial')
            ->get();

        $processed = 0;

        foreach ($endingTrials as $subscription) {
            $this->endTrialPeriod($subscription);
            $processed++;
        }

        return $processed;
    }

    /**
     * End trial period for a subscription.
     */
    public function endTrialPeriod(Subscription $subscription): bool
    {
        if (!$subscription->isOnTrial()) {
            return false;
        }

        $subscription->update([
            'is_trial' => false,
            'status' => 'active'
        ]);

        SubscriptionEvent::createEvent(
            $subscription->id,
            'trial_ended',
            'Trial period ended - subscription now active'
        );

        return true;
    }

    /**
     * Process upcoming renewals (send notifications).
     */
    public function processUpcomingRenewals(int $daysAhead = 3): Collection
    {
        $upcomingRenewals = Subscription::where('status', 'active')
            ->where('next_billing_date', '>=', Carbon::now())
            ->where('next_billing_date', '<=', Carbon::now()->addDays($daysAhead))
            ->with(['plan', 'website'])
            ->get();

        foreach ($upcomingRenewals as $subscription) {
            // Here you would send renewal notifications
            // This is a placeholder for notification logic
            \Log::info("Upcoming renewal for subscription {$subscription->subscription_id}", [
                'customer_email' => $subscription->customer_email,
                'next_billing_date' => $subscription->next_billing_date,
                'amount' => $subscription->plan_data['price']
            ]);
        }

        return $upcomingRenewals;
    }

    /**
     * Process overdue subscriptions.
     */
    public function processOverdueSubscriptions(): int
    {
        $overdueSubscriptions = Subscription::where('status', 'past_due')
            ->where('grace_period_ends_at', '<=', Carbon::now())
            ->get();

        $processed = 0;

        foreach ($overdueSubscriptions as $subscription) {
            // Cancel subscription after grace period
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => Carbon::now(),
                'cancellation_reason' => 'Payment overdue - grace period expired'
            ]);

            SubscriptionEvent::createEvent(
                $subscription->id,
                'cancelled',
                'Subscription cancelled due to overdue payment',
                ['automatic_cancellation' => true]
            );

            $processed++;
        }

        return $processed;
    }

    /**
     * Upgrade subscription to a new plan.
     */
    public function upgradePlan(Subscription $subscription, Plan $newPlan, bool $prorate = true): bool
    {
        if (!$subscription->isActive()) {
            return false;
        }

        $oldPlan = $subscription->plan;
        $now = Carbon::now();

        // Calculate proration if needed
        $prorationAmount = 0;
        if ($prorate && $oldPlan->prorate_on_change) {
            $prorationAmount = $this->calculateProration($subscription, $oldPlan, $newPlan);
        }

        // Update subscription
        $changes = [
            'plan_id' => $newPlan->id,
            'plan_data' => [
                'name' => $newPlan->name,
                'price' => $newPlan->price,
                'currency' => $newPlan->currency,
                'features' => $newPlan->features,
                'billing_interval' => $newPlan->billing_interval,
                'billing_interval_count' => $newPlan->billing_interval_count,
            ]
        ];

        // Update next billing date if billing interval changed
        if ($newPlan->billing_interval !== $oldPlan->billing_interval || 
            $newPlan->billing_interval_count !== $oldPlan->billing_interval_count) {
            $changes['next_billing_date'] = $newPlan->calculateNextBillingDate($now);
        }

        $subscription->update($changes);

        // Record plan change in history
        $planChanges = $subscription->plan_changes_history ?? [];
        $planChanges[] = [
            'from_plan_id' => $oldPlan->id,
            'to_plan_id' => $newPlan->id,
            'changed_at' => $now->toISOString(),
            'proration_amount' => $prorationAmount,
            'changed_by' => auth()->user()->id ?? 'system'
        ];

        $subscription->update(['plan_changes_history' => $planChanges]);

        // Log the upgrade event
        SubscriptionEvent::createEvent(
            $subscription->id,
            'plan_upgraded',
            "Plan upgraded from '{$oldPlan->name}' to '{$newPlan->name}'",
            [
                'old_plan_id' => $oldPlan->id,
                'new_plan_id' => $newPlan->id,
                'old_price' => $oldPlan->price,
                'new_price' => $newPlan->price,
                'proration_amount' => $prorationAmount
            ],
            null,
            $newPlan->id
        );

        return true;
    }

    /**
     * Downgrade subscription to a new plan.
     */
    public function downgradePlan(Subscription $subscription, Plan $newPlan, bool $atPeriodEnd = true): bool
    {
        if (!$subscription->isActive()) {
            return false;
        }

        $oldPlan = $subscription->plan;

        if ($atPeriodEnd) {
            // Schedule downgrade for end of current billing period
            $planChanges = $subscription->plan_changes_history ?? [];
            $planChanges[] = [
                'from_plan_id' => $oldPlan->id,
                'to_plan_id' => $newPlan->id,
                'scheduled_for' => $subscription->next_billing_date->toISOString(),
                'scheduled_at' => Carbon::now()->toISOString(),
                'type' => 'downgrade'
            ];

            $subscription->update(['plan_changes_history' => $planChanges]);

            SubscriptionEvent::createEvent(
                $subscription->id,
                'plan_downgraded',
                "Plan downgrade scheduled from '{$oldPlan->name}' to '{$newPlan->name}' at period end",
                [
                    'old_plan_id' => $oldPlan->id,
                    'new_plan_id' => $newPlan->id,
                    'effective_date' => $subscription->next_billing_date,
                    'immediate' => false
                ]
            );
        } else {
            // Immediate downgrade
            $subscription->update([
                'plan_id' => $newPlan->id,
                'plan_data' => [
                    'name' => $newPlan->name,
                    'price' => $newPlan->price,
                    'currency' => $newPlan->currency,
                    'features' => $newPlan->features,
                    'billing_interval' => $newPlan->billing_interval,
                    'billing_interval_count' => $newPlan->billing_interval_count,
                ]
            ]);

            SubscriptionEvent::createEvent(
                $subscription->id,
                'plan_downgraded',
                "Plan downgraded from '{$oldPlan->name}' to '{$newPlan->name}' (immediate)",
                [
                    'old_plan_id' => $oldPlan->id,
                    'new_plan_id' => $newPlan->id,
                    'immediate' => true
                ]
            );
        }

        return true;
    }

    /**
     * Get subscription statistics.
     */
    public function getSubscriptionStats(?int $websiteId = null): array
    {
        $query = Subscription::query();
        
        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        return [
            'total' => $query->count(),
            'active' => $query->clone()->whereIn('status', ['active', 'trial'])->count(),
            'trial' => $query->clone()->where('status', 'trial')->count(),
            'cancelled' => $query->clone()->where('status', 'cancelled')->count(),
            'past_due' => $query->clone()->where('status', 'past_due')->count(),
            'paused' => $query->clone()->where('status', 'paused')->count(),
            'expired' => $query->clone()->where('status', 'expired')->count(),
            'mrr' => $this->calculateMRR($websiteId), // Monthly Recurring Revenue
            'churn_rate' => $this->calculateChurnRate($websiteId),
        ];
    }

    /**
     * Calculate Monthly Recurring Revenue (MRR).
     */
    public function calculateMRR(?int $websiteId = null): float
    {
        $query = Subscription::whereIn('status', ['active', 'trial'])
            ->with('plan');
            
        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        $subscriptions = $query->get();
        $mrr = 0;

        foreach ($subscriptions as $subscription) {
            $plan = $subscription->plan;
            if (!$plan->isRecurring()) continue;

            $monthlyAmount = $plan->price;

            // Convert to monthly amount based on billing interval
            switch ($plan->billing_interval) {
                case 'daily':
                    $monthlyAmount = $plan->price * 30 / $plan->billing_interval_count;
                    break;
                case 'weekly':
                    $monthlyAmount = $plan->price * 4.33 / $plan->billing_interval_count; // ~4.33 weeks per month
                    break;
                case 'monthly':
                    $monthlyAmount = $plan->price / $plan->billing_interval_count;
                    break;
                case 'quarterly':
                    $monthlyAmount = $plan->price / (3 * $plan->billing_interval_count);
                    break;
                case 'yearly':
                    $monthlyAmount = $plan->price / (12 * $plan->billing_interval_count);
                    break;
            }

            $mrr += $monthlyAmount;
        }

        return round($mrr, 2);
    }

    /**
     * Calculate churn rate (percentage of subscriptions cancelled in last 30 days).
     */
    private function calculateChurnRate(?int $websiteId = null): float
    {
        $query = Subscription::query();
        
        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $totalSubscriptions = $query->clone()->where('created_at', '>=', $thirtyDaysAgo)->count();
        $cancelledSubscriptions = $query->clone()
            ->where('cancelled_at', '>=', $thirtyDaysAgo)
            ->count();

        if ($totalSubscriptions === 0) {
            return 0;
        }

        return round(($cancelledSubscriptions / $totalSubscriptions) * 100, 2);
    }

    /**
     * Calculate proration amount when changing plans.
     */
    private function calculateProration(Subscription $subscription, Plan $oldPlan, Plan $newPlan): float
    {
        if (!$subscription->next_billing_date) {
            return 0;
        }

        $daysUntilNextBilling = Carbon::now()->diffInDays($subscription->next_billing_date);
        $totalBillingDays = $this->getBillingPeriodDays($oldPlan);
        
        if ($totalBillingDays === 0) {
            return 0;
        }

        // Calculate unused portion of current plan
        $unusedPortion = $daysUntilNextBilling / $totalBillingDays;
        $refundAmount = $oldPlan->price * $unusedPortion;
        
        // Calculate prorated amount for new plan
        $newPlanProrated = $newPlan->price * $unusedPortion;
        
        return round($newPlanProrated - $refundAmount, 2);
    }

    /**
     * Get billing period in days for a plan.
     */
    private function getBillingPeriodDays(Plan $plan): int
    {
        switch ($plan->billing_interval) {
            case 'daily': return $plan->billing_interval_count;
            case 'weekly': return $plan->billing_interval_count * 7;
            case 'monthly': return $plan->billing_interval_count * 30; // Approximate
            case 'quarterly': return $plan->billing_interval_count * 90; // Approximate
            case 'yearly': return $plan->billing_interval_count * 365; // Approximate
            default: return 0;
        }
    }
}