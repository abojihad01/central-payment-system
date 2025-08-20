<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Plan;
use App\Models\PaymentAccount;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;

class SyncStripePlansAcrossAccounts extends Command
{
    protected $signature = 'stripe:sync-plans {--dry-run : Show what would be done without making changes}';
    protected $description = 'Sync all plans across all Stripe accounts to ensure consistency';

    public function handle()
    {
        $this->info('Starting Stripe plans synchronization...');
        
        $stripeAccounts = PaymentAccount::whereHas('gateway', function($query) {
            $query->where('name', 'stripe');
        })->where('is_active', true)->get();
        
        if ($stripeAccounts->isEmpty()) {
            $this->error('No active Stripe accounts found.');
            return;
        }
        
        $plans = Plan::where('is_active', true)->get();
        
        if ($plans->isEmpty()) {
            $this->error('No active plans found.');
            return;
        }
        
        $this->info("Found {$stripeAccounts->count()} Stripe accounts and {$plans->count()} plans.");
        
        foreach ($plans as $plan) {
            $this->info("\n--- Processing Plan: {$plan->name} (ID: {$plan->id}) ---");
            
            foreach ($stripeAccounts as $account) {
                $this->syncPlanToAccount($plan, $account);
            }
        }
        
        $this->info("\n✅ Synchronization completed!");
    }
    
    private function syncPlanToAccount(Plan $plan, PaymentAccount $account)
    {
        $this->line("Syncing to account: {$account->name} (ID: {$account->id})");
        
        if ($this->option('dry-run')) {
            $this->warn('  [DRY RUN] Would sync plan to this account');
            return;
        }
        
        try {
            // Set Stripe API key for this account
            Stripe::setApiKey($account->getCredential('secret_key'));
            
            // Check if plan already exists in this account's metadata
            $metadata = $plan->metadata ?? [];
            $accountProducts = $metadata['stripe_products'] ?? [];
            
            if (isset($accountProducts[$account->id])) {
                $this->line("  ✓ Plan already exists in this account");
                
                // Verify the product and price still exist in Stripe
                try {
                    $productId = $accountProducts[$account->id]['product_id'];
                    $priceId = $accountProducts[$account->id]['price_id'];
                    
                    Product::retrieve($productId);
                    Price::retrieve($priceId);
                    
                    $this->line("  ✓ Stripe product and price verified");
                    return;
                } catch (\Exception $e) {
                    $this->warn("  ⚠ Stripe verification failed, will recreate: " . $e->getMessage());
                }
            }
            
            // Create product in this Stripe account
            $product = Product::create([
                'name' => $plan->name,
                'description' => $plan->description,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'source_account' => $account->id,
                    'currency' => $plan->currency
                ]
            ]);
            
            $this->line("  ✓ Created Stripe product: {$product->id}");
            
            // Create price for the product
            $priceData = [
                'unit_amount' => intval($plan->price * 100), // Convert to cents
                'currency' => strtolower($plan->currency),
                'product' => $product->id,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'account_id' => $account->id
                ]
            ];
            
            // Add recurring data if plan is recurring
            if ($plan->isRecurring()) {
                $interval = $this->convertBillingInterval($plan->billing_interval);
                $priceData['recurring'] = [
                    'interval' => $interval,
                    'interval_count' => $plan->billing_interval_count ?? 1
                ];
                
                if ($plan->trial_period_days > 0) {
                    $priceData['recurring']['trial_period_days'] = $plan->trial_period_days;
                }
            }
            
            $price = Price::create($priceData);
            
            $this->line("  ✓ Created Stripe price: {$price->id}");
            
            // Update plan metadata with new product/price info
            $updatedMetadata = $metadata;
            $updatedMetadata['stripe_products'] = $updatedMetadata['stripe_products'] ?? [];
            $updatedMetadata['stripe_products'][$account->id] = [
                'product_id' => $product->id,
                'price_id' => $price->id
            ];
            
            $plan->update(['metadata' => $updatedMetadata]);
            
            $this->info("  ✅ Successfully synced plan to account {$account->name}");
            
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to sync to account {$account->name}: " . $e->getMessage());
        }
    }
    
    private function convertBillingInterval(string $interval): string
    {
        return match($interval) {
            'daily' => 'day',
            'weekly' => 'week',
            'monthly' => 'month',
            'quarterly' => 'month', // Stripe doesn't support quarter, use month with count 3
            'yearly' => 'year',
            default => 'month'
        };
    }
}