<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProcessPendingPayments extends Command
{
    protected $signature = 'payments:process-pending';
    protected $description = 'Process pending payments and create subscriptions and customers';

    public function handle()
    {
        $this->info('Processing pending payments...');
        
        // Get all pending payments that don't have subscriptions yet
        $pendingPayments = Payment::where('status', 'pending')
            ->whereDoesntHave('subscription')
            ->with(['generatedLink.plan', 'generatedLink.website'])
            ->get();
            
        if ($pendingPayments->isEmpty()) {
            $this->info('No pending payments found.');
            return 0;
        }
        
        $this->info("Found {$pendingPayments->count()} pending payments to process.");
        
        $processed = 0;
        $errors = 0;
        
        foreach ($pendingPayments as $payment) {
            try {
                // For testing purposes, process all pending payments
                // In production, this should only be done after actual payment confirmation
                $this->processPayment($payment);
                $processed++;
                $this->info("Processed payment ID: {$payment->id}");
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error processing payment ID {$payment->id}: " . $e->getMessage());
            }
        }
        
        $this->info("Processing complete. Processed: {$processed}, Errors: {$errors}");
        
        return 0;
    }
    
    private function processPayment(Payment $payment)
    {
        // Mark payment as completed
        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);
        
        // Create or find customer
        $customer = Customer::firstOrCreate([
            'email' => $payment->customer_email,
        ], [
            'customer_id' => 'cust_' . Str::random(16),
            'email' => $payment->customer_email,
            'phone' => $payment->customer_phone,
            'first_name' => $this->extractNameFromEmail($payment->customer_email),
            'status' => 'active',
            'risk_score' => 10, // Low risk for new customers
            'risk_level' => 'low',
            'total_subscriptions' => 0,
            'active_subscriptions' => 0,
            'lifetime_value' => 0,
            'total_spent' => 0,
            'successful_payments' => 0,
            'failed_payments' => 0,
            'chargebacks' => 0,
            'refunds' => 0,
            'preferences' => [],
            'payment_methods' => [$payment->payment_gateway],
            'subscription_history' => [],
            'tags' => ['payment_link'],
            'first_purchase_at' => now(),
            'acquisition_source' => 'payment_link',
            'marketing_consent' => false,
            'email_verified' => false
        ]);
        
        // Calculate dates based on billing interval
        $startsAt = now();
        $expiresAt = $payment->generatedLink->plan->calculateExpiryDate($startsAt);
        $durationDays = $payment->generatedLink->plan->getSubscriptionDurationDays();
        
        // Create subscription
        $subscription = Subscription::create([
            'subscription_id' => 'sub_' . Str::random(24),
            'payment_id' => $payment->id,
            'website_id' => $payment->generatedLink->website_id,
            'plan_id' => $payment->generatedLink->plan_id,
            'customer_email' => $payment->customer_email,
            'customer_phone' => $payment->customer_phone,
            'status' => 'active',
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'next_billing_date' => $expiresAt->copy(),
            'billing_cycle_count' => 1,
            'last_billing_date' => $startsAt,
            'plan_data' => [
                'name' => $payment->generatedLink->plan->name,
                'price' => $payment->amount,
                'currency' => $payment->currency,
                'duration_days' => $durationDays,
                'features' => $payment->generatedLink->plan->features ?? []
            ]
        ]);
        
        // Update payment account statistics
        if ($payment->payment_account_id) {
            $account = \App\Models\PaymentAccount::find($payment->payment_account_id);
            if ($account) {
                $account->incrementSuccessfulTransaction($payment->amount);
            }
        }
        
        $this->line("  → Created customer: {$customer->email}");
        $this->line("  → Created subscription: {$subscription->subscription_id}");
    }
    
    private function extractNameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0];
        return ucfirst(str_replace(['.', '_', '-', '+'], ' ', $localPart));
    }
}