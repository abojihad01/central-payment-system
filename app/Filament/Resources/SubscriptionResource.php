<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Website;
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
use Carbon\Carbon;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationGroup = 'Subscription Management';
    
    protected static ?string $recordTitleAttribute = 'subscription_id';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('subscription_id')
                            ->label('Subscription ID')
                            ->required()
                            ->maxLength(36)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'trial' => 'Trial',
                                'past_due' => 'Past Due',
                                'cancelled' => 'Cancelled',
                                'paused' => 'Paused',
                                'pending_cancellation' => 'Pending Cancellation',
                                'expired' => 'Expired',
                            ])
                            ->default('active'),
                        Forms\Components\TextInput::make('gateway_subscription_id')
                            ->label('Gateway Subscription ID')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Customer & Plan')
                    ->schema([
                        Forms\Components\TextInput::make('customer_email')
                            ->label('Customer Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Customer Phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Select::make('website_id')
                            ->label('Website')
                            ->relationship('website', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('plan_id')
                            ->label('Plan')
                            ->relationship('plan', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('payment_id')
                            ->label('Payment Method')
                            ->relationship('payment', 'id')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Billing Information')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start Date')
                            ->required(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiry Date'),
                        Forms\Components\DateTimePicker::make('next_billing_date')
                            ->label('Next Billing Date'),
                        Forms\Components\DateTimePicker::make('last_billing_date')
                            ->label('Last Billing Date'),
                        Forms\Components\TextInput::make('billing_cycle_count')
                            ->label('Billing Cycle Count')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('failed_payment_count')
                            ->label('Failed Payment Count')
                            ->numeric()
                            ->default(0),
                    ])->columns(3),

                Forms\Components\Section::make('Trial Information')
                    ->schema([
                        Forms\Components\Toggle::make('is_trial')
                            ->label('Is Trial Period')
                            ->default(false),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Trial Ends At'),
                    ])->columns(2),

                Forms\Components\Section::make('Cancellation & Pausing')
                    ->schema([
                        Forms\Components\DateTimePicker::make('cancelled_at')
                            ->label('Cancelled At'),
                        Forms\Components\TextInput::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('cancel_at_period_end')
                            ->label('Cancel at Period End')
                            ->default(false),
                        Forms\Components\DateTimePicker::make('grace_period_ends_at')
                            ->label('Grace Period Ends At'),
                        Forms\Components\DateTimePicker::make('paused_at')
                            ->label('Paused At'),
                        Forms\Components\TextInput::make('pause_reason')
                            ->label('Pause Reason')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Additional Data')
                    ->schema([
                        Forms\Components\Textarea::make('plan_changes_history')
                            ->label('Plan Changes History')
                            ->rows(3),
                        Forms\Components\Textarea::make('plan_data')
                            ->label('Plan Data')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscription_id')
                    ->label('Subscription ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'primary' => 'trial',
                        'warning' => ['past_due', 'paused'],
                        'danger' => ['cancelled', 'expired'],
                        'secondary' => 'pending_cancellation',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('website.name')
                    ->label('Website')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_trial')
                    ->label('Trial')
                    ->boolean(),
                Tables\Columns\TextColumn::make('next_billing_date')
                    ->label('Next Billing')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('billing_cycle_count')
                    ->label('Cycles')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('failed_payment_count')
                    ->label('Failed Payments')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Start Date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'trial' => 'Trial',
                        'past_due' => 'Past Due',
                        'cancelled' => 'Cancelled',
                        'paused' => 'Paused',
                        'pending_cancellation' => 'Pending Cancellation',
                        'expired' => 'Expired',
                    ]),
                SelectFilter::make('is_trial')
                    ->label('Trial Status')
                    ->options([
                        1 => 'Trial',
                        0 => 'Regular',
                    ]),
                Filter::make('next_billing_date')
                    ->form([
                        Forms\Components\DatePicker::make('billing_from'),
                        Forms\Components\DatePicker::make('billing_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['billing_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('next_billing_date', '>=', $date),
                            )
                            ->when(
                                $data['billing_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('next_billing_date', '<=', $date),
                            );
                    }),
                SelectFilter::make('website')
                    ->relationship('website', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('plan')
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if ($record->pause('Admin action')) {
                            Notification::make()
                                ->title('Subscription paused successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to pause subscription')
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => $record->status === 'active'),
                Action::make('resume')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if ($record->resume()) {
                            Notification::make()
                                ->title('Subscription resumed successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to resume subscription')
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => $record->status === 'paused'),
                Action::make('cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required(),
                        Forms\Components\Toggle::make('at_period_end')
                            ->label('Cancel at period end')
                            ->default(true),
                    ])
                    ->action(function ($record, array $data) {
                        if ($record->cancel($data['reason'], $data['at_period_end'])) {
                            Notification::make()
                                ->title('Subscription cancelled successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to cancel subscription')
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => !in_array($record->status, ['cancelled', 'expired'])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
