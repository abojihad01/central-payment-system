<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Models\PaymentAccount;
use App\Services\StripeSubscriptionService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;
    
    protected function afterCreate(): void
    {
        $record = $this->record;
        $data = $this->form->getState();
        
        if ($data['create_stripe_product'] ?? false) {
            $this->createStripeProducts($record, $data['stripe_account_ids'] ?? []);
        }
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['create_stripe_product'], $data['stripe_account_ids']);
        
        if ($data['subscription_type'] === 'one_time') {
            $data['billing_interval'] = null;
            $data['billing_interval_count'] = null;
            $data['trial_period_days'] = 0;
            $data['setup_fee'] = 0;
            $data['max_billing_cycles'] = null;
            $data['prorate_on_change'] = false;
        }
        
        return $data;
    }
    
    private function createStripeProducts($plan, array $stripeAccountIds): void
    {
        if (empty($stripeAccountIds)) {
            Notification::make()
                ->title('No Stripe Accounts Selected')
                ->body('Please select at least one Stripe account')
                ->warning()
                ->send();
            return;
        }

        $accounts = PaymentAccount::with('gateway')->whereIn('id', $stripeAccountIds)->get();
        $successfulCreations = [];
        $failedCreations = [];
        $stripeProducts = [];

        foreach ($accounts as $account) {
            if (!$account->gateway || $account->gateway->name !== 'stripe') {
                $failedCreations[] = "Account '{$account->name}' is not a valid Stripe account";
                continue;
            }

            try {
                $stripeService = app(StripeSubscriptionService::class);
                $result = $stripeService->createProductAndPrice($plan, $account);
                
                $successfulCreations[] = [
                    'account_name' => $account->name,
                    'account_id' => $account->id,
                    'product_id' => $result['product']->id,
                    'price_id' => $result['price']->id
                ];
                
                $stripeProducts[$account->id] = [
                    'product_id' => $result['product']->id,
                    'price_id' => $result['price']->id
                ];
                
            } catch (\Exception $e) {
                $failedCreations[] = "Account '{$account->name}': {$e->getMessage()}";
            }
        }

        // Update metadata with all successful Stripe products
        if (!empty($successfulCreations)) {
            $metadata = $plan->metadata ?? [];
            
            // If only one account was successful, use legacy single product format
            if (count($successfulCreations) === 1) {
                $success = $successfulCreations[0];
                $metadata['stripe_product_id'] = $success['product_id'];
                $metadata['stripe_price_id'] = $success['price_id'];
                $metadata['stripe_account_id'] = $success['account_id'];
            }
            
            // Always save multiple products info for future reference
            $metadata['stripe_products'] = $stripeProducts;
            $metadata['recurring'] = $plan->subscription_type === 'recurring';
            $metadata['created_via_admin'] = true;
            $metadata['creation_timestamp'] = now()->toISOString();
            
            $plan->update(['metadata' => $metadata]);
        }

        // Show comprehensive notification
        $this->showCreationResults($successfulCreations, $failedCreations);
    }

    private function showCreationResults(array $successful, array $failed): void
    {
        if (empty($successful) && empty($failed)) {
            return;
        }

        if (!empty($successful) && empty($failed)) {
            // All successful
            $accountNames = collect($successful)->pluck('account_name')->implode(', ');
            $productIds = collect($successful)->pluck('product_id')->implode(', ');
            
            Notification::make()
                ->title('Plan Created Successfully!')
                ->body("Plan created and synced to Stripe accounts: {$accountNames}. Product IDs: {$productIds}")
                ->success()
                ->send();
                
        } elseif (!empty($successful) && !empty($failed)) {
            // Partial success
            $successCount = count($successful);
            $failureCount = count($failed);
            
            Notification::make()
                ->title('Plan Created with Partial Stripe Sync')
                ->body("Plan created successfully. {$successCount} Stripe products created, {$failureCount} failed. Check logs for details.")
                ->warning()
                ->send();
                
        } else {
            // All failed
            Notification::make()
                ->title('Plan Created but All Stripe Syncs Failed')
                ->body("Plan created successfully, but failed to create in any Stripe account. " . implode('; ', $failed))
                ->warning()
                ->send();
        }
    }
}
