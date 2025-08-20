<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Plan;
use App\Models\PaymentAccount;
use App\Services\StripeSubscriptionService;

class ManageStripeProducts extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationGroup = 'إدارة المدفوعات';
    
    protected static ?string $navigationLabel = 'منتجات سترايب';

    protected static string $view = 'filament.pages.manage-stripe-products';
    
    protected static ?int $navigationSort = 6;
    
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\StripeSyncStatusWidget::class,
        ];
    }

    public $selectedPlan = null;
    public $selectedAccount = null;
    
    public function mount(): void
    {
        $this->selectedPlan = Plan::first()?->id;
        $this->selectedAccount = PaymentAccount::whereHas('gateway', function($q) {
            $q->where('name', 'stripe');
        })->first()?->id;
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createStripeProduct')
                ->label('إنشاء منتج سترايب')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('plan_id')
                        ->label('اختر الخطة')
                        ->options(Plan::all()->pluck('name', 'id'))
                        ->required(),
                    Forms\Components\Select::make('payment_account_id')
                        ->label('حساب سترايب')
                        ->options(PaymentAccount::whereHas('gateway', function($q) {
                            $q->where('name', 'stripe');
                        })->pluck('name', 'id'))
                        ->required(),
                    Forms\Components\Toggle::make('is_recurring')
                        ->label('اشتراك متكرر')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $plan = Plan::find($data['plan_id']);
                    $account = PaymentAccount::find($data['payment_account_id']);
                    
                    try {
                        $stripeService = app(StripeSubscriptionService::class);
                        $result = $stripeService->createProductAndPrice($plan, $account);
                        
                        // Update plan metadata
                        $metadata = $plan->metadata ?? [];
                        $metadata['stripe_product_id'] = $result['product']->id;
                        $metadata['stripe_price_id'] = $result['price']->id;
                        $metadata['recurring'] = $data['is_recurring'];
                        $metadata['stripe_account_id'] = $account->id;
                        
                        $plan->update(['metadata' => $metadata]);
                        
                        Notification::make()
                            ->title('Stripe Product Created Successfully')
                            ->body("Product: {$result['product']->id}, Price: {$result['price']->id}")
                            ->success()
                            ->send();
                            
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Failed to Create Stripe Product')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
                
            Action::make('importFromStripe')
                ->label('Import from Stripe')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('payment_account_id')
                        ->label('Stripe Account')
                        ->options(PaymentAccount::whereHas('gateway', function($q) {
                            $q->where('name', 'stripe');
                        })->pluck('name', 'id'))
                        ->required(),
                    Forms\Components\Toggle::make('create_new_plans')
                        ->label('Create New Plans from Stripe Products')
                        ->default(true),
                    Forms\Components\Toggle::make('update_existing')
                        ->label('Update Existing Plans with Stripe Data')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    try {
                        $account = PaymentAccount::find($data['payment_account_id']);
                        $credentials = $account->credentials;
                        
                        if (!$credentials || !isset($credentials['secret_key'])) {
                            Notification::make()
                                ->title('Invalid Stripe Account')
                                ->body('Stripe credentials not found for this account')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        \Stripe\Stripe::setApiKey($credentials['secret_key']);
                        
                        // Fetch products from Stripe
                        $products = \Stripe\Product::all(['limit' => 100]);
                        
                        $created = 0;
                        $updated = 0;
                        $synced = 0;
                        
                        foreach ($products->data as $product) {
                            $prices = \Stripe\Price::all(['product' => $product->id, 'limit' => 100]);
                            
                            foreach ($prices->data as $price) {
                                $existingPlan = Plan::where('metadata->stripe_product_id', $product->id)
                                    ->orWhere('metadata->stripe_price_id', $price->id)
                                    ->first();
                                
                                if ($existingPlan) {
                                    if ($data['update_existing']) {
                                        $this->updatePlanFromStripeData($existingPlan, $product, $price, $account);
                                        $updated++;
                                    } else {
                                        // Just sync metadata
                                        $metadata = $existingPlan->metadata ?? [];
                                        $metadata['stripe_product_id'] = $product->id;
                                        $metadata['stripe_price_id'] = $price->id;
                                        $metadata['stripe_account_id'] = $account->id;
                                        $existingPlan->update(['metadata' => $metadata]);
                                        $synced++;
                                    }
                                } elseif ($data['create_new_plans']) {
                                    $this->createPlanFromStripeData($product, $price, $account);
                                    $created++;
                                }
                            }
                        }
                        
                        Notification::make()
                            ->title('Import from Stripe Complete')
                            ->body("Created: {$created}, Updated: {$updated}, Synced: {$synced}")
                            ->success()
                            ->send();
                            
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Import Failed')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            
            Action::make('syncPlansAcrossAccounts')
                ->label('Sync Plans Across All Accounts')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Sync Plans Across All Stripe Accounts')
                ->modalDescription('This will ensure all plans are available in all Stripe accounts. Missing products and prices will be created automatically.')
                ->form([
                    Forms\Components\Toggle::make('dry_run')
                        ->label('Preview Only (Dry Run)')
                        ->helperText('Enable to see what would be synced without making actual changes')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    try {
                        $isDryRun = $data['dry_run'] ?? true;
                        
                        // Run the sync command
                        $command = $isDryRun ? 'stripe:sync-plans --dry-run' : 'stripe:sync-plans';
                        $output = \Artisan::call($command);
                        
                        // Get the command output
                        $commandOutput = \Artisan::output();
                        
                        $title = $isDryRun ? 'Sync Preview Complete' : 'Plans Synced Successfully';
                        $body = $isDryRun 
                            ? 'Preview completed. Check the results and run without dry-run to apply changes.'
                            : 'All plans have been synced across all Stripe accounts.';
                        
                        Notification::make()
                            ->title($title)
                            ->body($body)
                            ->success()
                            ->send();
                            
                        // Log the output for admin reference
                        \Log::info('Stripe Plans Sync Command Output', [
                            'dry_run' => $isDryRun,
                            'output' => $commandOutput
                        ]);
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync Failed')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                        
                        \Log::error('Stripe Plans Sync Failed', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }),
            
            Action::make('syncAllProducts')
                ->label('Create All in Stripe (Legacy)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $plans = Plan::whereNull('metadata->stripe_product_id')->get();
                    $account = PaymentAccount::whereHas('gateway', function($q) {
                        $q->where('name', 'stripe');
                    })->first();
                    
                    if (!$account) {
                        Notification::make()
                            ->title('No Stripe Account Found')
                            ->body('Please configure a Stripe payment account first')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    $created = 0;
                    $errors = 0;
                    
                    foreach ($plans as $plan) {
                        try {
                            $stripeService = app(StripeSubscriptionService::class);
                            $result = $stripeService->createProductAndPrice($plan, $account);
                            
                            $metadata = $plan->metadata ?? [];
                            $metadata['stripe_product_id'] = $result['product']->id;
                            $metadata['stripe_price_id'] = $result['price']->id;
                            $metadata['stripe_account_id'] = $account->id;
                            
                            $plan->update(['metadata' => $metadata]);
                            $created++;
                            
                        } catch (\Exception $e) {
                            $errors++;
                            \Log::error('Failed to sync plan to Stripe', [
                                'plan_id' => $plan->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    
                    Notification::make()
                        ->title('Sync Complete')
                        ->body("Created {$created} products, {$errors} errors")
                        ->success()
                        ->send();
                }),
        ];
    }
    
    public function getPlansProperty()
    {
        $stripeAccounts = PaymentAccount::whereHas('gateway', function($q) {
            $q->where('name', 'stripe');
        })->where('is_active', true)->get();
        
        return Plan::with(['generatedLinks'])->get()->map(function ($plan) use ($stripeAccounts) {
            $metadata = $plan->metadata ?? [];
            $stripeProducts = $metadata['stripe_products'] ?? [];
            
            // Count how many accounts have this plan
            $accountsWithPlan = 0;
            $accountsSyncStatus = [];
            
            foreach ($stripeAccounts as $account) {
                $hasProduct = isset($stripeProducts[$account->id]);
                $accountsSyncStatus[$account->id] = [
                    'name' => $account->name,
                    'synced' => $hasProduct,
                    'product_id' => $hasProduct ? $stripeProducts[$account->id]['product_id'] : null,
                    'price_id' => $hasProduct ? $stripeProducts[$account->id]['price_id'] : null,
                ];
                if ($hasProduct) $accountsWithPlan++;
            }
            
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'duration_days' => $plan->duration_days,
                'stripe_product_id' => $metadata['stripe_product_id'] ?? null,
                'stripe_price_id' => $metadata['stripe_price_id'] ?? null,
                'recurring' => $metadata['recurring'] ?? false,
                'is_synced' => isset($metadata['stripe_product_id']),
                'links_count' => $plan->generatedLinks->count(),
                'accounts_synced' => $accountsWithPlan,
                'total_accounts' => $stripeAccounts->count(),
                'sync_status' => $accountsSyncStatus,
                'fully_synced' => $accountsWithPlan === $stripeAccounts->count(),
            ];
        });
    }
    
    public function getStripeAccountsProperty()
    {
        return PaymentAccount::whereHas('gateway', function($q) {
                $q->where('name', 'stripe');
            })
            ->get()
            ->map(function ($account) {
                $credentials = $account->credentials ?? [];
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'status' => $account->status,
                    'has_credentials' => isset($credentials['secret_key'], $credentials['publishable_key']),
                    'total_transactions' => $account->total_transactions,
                    'success_rate' => $account->total_transactions > 0 
                        ? round(($account->successful_transactions / $account->total_transactions) * 100, 1) 
                        : 0,
                ];
            });
    }
    
    private function createPlanFromStripeData($product, $price, $account)
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
    
    private function updatePlanFromStripeData($plan, $product, $price, $account)
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