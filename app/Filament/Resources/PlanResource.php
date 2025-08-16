<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Resources\PlanResource\RelationManagers;
use App\Models\Plan;
use App\Models\PaymentAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    
    protected static ?string $navigationGroup = 'Payment Management';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('website_id')
                            ->relationship('website', 'name')
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if ($state) {
                                    $set('description', "Subscription plan: {$state}");
                                }
                            }),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('features')
                            ->placeholder('Enter features separated by lines or commas')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Pricing & Billing')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->step(0.01),
                        Forms\Components\Select::make('currency')
                            ->options([
                                'USD' => 'US Dollar (USD)',
                                'EUR' => 'Euro (EUR)',
                                'GBP' => 'British Pound (GBP)',
                                'SAR' => 'Saudi Riyal (SAR)',
                                'AED' => 'UAE Dirham (AED)',
                                'EGP' => 'Egyptian Pound (EGP)',
                            ])
                            ->default('USD')
                            ->required(),
                        Forms\Components\Select::make('subscription_type')
                            ->options([
                                'one_time' => 'One-time Payment',
                                'recurring' => 'Recurring Subscription',
                            ])
                            ->default('one_time')
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('duration_days')
                            ->numeric()
                            ->minValue(1)
                            ->hint('Leave empty for lifetime access')
                            ->visible(fn (Forms\Get $get): bool => $get('subscription_type') === 'one_time'),
                    ])->columns(2),

                Forms\Components\Section::make('Recurring Billing Settings')
                    ->schema([
                        Forms\Components\Select::make('billing_interval')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly', 
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                                'yearly' => 'Yearly',
                            ])
                            ->default('monthly')
                            ->required(),
                        Forms\Components\TextInput::make('billing_interval_count')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(999)
                            ->required(),
                        Forms\Components\TextInput::make('trial_period_days')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->hint('0 = No trial period'),
                        Forms\Components\TextInput::make('setup_fee')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('$')
                            ->step(0.01),
                        Forms\Components\TextInput::make('max_billing_cycles')
                            ->numeric()
                            ->minValue(1)
                            ->hint('Leave empty for unlimited billing'),
                        Forms\Components\Toggle::make('prorate_on_change')
                            ->label('Prorate on plan changes')
                            ->default(true),
                    ])
                    ->columns(3)
                    ->visible(fn (Forms\Get $get): bool => $get('subscription_type') === 'recurring'),

                Forms\Components\Section::make('Stripe Integration')
                    ->schema([
                        Forms\Components\Toggle::make('create_stripe_product')
                            ->label('Create Stripe Product')
                            ->hint('Automatically create this plan as a Stripe product/price')
                            ->live()
                            ->default(false),
                        Forms\Components\Select::make('stripe_account_ids')
                            ->label('Stripe Accounts')
                            ->multiple()
                            ->options(function () {
                                return PaymentAccount::whereHas('gateway', function($q) {
                                    $q->where('name', 'stripe');
                                })->where('is_active', true)->pluck('name', 'id');
                            })
                            ->placeholder('Select one or more Stripe accounts')
                            ->visible(fn (Forms\Get $get): bool => $get('create_stripe_product'))
                            ->required(fn (Forms\Get $get): bool => $get('create_stripe_product'))
                            ->hint('The plan will be created as a product in all selected Stripe accounts'),
                        Forms\Components\Placeholder::make('stripe_info')
                            ->label('Stripe Integration Info')
                            ->content('This will create the product and price in Stripe when you save the plan.')
                            ->visible(fn (Forms\Get $get): bool => $get('create_stripe_product')),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money(fn (Plan $record): string => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'recurring' => 'success',
                        'one_time' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('billing_interval_text')
                    ->label('Billing')
                    ->getStateUsing(function (Plan $record): string {
                        if ($record->subscription_type === 'recurring') {
                            $count = $record->billing_interval_count;
                            $interval = $record->billing_interval;
                            
                            if ($count === 1) {
                                return match($interval) {
                                    'daily' => 'Daily',
                                    'weekly' => 'Weekly',
                                    'monthly' => 'Monthly',
                                    'quarterly' => 'Quarterly',
                                    'yearly' => 'Yearly',
                                    default => ucfirst($interval)
                                };
                            }
                            
                            $intervalSingle = match($interval) {
                                'daily' => 'day',
                                'weekly' => 'week', 
                                'monthly' => 'month',
                                'quarterly' => 'quarter',
                                'yearly' => 'year',
                                default => $interval
                            };
                            
                            return "Every {$count} " . str($intervalSingle)->plural($count);
                        }
                        return $record->duration_days ? "{$record->duration_days} days" : 'Lifetime';
                    }),
                Tables\Columns\TextColumn::make('stripe_status')
                    ->label('Stripe')
                    ->getStateUsing(function (Plan $record): string {
                        $metadata = $record->metadata ?? [];
                        
                        if (empty($metadata['stripe_product_id']) && empty($metadata['stripe_products'])) {
                            return 'Not Synced';
                        }
                        
                        if (!empty($metadata['stripe_products'])) {
                            $count = count($metadata['stripe_products']);
                            return "{$count} Account" . ($count > 1 ? 's' : '');
                        }
                        
                        return 'Synced';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === 'Not Synced' => 'gray',
                        str_contains($state, 'Account') => 'success',
                        $state === 'Synced' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('website.name')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('trial_period_days')
                    ->label('Trial Days')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_type')
                    ->options([
                        'one_time' => 'One-time Payment',
                        'recurring' => 'Recurring Subscription',
                    ]),
                Tables\Filters\TernaryFilter::make('stripe_synced')
                    ->label('Stripe Synced')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function($q) {
                            $q->whereNotNull('metadata->stripe_product_id')
                              ->orWhereNotNull('metadata->stripe_products');
                        }),
                        false: fn (Builder $query): Builder => $query->where(function($q) {
                            $q->whereNull('metadata->stripe_product_id')
                              ->whereNull('metadata->stripe_products');
                        }),
                    ),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Plans'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('create_stripe_product')
                    ->label('Create in Stripe')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn (Plan $record): bool => empty($record->metadata['stripe_product_id']) && empty($record->metadata['stripe_products']))
                    ->form([
                        Forms\Components\Select::make('stripe_account_ids')
                            ->label('Select Stripe Accounts')
                            ->multiple()
                            ->options(function () {
                                return PaymentAccount::whereHas('gateway', function($q) {
                                    $q->where('name', 'stripe');
                                })->where('is_active', true)->pluck('name', 'id');
                            })
                            ->placeholder('Select one or more Stripe accounts')
                            ->required()
                            ->hint('The product will be created in all selected accounts'),
                    ])
                    ->action(function (Plan $record, array $data) {
                        $stripeAccountIds = $data['stripe_account_ids'] ?? [];
                        
                        if (empty($stripeAccountIds)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No Accounts Selected')
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
                                $stripeService = app(\App\Services\StripeSubscriptionService::class);
                                $result = $stripeService->createProductAndPrice($record, $account);
                                
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

                        // Update metadata
                        if (!empty($successfulCreations)) {
                            $metadata = $record->metadata ?? [];
                            
                            // Legacy format for single account
                            if (count($successfulCreations) === 1) {
                                $success = $successfulCreations[0];
                                $metadata['stripe_product_id'] = $success['product_id'];
                                $metadata['stripe_price_id'] = $success['price_id'];
                                $metadata['stripe_account_id'] = $success['account_id'];
                            }
                            
                            // Multiple products info
                            $metadata['stripe_products'] = $stripeProducts;
                            $metadata['created_via_table'] = true;
                            
                            $record->update(['metadata' => $metadata]);
                        }

                        // Show results
                        if (!empty($successfulCreations) && empty($failedCreations)) {
                            $accountNames = collect($successfulCreations)->pluck('account_name')->implode(', ');
                            \Filament\Notifications\Notification::make()
                                ->title('Stripe Products Created Successfully')
                                ->body("Products created in accounts: {$accountNames}")
                                ->success()
                                ->send();
                        } elseif (!empty($successfulCreations)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Partial Success')
                                ->body(count($successfulCreations) . " products created, " . count($failedCreations) . " failed")
                                ->warning()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('All Failed')
                                ->body("Failed to create products: " . implode('; ', $failedCreations))
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('view_stripe_products')
                    ->label('View in Stripe')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (Plan $record): bool => !empty($record->metadata['stripe_product_id']) || !empty($record->metadata['stripe_products']))
                    ->action(function (Plan $record) {
                        $metadata = $record->metadata ?? [];
                        $urls = [];
                        
                        // Single product (legacy format)
                        if (!empty($metadata['stripe_product_id'])) {
                            $urls[] = "https://dashboard.stripe.com/products/{$metadata['stripe_product_id']}";
                        }
                        
                        // Multiple products
                        if (!empty($metadata['stripe_products'])) {
                            foreach ($metadata['stripe_products'] as $accountId => $productInfo) {
                                if (!empty($productInfo['product_id'])) {
                                    $urls[] = "https://dashboard.stripe.com/products/{$productInfo['product_id']}";
                                }
                            }
                        }
                        
                        // If only one URL, open directly
                        if (count($urls) === 1) {
                            redirect()->to($urls[0]);
                            return;
                        }
                        
                        // Multiple URLs - show selection
                        $accounts = PaymentAccount::whereIn('id', array_keys($metadata['stripe_products'] ?? []))->pluck('name', 'id');
                        $options = [];
                        
                        foreach ($metadata['stripe_products'] ?? [] as $accountId => $productInfo) {
                            $accountName = $accounts[$accountId] ?? "Account {$accountId}";
                            $options[$productInfo['product_id']] = "{$accountName} - {$productInfo['product_id']}";
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Multiple Stripe Products Found')
                            ->body('This plan has products in ' . count($urls) . ' Stripe accounts. Please visit each manually.')
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
