<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Plan;
use Illuminate\Console\Command;

class FixBrokenSubscriptions extends Command
{
    protected $signature = 'subscriptions:fix-broken 
                           {--dry-run : Show what would be fixed without making changes}
                           {--limit=50 : Maximum number of subscriptions to fix}';

    protected $description = 'Fix subscriptions with broken dates and billing information';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        $this->info('🔍 Looking for broken subscriptions...');

        // Find subscriptions with issues
        $brokenSubscriptions = Subscription::where(function($query) {
            $query->whereNull('expires_at')
                  ->orWhereNull('next_billing_date') 
                  ->orWhere('billing_cycle_count', 0)
                  ->orWhereColumn('starts_at', 'expires_at');
        })
        ->with(['payment.generatedLink.plan', 'plan'])
        ->limit($limit)
        ->get();

        if ($brokenSubscriptions->isEmpty()) {
            $this->info('✅ No broken subscriptions found!');
            return Command::SUCCESS;
        }

        $this->info("Found {$brokenSubscriptions->count()} broken subscriptions");
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $fixed = 0;
        $errors = 0;

        foreach ($brokenSubscriptions as $subscription) {
            try {
                $this->info("Processing subscription {$subscription->id} ({$subscription->customer_email})");
                
                if ($isDryRun) {
                    $this->showWhatWouldBeFixed($subscription);
                } else {
                    $this->fixSubscription($subscription);
                }
                
                $fixed++;
                
            } catch (\Exception $e) {
                $this->error("Failed to fix subscription {$subscription->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->newLine();
        if ($isDryRun) {
            $this->info("📊 Would fix {$fixed} subscriptions");
        } else {
            $this->info("📊 Fixed {$fixed} subscriptions");
        }
        
        if ($errors > 0) {
            $this->warn("⚠️  {$errors} subscriptions had errors");
        }

        return Command::SUCCESS;
    }

    protected function showWhatWouldBeFixed(Subscription $subscription)
    {
        $this->line("  Would fix:");
        
        if (is_null($subscription->expires_at)) {
            $this->line("    - Set expires_at");
        }
        
        if ($subscription->starts_at && $subscription->expires_at && $subscription->starts_at->equalTo($subscription->expires_at)) {
            $this->line("    - Fix expires_at (currently same as starts_at)");
        }
        
        if (is_null($subscription->next_billing_date)) {
            $this->line("    - Set next_billing_date");
        }
        
        if ($subscription->billing_cycle_count == 0) {
            $this->line("    - Set billing_cycle_count to 1");
        }
    }

    protected function fixSubscription(Subscription $subscription)
    {
        // Try to get duration from multiple sources
        $durationDays = $this->getDurationDays($subscription);
        
        if (!$durationDays) {
            $this->warn("  ⚠️  Could not determine duration, using 30 days default");
            $durationDays = 30;
        }

        // Fix dates
        $startsAt = $subscription->starts_at ?: now();
        $expiresAt = $subscription->expires_at;
        
        // If expires_at is null or same as starts_at, calculate new expiry
        if (!$expiresAt || $startsAt->equalTo($expiresAt)) {
            $expiresAt = $startsAt->copy()->addDays($durationDays);
        }
        
        // Calculate next billing date
        $nextBillingDate = $expiresAt->copy();
        
        // Fix billing cycle count
        $billingCycleCount = max(1, $subscription->billing_cycle_count);
        
        // Update subscription
        $subscription->update([
            'expires_at' => $expiresAt,
            'next_billing_date' => $nextBillingDate,
            'billing_cycle_count' => $billingCycleCount,
            'last_billing_date' => $subscription->last_billing_date ?: $startsAt,
            'plan_data' => array_merge($subscription->plan_data ?: [], [
                'duration_days' => $durationDays
            ])
        ]);

        $this->line("  ✅ Fixed subscription {$subscription->id}");
        $this->line("     - Expires: {$expiresAt->format('Y-m-d H:i')}");
        $this->line("     - Next Billing: {$nextBillingDate->format('Y-m-d H:i')}");
        $this->line("     - Cycle Count: {$billingCycleCount}");
    }

    protected function getDurationDays(Subscription $subscription): ?int
    {
        // 1. From subscription plan_data
        if (isset($subscription->plan_data['duration_days'])) {
            return $subscription->plan_data['duration_days'];
        }
        
        // 2. From linked plan
        if ($subscription->plan && $subscription->plan->duration_days) {
            return $subscription->plan->duration_days;
        }
        
        // 3. From payment's generated link plan
        if ($subscription->payment && 
            $subscription->payment->generatedLink && 
            $subscription->payment->generatedLink->plan &&
            $subscription->payment->generatedLink->plan->duration_days) {
            return $subscription->payment->generatedLink->plan->duration_days;
        }
        
        return null;
    }
}