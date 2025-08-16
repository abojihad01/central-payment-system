<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Plan;
use App\Models\PaymentAccount;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;

class SyncStripeProducts extends Command
{
    protected $signature = 'stripe:sync-products {--account=} {--create-plans} {--update-existing}';
    protected $description = 'Sync products and prices from Stripe API';

    public function handle()
    {
        $accountId = $this->option('account');
        $createPlans = $this->option('create-plans');
        $updateExisting = $this->option('update-existing');
        
        // Get Stripe account
        $account = $accountId 
            ? PaymentAccount::find($accountId)
            : PaymentAccount::whereHas('gateway', function($q) {
                $q->where('name', 'stripe');
            })->where('is_active', true)->first();
            
        if (!$account) {
            $this->error('No active Stripe account found. Please specify --account=ID or configure a Stripe account.');
            return 1;
        }
        
        $credentials = $account->credentials;
        if (!$credentials || !isset($credentials['secret_key'])) {
            $this->error('Stripe credentials not found for account: ' . $account->name);
            return 1;
        }
        
        Stripe::setApiKey($credentials['secret_key']);
        
        $this->info("Syncing with Stripe account: {$account->name}");
        
        try {
            // Fetch all products from Stripe
            $products = Product::all(['limit' => 100]);
            $this->info("Found {$products->count()} products in Stripe");
            
            $synced = 0;
            $created = 0;
            $updated = 0;
            
            foreach ($products->data as $product) {
                // Get all prices for this product
                $prices = Price::all(['product' => $product->id, 'limit' => 100]);
                
                foreach ($prices->data as $price) {
                    $result = $this->syncProduct($product, $price, $account, $createPlans, $updateExisting);
                    
                    if ($result === 'created') $created++;
                    elseif ($result === 'updated') $updated++;
                    elseif ($result === 'synced') $synced++;
                }
            }
            
            $this->info("Sync complete!");
            $this->table(['Action', 'Count'], [
                ['Created new plans', $created],
                ['Updated existing plans', $updated], 
                ['Synced existing plans', $synced],
            ]);
            
        } catch (\Exception $e) {
            $this->error('Error syncing with Stripe: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function syncProduct($product, $price, $account, $createPlans, $updateExisting)
    {
        // Check if plan already exists with this Stripe product
        $existingPlan = Plan::where('metadata->stripe_product_id', $product->id)
            ->orWhere('metadata->stripe_price_id', $price->id)
            ->first();
            
        if ($existingPlan) {
            if ($updateExisting) {
                // Update existing plan with Stripe data
                $this->updatePlanFromStripe($existingPlan, $product, $price, $account);
                $this->line("Updated plan: {$existingPlan->name}");
                return 'updated';
            } else {
                // Just sync the metadata
                $metadata = $existingPlan->metadata ?? [];
                $metadata['stripe_product_id'] = $product->id;
                $metadata['stripe_price_id'] = $price->id;
                $metadata['stripe_account_id'] = $account->id;
                $existingPlan->update(['metadata' => $metadata]);
                $this->line("Synced plan: {$existingPlan->name}");
                return 'synced';
            }
        }
        
        if ($createPlans) {
            // Create new plan from Stripe product
            $newPlan = $this->createPlanFromStripe($product, $price, $account);
            $this->info("Created new plan: {$newPlan->name}");
            return 'created';
        }
        
        $this->comment("Skipped product (use --create-plans to create): {$product->name}");
        return 'skipped';
    }
    
    private function createPlanFromStripe($product, $price, $account)
    {
        $currency = strtoupper($price->currency);
        $amount = $price->unit_amount / 100; // Convert from cents
        
        // Determine if it's recurring
        $isRecurring = isset($price->recurring);
        $durationDays = null;
        
        if ($isRecurring) {
            $interval = $price->recurring->interval;
            $intervalCount = $price->recurring->interval_count ?? 1;
            
            switch ($interval) {
                case 'day':
                    $durationDays = $intervalCount;
                    break;
                case 'week':
                    $durationDays = $intervalCount * 7;
                    break;
                case 'month':
                    $durationDays = $intervalCount * 30;
                    break;
                case 'year':
                    $durationDays = $intervalCount * 365;
                    break;
            }
        }
        
        return Plan::create([
            'name' => $product->name,
            'description' => $product->description ?? "Imported from Stripe: {$product->name}",
            'price' => $amount,
            'currency' => $currency,
            'duration_days' => $durationDays,
            'features' => $product->metadata['features'] ?? '',
            'metadata' => [
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
                'stripe_account_id' => $account->id,
                'recurring' => $isRecurring,
                'imported_from_stripe' => true,
                'stripe_interval' => $price->recurring->interval ?? null,
                'stripe_interval_count' => $price->recurring->interval_count ?? null,
            ]
        ]);
    }
    
    private function updatePlanFromStripe($plan, $product, $price, $account)
    {
        $currency = strtoupper($price->currency);
        $amount = $price->unit_amount / 100;
        $isRecurring = isset($price->recurring);
        
        $durationDays = null;
        if ($isRecurring) {
            $interval = $price->recurring->interval;
            $intervalCount = $price->recurring->interval_count ?? 1;
            
            switch ($interval) {
                case 'day':
                    $durationDays = $intervalCount;
                    break;
                case 'week':
                    $durationDays = $intervalCount * 7;
                    break;
                case 'month':
                    $durationDays = $intervalCount * 30;
                    break;
                case 'year':
                    $durationDays = $intervalCount * 365;
                    break;
            }
        }
        
        $metadata = $plan->metadata ?? [];
        $metadata['stripe_product_id'] = $product->id;
        $metadata['stripe_price_id'] = $price->id;
        $metadata['stripe_account_id'] = $account->id;
        $metadata['recurring'] = $isRecurring;
        $metadata['updated_from_stripe'] = now()->toISOString();
        
        $plan->update([
            'name' => $product->name,
            'description' => $product->description ?? $plan->description,
            'price' => $amount,
            'currency' => $currency,
            'duration_days' => $durationDays,
            'metadata' => $metadata
        ]);
    }
}