<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Customer;
use App\Models\Subscription;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CheckPendingPayments extends Command
{
    protected $signature = 'payments:check-pending 
                            {--max-age=24 : Maximum age in hours for pending payments to process}
                            {--limit=50 : Maximum number of payments to process at once}
                            {--dry-run : Show what would be processed without actually processing}';

    protected $description = 'Check and process pending payments that need to be converted to subscriptions';

    public function handle()
    {
        $maxAgeHours = $this->option('max-age');
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');
        
        $this->info("🔍 Checking for pending payments...");
        
        // Get pending payments older than specified hours
        $cutoffTime = Carbon::now()->subHours($maxAgeHours);
        
        $pendingPayments = Payment::where('status', 'pending')
            ->where('created_at', '<=', $cutoffTime)
            ->limit($limit)
            ->orderBy('created_at', 'asc')
            ->get();
            
        if ($pendingPayments->isEmpty()) {
            $this->info("✅ No pending payments found older than {$maxAgeHours} hours.");
            return 0;
        }
        
        $this->info("Found {$pendingPayments->count()} pending payments to process:");
        
        // Display summary table
        $tableData = [];
        foreach ($pendingPayments as $payment) {
            $tableData[] = [
                'ID' => $payment->id,
                'Amount' => $payment->amount . ' ' . $payment->currency,
                'Email' => $payment->customer_email,
                'Age' => $payment->created_at->diffForHumans(),
                'Gateway' => $payment->payment_gateway ?? 'N/A'
            ];
        }
        
        $this->table(['ID', 'Amount', 'Email', 'Age', 'Gateway'], $tableData);
        
        if ($dryRun) {
            $this->warn("🏃‍♂️ DRY RUN MODE - No changes will be made");
            return 0;
        }
        
        // Ask for confirmation unless running in quiet mode
        if (!$this->option('quiet')) {
            if (!$this->confirm('Do you want to process these pending payments?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        // Process payments
        $processed = 0;
        $failed = 0;
        $errors = [];
        
        $progressBar = $this->output->createProgressBar($pendingPayments->count());
        $progressBar->start();
        
        foreach ($pendingPayments as $payment) {
            try {
                $result = $this->processPayment($payment);
                if ($result) {
                    $processed++;
                } else {
                    $failed++;
                    $errors[] = "Payment {$payment->id}: Processing failed";
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Payment {$payment->id}: {$e->getMessage()}";
                \Log::error('Failed to process pending payment', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        // Summary
        $this->info("📊 Processing Summary:");
        $this->info("✅ Successfully processed: {$processed}");
        
        if ($failed > 0) {
            $this->error("❌ Failed: {$failed}");
            if (!empty($errors)) {
                $this->newLine();
                $this->error("Errors encountered:");
                foreach ($errors as $error) {
                    $this->error("  • {$error}");
                }
            }
        }
        
        $this->info("🎉 Job completed!");
        
        return $failed > 0 ? 1 : 0;
    }
    
    private function processPayment(Payment $payment): bool
    {
        try {
            // Get plan information
            if (!$payment->generated_link) {
                $this->error("Payment {$payment->id}: No generated link found");
                return false;
            }
            
            $plan = Plan::find($payment->generated_link->plan_id);
            if (!$plan) {
                $this->error("Payment {$payment->id}: Plan not found");
                return false;
            }
            
            // Create or find customer
            $customer = Customer::firstOrCreate([
                'email' => $payment->customer_email,
            ], [
                'customer_id' => 'cust_' . Str::random(16),
                'email' => $payment->customer_email,
                'phone' => $payment->customer_phone,
                'first_name' => $this->extractNameFromEmail($payment->customer_email),
                'last_name' => '',
                'status' => 'active',
                'registration_date' => now(),
                'last_activity' => now(),
                'total_spent' => $payment->amount,
                'total_orders' => 1,
                'average_order_value' => $payment->amount,
                'ltv_prediction' => $payment->amount * 2,
                'risk_score' => 0,
                'preferred_currency' => $payment->currency,
                'marketing_consent' => false,
                'data_retention_consent' => true,
                'account_status' => 'verified',
            ]);
            
            // Create subscription
            $subscription = Subscription::create([
                'subscription_id' => Str::uuid(),
                'gateway_subscription_id' => $payment->gateway_session_id,
                'billing_cycle_count' => 0,
                'next_billing_date' => $this->calculateNextBillingDate($plan),
                'is_trial' => $plan->trial_period_days > 0,
                'trial_ends_at' => $plan->trial_period_days > 0 ? now()->addDays($plan->trial_period_days) : null,
                'status' => 'active',
                'payment_id' => $payment->id,
                'website_id' => $payment->generated_link->website_id,
                'plan_id' => $plan->id,
                'customer_email' => $payment->customer_email,
                'customer_phone' => $payment->customer_phone,
                'starts_at' => now(),
                'expires_at' => $this->calculateExpirationDate($plan),
                'plan_data' => [
                    'plan_name' => $plan->name,
                    'plan_price' => $plan->price,
                    'plan_currency' => $plan->currency,
                    'billing_interval' => $plan->billing_interval,
                    'billing_interval_count' => $plan->billing_interval_count,
                ],
            ]);
            
            // Update payment status
            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
            ]);
            
            $this->line("✅ Payment {$payment->id} processed successfully - Customer: {$customer->id}, Subscription: {$subscription->id}");
            
            return true;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to process payment {$payment->id}: {$e->getMessage()}");
            return false;
        }
    }
    
    private function extractNameFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        $username = $parts[0];
        
        // Remove numbers and special characters, capitalize
        $name = preg_replace('/[^a-zA-Z]/', ' ', $username);
        $name = trim($name);
        
        return $name ? ucwords($name) : 'Customer';
    }
    
    private function calculateNextBillingDate(Plan $plan): ?\Carbon\Carbon
    {
        if ($plan->subscription_type !== 'recurring' || !$plan->billing_interval) {
            return null;
        }
        
        $nextDate = now();
        $count = $plan->billing_interval_count ?? 1;
        
        return match($plan->billing_interval) {
            'daily' => $nextDate->addDays($count),
            'weekly' => $nextDate->addWeeks($count),
            'monthly' => $nextDate->addMonths($count),
            'quarterly' => $nextDate->addMonths($count * 3),
            'yearly' => $nextDate->addYears($count),
            default => null,
        };
    }
    
    private function calculateExpirationDate(Plan $plan): ?\Carbon\Carbon
    {
        if ($plan->subscription_type === 'recurring') {
            return null; // Recurring subscriptions don't expire
        }
        
        if ($plan->duration_days && $plan->duration_days > 0) {
            return now()->addDays($plan->duration_days);
        }
        
        return null; // Lifetime access
    }
}
