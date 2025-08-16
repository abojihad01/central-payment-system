<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Services\CustomerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationGroup = 'Customer Management';
    
    protected static ?string $recordTitleAttribute = 'email';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\TextInput::make('customer_id')
                            ->label('Customer ID')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(fn () => 'CUS_' . Str::upper(Str::random(10))),
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'blocked' => 'Blocked',
                                'pending' => 'Pending',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active'),
                    ])->columns(2),

                Forms\Components\Section::make('Personal Details')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('country_code')
                            ->label('Country Code')
                            ->maxLength(2)
                            ->placeholder('US'),
                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('address')
                            ->label('Address')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('postal_code')
                            ->label('Postal Code')
                            ->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('Risk & Analytics')
                    ->schema([
                        Forms\Components\TextInput::make('risk_score')
                            ->label('Risk Score (0-100)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->disabled(),
                        Forms\Components\Select::make('risk_level')
                            ->label('Risk Level')
                            ->options([
                                'low' => 'Low Risk',
                                'medium' => 'Medium Risk',
                                'high' => 'High Risk',
                                'critical' => 'Critical Risk',
                            ])
                            ->default('low')
                            ->disabled(),
                        Forms\Components\TextInput::make('lifetime_value')
                            ->label('Lifetime Value')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Subscription Statistics')
                    ->schema([
                        Forms\Components\TextInput::make('total_subscriptions')
                            ->label('Total Subscriptions')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('active_subscriptions')
                            ->label('Active Subscriptions')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('total_spent')
                            ->label('Total Spent')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->disabled(),
                        Forms\Components\TextInput::make('successful_payments')
                            ->label('Successful Payments')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('failed_payments')
                            ->label('Failed Payments')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('chargebacks')
                            ->label('Chargebacks')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('refunds')
                            ->label('Refunds')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ])->columns(4),

                Forms\Components\Section::make('Activity & Preferences')
                    ->schema([
                        Forms\Components\DateTimePicker::make('first_purchase_at')
                            ->label('First Purchase')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('last_purchase_at')
                            ->label('Last Purchase')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('last_login_at')
                            ->label('Last Login')
                            ->disabled(),
                        Forms\Components\TextInput::make('acquisition_source')
                            ->label('Acquisition Source')
                            ->maxLength(255)
                            ->placeholder('Website, Referral, etc.'),
                        Forms\Components\Toggle::make('marketing_consent')
                            ->label('Marketing Consent')
                            ->default(false),
                        Forms\Components\Toggle::make('email_verified')
                            ->label('Email Verified')
                            ->default(false),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('preferences')
                            ->label('Customer Preferences (JSON)')
                            ->rows(3)
                            ->placeholder('{"currency": "USD", "language": "en"}'),
                        Forms\Components\Textarea::make('payment_methods')
                            ->label('Payment Methods (JSON)')
                            ->rows(3)
                            ->disabled(),
                        Forms\Components\Textarea::make('subscription_history')
                            ->label('Subscription History (JSON)')
                            ->rows(3)
                            ->disabled(),
                        Forms\Components\TagsInput::make('tags')
                            ->label('Customer Tags')
                            ->placeholder('VIP, Early Adopter, etc.'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Admin Notes')
                            ->rows(4)
                            ->placeholder('Internal notes about this customer...'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_id')
                    ->label('Customer ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn ($record) => trim($record->first_name . ' ' . $record->last_name))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'pending',
                        'secondary' => 'inactive',
                        'danger' => ['blocked', 'suspended'],
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('risk_level')
                    ->label('Risk Level')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger' => ['high', 'critical'],
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('risk_score')
                    ->label('Risk Score')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => match (true) {
                        $state >= 70 => 'danger',
                        $state >= 40 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('active_subscriptions')
                    ->label('Active Subs')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'secondary'),
                Tables\Columns\TextColumn::make('lifetime_value')
                    ->label('LTV')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => match (true) {
                        $state >= 1000 => 'success',
                        $state >= 500 => 'primary',
                        $state >= 100 => 'warning',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total Revenue'),
                    ]),
                Tables\Columns\TextColumn::make('successful_payments')
                    ->label('Success')
                    ->numeric()
                    ->sortable()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('failed_payments')
                    ->label('Failed')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 5 ? 'danger' : 'secondary')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('chargebacks')
                    ->label('Chargebacks')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_purchase_at')
                    ->label('Last Purchase')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('email_verified')
                    ->label('Verified')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('acquisition_source')
                    ->label('Source')
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'blocked' => 'Blocked',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                    ]),
                SelectFilter::make('risk_level')
                    ->label('Risk Level')
                    ->options([
                        'low' => 'Low Risk',
                        'medium' => 'Medium Risk',
                        'high' => 'High Risk',
                        'critical' => 'Critical Risk',
                    ]),
                SelectFilter::make('email_verified')
                    ->label('Email Verification')
                    ->options([
                        1 => 'Verified',
                        0 => 'Unverified',
                    ]),
                Filter::make('high_value_customers')
                    ->label('High Value Customers (LTV ≥ $500)')
                    ->query(fn (Builder $query): Builder => $query->where('lifetime_value', '>=', 500)),
                Filter::make('risk_customers')
                    ->label('High Risk Customers')
                    ->query(fn (Builder $query): Builder => $query->whereIn('risk_level', ['high', 'critical'])),
                Filter::make('recent_activity')
                    ->form([
                        Forms\Components\DatePicker::make('active_since')
                            ->label('Active Since'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['active_since'],
                            fn (Builder $query, $date): Builder => $query->where('last_purchase_at', '>=', $date)
                        );
                    }),
            ])
            ->actions([
                Action::make('view_analytics')
                    ->label('Analytics')
                    ->icon('heroicon-o-chart-bar')
                    ->color('primary')
                    ->url(fn ($record) => '#')
                    ->openUrlInNewTab(),
                Action::make('block_customer')
                    ->label('Block')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'blocked']);
                        
                        Notification::make()
                            ->title('Customer blocked successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->status !== 'blocked'),
                Action::make('unblock_customer')
                    ->label('Unblock')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'active']);
                        
                        Notification::make()
                            ->title('Customer unblocked successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->status === 'blocked'),
                Action::make('recalculate_ltv')
                    ->label('Recalculate LTV')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        // Here you would call the CustomerService to recalculate LTV
                        // app(CustomerService::class)->recalculateCustomerMetrics($record->customer_id);
                        
                        Notification::make()
                            ->title('LTV recalculation queued')
                            ->body('Customer metrics will be updated shortly')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_verify_email')
                        ->label('Verify Emails')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'email_verified' => true,
                                    'email_verified_at' => now(),
                                ]);
                            });
                            
                            Notification::make()
                                ->title('Email addresses verified')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_block')
                        ->label('Block Selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['status' => 'blocked']);
                            
                            Notification::make()
                                ->title('Customers blocked successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('export_customers')
                        ->label('Export to CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->action(function ($records) {
                            // Here you would implement CSV export logic
                            Notification::make()
                                ->title('Export initiated')
                                ->body('Customer data export will be available shortly')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
