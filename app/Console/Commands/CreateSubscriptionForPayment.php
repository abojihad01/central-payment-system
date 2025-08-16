<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSubscriptionForPayment extends Command
{
    protected $signature = 'subscription:create-for-payment {payment_id}';
    protected $description = 'Create subscription and customer for specific payment ID';

    public function handle()
    {
        $paymentId = $this->argument('payment_id');
        
        $payment = Payment::with(['generatedLink.plan', 'generatedLink.website'])->find($paymentId);
        
        if (!$payment) {
            $this->error("Payment ID {$paymentId} not found.");
            return 1;
        }
        
        $this->info("Processing payment ID: {$payment->id}");
        $this->info("Amount: {$payment->amount} {$payment->currency}");
        $this->info("Customer: {$payment->customer_email}");
        
        if (!$payment->generatedLink) {
            $this->error("Generated link not found for this payment.");
            return 1;
        }
        
        if (!$payment->generatedLink->plan) {
            $this->error("Plan not found for generated link.");
            return 1;
        }
        
        $this->info("Plan: {$payment->generatedLink->plan->name}");
        $this->info("Website ID: {$payment->generatedLink->website_id}");
        
        // Check if subscription already exists
        $existingSubscription = Subscription::where('payment_id', $payment->id)->first();
        if ($existingSubscription) {
            $this->warn("Subscription already exists for this payment: {$existingSubscription->subscription_id}");
            return 0;
        }
        
        // Create customer
        $this->info("Creating/finding customer...");
        $customer = Customer::firstOrCreate([
            'email' => $payment->customer_email,
        ], [
            'customer_id' => 'cust_' . Str::random(16),
            'email' => $payment->customer_email,
            'phone' => $payment->customer_phone,
            'first_name' => explode('@', $payment->customer_email)[0],
            'status' => 'active',
            'risk_score' => 10,
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
        
        $this->info("Customer created/found: {$customer->email} (ID: {$customer->id})");
        
        // Create subscription
        $this->info("Creating subscription...");
        $subscription = Subscription::create([
            'subscription_id' => 'sub_' . Str::random(24),
            'payment_id' => $payment->id,
            'website_id' => $payment->generatedLink->website_id,
            'plan_id' => $payment->generatedLink->plan_id,
            'customer_email' => $payment->customer_email,
            'customer_phone' => $payment->customer_phone,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => $payment->generatedLink->plan->duration_days 
                ? now()->addDays($payment->generatedLink->plan->duration_days) 
                : null,
            'plan_data' => [
                'name' => $payment->generatedLink->plan->name,
                'price' => $payment->amount,
                'currency' => $payment->currency,
                'features' => $payment->generatedLink->plan->features ?? 'No features'
            ]
        ]);
        
        $this->info("Subscription created: {$subscription->subscription_id}");
        $this->info("Status: {$subscription->status}");
        $this->info("Starts at: {$subscription->starts_at}");
        $this->info("Expires at: " . ($subscription->expires_at ? $subscription->expires_at : 'Never'));
        
        $this->info("SUCCESS: Payment processed successfully!");
        
        return 0;
    }
}