<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerEvent;
use App\Models\CustomerSegment;
use App\Models\Subscription;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CustomerService
{
    /**
     * Create or update customer from payment data
     */
    public function createOrUpdateCustomer(
        string $email,
        ?string $phone = null,
        ?array $additionalData = [],
        ?string $source = 'payment'
    ): Customer {
        $customerId = $this->generateCustomerId();
        
        $customer = Customer::updateOrCreate(
            ['email' => $email],
            array_merge([
                'customer_id' => $customerId,
                'phone' => $phone,
                'acquisition_source' => $source,
                'first_purchase_at' => now(),
            ], $additionalData)
        );

        // Log creation event if new customer
        if ($customer->wasRecentlyCreated) {
            $this->logCustomerEvent(
                $customer->id,
                'customer_created',
                'Customer account created',
                [
                    'source' => $source,
                    'email' => $email,
                    'phone' => $phone
                ]
            );
        }

        return $customer;
    }

    /**
     * Update customer statistics after successful payment
     */
    public function updateCustomerStatsAfterPayment(Customer $customer, Payment $payment): void
    {
        $customer->increment('successful_payments');
        $customer->increment('total_spent', $payment->amount);
        $customer->update([
            'last_purchase_at' => now(),
            'lifetime_value' => $customer->total_spent + $payment->amount
        ]);

        // Update risk score (successful payments reduce risk)
        $this->updateRiskScore($customer, -5);

        $this->logCustomerEvent(
            $customer->id,
            'payment_successful',
            "Payment of {$payment->amount} {$payment->currency} processed successfully",
            [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_gateway' => $payment->payment_gateway
            ]
        );
    }

    /**
     * Update customer statistics after failed payment
     */
    public function updateCustomerStatsAfterFailedPayment(Customer $customer, Payment $payment): void
    {
        $customer->increment('failed_payments');

        // Update risk score (failed payments increase risk)
        $this->updateRiskScore($customer, 10);

        $this->logCustomerEvent(
            $customer->id,
            'payment_failed',
            "Payment of {$payment->amount} {$payment->currency} failed",
            [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_gateway' => $payment->payment_gateway,
                'failure_reason' => $payment->failure_reason ?? 'Unknown'
            ]
        );
    }

    /**
     * Update customer statistics after new subscription
     */
    public function updateCustomerStatsAfterSubscription(Customer $customer, Subscription $subscription): void
    {
        $customer->increment('total_subscriptions');
        $customer->increment('active_subscriptions');

        $this->logCustomerEvent(
            $customer->id,
            'subscription_created',
            "New subscription created: {$subscription->plan_data['name']}",
            [
                'subscription_id' => $subscription->subscription_id,
                'plan_name' => $subscription->plan_data['name'],
                'plan_price' => $subscription->plan_data['price'],
                'plan_currency' => $subscription->plan_data['currency']
            ]
        );

        // Update subscription history
        $history = $customer->subscription_history ?? [];
        $history[] = [
            'subscription_id' => $subscription->subscription_id,
            'plan_name' => $subscription->plan_data['name'],
            'started_at' => $subscription->starts_at->toISOString(),
            'status' => $subscription->status
        ];
        $customer->update(['subscription_history' => $history]);
    }

    /**
     * Get customer analytics
     */
    public function getCustomerAnalytics(Customer $customer): array
    {
        $subscriptions = Subscription::where('customer_email', $customer->email)->get();
        $payments = Payment::where('customer_email', $customer->email)->get();

        return [
            'customer_id' => $customer->customer_id,
            'email' => $customer->email,
            'status' => $customer->status,
            'risk_level' => $customer->risk_level,
            'lifetime_value' => $customer->lifetime_value,
            'total_spent' => $customer->total_spent,
            'acquisition_source' => $customer->acquisition_source,
            
            // Subscription metrics
            'total_subscriptions' => $subscriptions->count(),
            'active_subscriptions' => $subscriptions->where('status', 'active')->count(),
            'cancelled_subscriptions' => $subscriptions->where('status', 'cancelled')->count(),
            'average_subscription_duration' => $this->calculateAverageSubscriptionDuration($subscriptions),
            
            // Payment metrics
            'total_payments' => $payments->count(),
            'successful_payment_rate' => $customer->successful_payments / max(1, $customer->successful_payments + $customer->failed_payments) * 100,
            'average_payment_amount' => $payments->where('status', 'completed')->avg('amount') ?? 0,
            'preferred_payment_method' => $this->getPreferredPaymentMethod($payments),
            
            // Engagement metrics
            'days_since_first_purchase' => $customer->first_purchase_at ? $customer->first_purchase_at->diffInDays(now()) : 0,
            'days_since_last_purchase' => $customer->last_purchase_at ? $customer->last_purchase_at->diffInDays(now()) : 0,
            'purchase_frequency' => $this->calculatePurchaseFrequency($customer),
            
            // Risk metrics
            'risk_score' => $customer->risk_score,
            'chargeback_rate' => $customer->chargebacks / max(1, $customer->successful_payments) * 100,
            'refund_rate' => $customer->refunds / max(1, $customer->successful_payments) * 100,
        ];
    }

    /**
     * Segment customers based on behavior
     */
    public function segmentCustomers(): array
    {
        $segments = [
            'high_value' => Customer::where('lifetime_value', '>', 1000)
                ->where('status', 'active')
                ->count(),
            
            'frequent_buyers' => Customer::where('successful_payments', '>', 5)
                ->where('status', 'active')
                ->count(),
            
            'at_risk' => Customer::where('risk_score', '>', 50)
                ->where('status', 'active')
                ->count(),
            
            'new_customers' => Customer::where('created_at', '>=', now()->subDays(30))
                ->count(),
            
            'inactive_customers' => Customer::where('last_purchase_at', '<', now()->subDays(90))
                ->where('status', 'active')
                ->count(),
            
            'single_purchase' => Customer::where('successful_payments', '=', 1)
                ->where('status', 'active')
                ->count(),
            
            'subscription_customers' => Customer::where('active_subscriptions', '>', 0)
                ->count(),
            
            'international_customers' => Customer::whereNotIn('country_code', ['US', 'CA', 'GB'])
                ->whereNotNull('country_code')
                ->where('status', 'active')
                ->count(),
        ];

        return $segments;
    }

    /**
     * Get customer lifetime value predictions
     */
    public function predictCustomerLTV(Customer $customer): array
    {
        $analytics = $this->getCustomerAnalytics($customer);
        
        // Simple LTV prediction based on historical data
        $monthlyValue = 0;
        $retentionProbability = 0.8; // Default 80% retention
        
        if ($analytics['days_since_first_purchase'] > 0) {
            $monthlyValue = $customer->total_spent / max(1, $analytics['days_since_first_purchase'] / 30);
            
            // Adjust retention based on payment success rate
            $retentionProbability = min(0.95, $analytics['successful_payment_rate'] / 100);
            
            // Adjust based on subscription status
            if ($analytics['active_subscriptions'] > 0) {
                $retentionProbability += 0.1; // Subscription customers have higher retention
            }
        }

        $predictedLTV = $monthlyValue * 12 * $retentionProbability;
        
        return [
            'current_ltv' => $customer->lifetime_value,
            'predicted_ltv_12_months' => round($predictedLTV, 2),
            'monthly_value' => round($monthlyValue, 2),
            'retention_probability' => round($retentionProbability * 100, 1),
            'ltv_segment' => $this->getLTVSegment($predictedLTV),
        ];
    }

    /**
     * Block customer
     */
    public function blockCustomer(Customer $customer, string $reason): void
    {
        $customer->update([
            'status' => 'blocked',
            'risk_level' => 'blocked',
            'risk_score' => 100,
            'notes' => ($customer->notes ?? '') . "\n" . "Blocked on " . now()->format('Y-m-d H:i:s') . ": " . $reason
        ]);

        $this->logCustomerEvent(
            $customer->id,
            'customer_blocked',
            "Customer blocked: {$reason}",
            ['reason' => $reason, 'blocked_by' => auth()->user()->id ?? 'system']
        );
    }

    /**
     * Unblock customer
     */
    public function unblockCustomer(Customer $customer, string $reason): void
    {
        $customer->update([
            'status' => 'active',
            'risk_level' => 'low',
            'risk_score' => max(0, $customer->risk_score - 30),
            'notes' => ($customer->notes ?? '') . "\n" . "Unblocked on " . now()->format('Y-m-d H:i:s') . ": " . $reason
        ]);

        $this->logCustomerEvent(
            $customer->id,
            'customer_unblocked',
            "Customer unblocked: {$reason}",
            ['reason' => $reason, 'unblocked_by' => auth()->user()->id ?? 'system']
        );
    }

    /**
     * Generate unique customer ID
     */
    private function generateCustomerId(): string
    {
        do {
            $customerId = 'CUST_' . strtoupper(Str::random(8));
        } while (Customer::where('customer_id', $customerId)->exists());

        return $customerId;
    }

    /**
     * Log customer event
     */
    private function logCustomerEvent(
        int $customerId,
        string $eventType,
        string $description,
        array $metadata = [],
        ?string $source = null,
        ?string $ipAddress = null
    ): CustomerEvent {
        return CustomerEvent::create([
            'customer_id' => $customerId,
            'event_type' => $eventType,
            'description' => $description,
            'metadata' => $metadata,
            'source' => $source ?? 'system',
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Update customer risk score
     */
    private function updateRiskScore(Customer $customer, int $adjustment): void
    {
        $newScore = max(0, min(100, $customer->risk_score + $adjustment));
        
        $riskLevel = match (true) {
            $newScore >= 80 => 'blocked',
            $newScore >= 60 => 'high',
            $newScore >= 30 => 'medium',
            default => 'low'
        };

        $customer->update([
            'risk_score' => $newScore,
            'risk_level' => $riskLevel
        ]);
    }

    /**
     * Calculate average subscription duration
     */
    private function calculateAverageSubscriptionDuration($subscriptions): int
    {
        $durations = [];
        
        foreach ($subscriptions as $subscription) {
            if ($subscription->cancelled_at) {
                $duration = $subscription->starts_at->diffInDays($subscription->cancelled_at);
                $durations[] = $duration;
            } elseif ($subscription->status === 'active') {
                $duration = $subscription->starts_at->diffInDays(now());
                $durations[] = $duration;
            }
        }

        return empty($durations) ? 0 : (int) array_sum($durations) / count($durations);
    }

    /**
     * Get preferred payment method
     */
    private function getPreferredPaymentMethod($payments): ?string
    {
        $methods = $payments->where('status', 'completed')
            ->groupBy('payment_gateway')
            ->map->count()
            ->sortDesc();

        return $methods->keys()->first();
    }

    /**
     * Calculate purchase frequency (purchases per month)
     */
    private function calculatePurchaseFrequency(Customer $customer): float
    {
        if (!$customer->first_purchase_at || $customer->successful_payments === 0) {
            return 0;
        }

        $monthsSinceFirstPurchase = max(1, $customer->first_purchase_at->diffInMonths(now()));
        return round($customer->successful_payments / $monthsSinceFirstPurchase, 2);
    }

    /**
     * Get LTV segment
     */
    private function getLTVSegment(float $ltv): string
    {
        return match (true) {
            $ltv >= 2000 => 'champion',
            $ltv >= 1000 => 'high_value',
            $ltv >= 500 => 'medium_value',
            $ltv >= 100 => 'low_value',
            default => 'minimal'
        };
    }
}