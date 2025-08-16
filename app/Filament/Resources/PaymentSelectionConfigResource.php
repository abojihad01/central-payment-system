<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentSelectionConfigResource\Pages;
use App\Models\PaymentSelectionConfig;
use App\Models\PaymentGateway;
use App\Models\PaymentAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Tables\Filters\SelectFilter;

class PaymentSelectionConfigResource extends Resource
{
    protected static ?string $model = PaymentSelectionConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationLabel = 'Payment Selection Config';

    protected static ?string $modelLabel = 'Payment Selection Configuration';

    protected static ?string $pluralModelLabel = 'Payment Selection Configurations';

    protected static ?string $navigationGroup = 'Payment Management';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Configuration')
                    ->tabs([
                        Tabs\Tab::make('Basic Settings')
                            ->schema([
                                Section::make('Configuration Details')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Configuration Name')
                                                    ->required()
                                                    ->unique(ignoreRecord: true)
                                                    ->placeholder('e.g., global, stripe, paypal')
                                                    ->helperText('Use "global" for default settings, or specific gateway names'),

                                                Forms\Components\Select::make('selection_strategy')
                                                    ->label('Selection Strategy')
                                                    ->required()
                                                    ->options(PaymentSelectionConfig::getAvailableStrategies())
                                                    ->default('least_used')
                                                    ->reactive()
                                                    ->helperText('Strategy used to select payment accounts'),
                                            ]),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')
                                            ->rows(3)
                                            ->placeholder('Describe when and how this configuration should be used'),

                                        Grid::make(3)
                                            ->schema([
                                                Forms\Components\Toggle::make('is_active')
                                                    ->label('Active')
                                                    ->default(true)
                                                    ->helperText('Enable this configuration'),

                                                Forms\Components\Toggle::make('enable_fallback')
                                                    ->label('Enable Fallback')
                                                    ->default(true)
                                                    ->helperText('Try alternative accounts if primary fails'),

                                                Forms\Components\Toggle::make('enable_load_balancing')
                                                    ->label('Enable Load Balancing')
                                                    ->default(true)
                                                    ->helperText('Distribute load across accounts'),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Strategy Configuration')
                            ->schema([
                                Section::make('Account Weights')
                                    ->description('Configure weights for weighted distribution strategy')
                                    ->schema([
                                        Forms\Components\Repeater::make('account_weights_repeater')
                                            ->label('Account Weights')
                                            ->schema([
                                                Forms\Components\Select::make('account_id')
                                                    ->label('Payment Account')
                                                    ->options(function () {
                                                        return PaymentAccount::with('gateway')
                                                            ->get()
                                                            ->mapWithKeys(function ($account) {
                                                                return [$account->account_id => "{$account->gateway->display_name} - {$account->name} ({$account->account_id})"];
                                                            });
                                                    })
                                                    ->searchable()
                                                    ->required(),

                                                Forms\Components\TextInput::make('weight')
                                                    ->label('Weight')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->required(),
                                            ])
                                            ->columns(2)
                                            ->visible(fn (Forms\Get $get) => $get('selection_strategy') === 'weighted')
                                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                $weights = [];
                                                foreach ($state ?? [] as $item) {
                                                    if (!empty($item['account_id']) && !empty($item['weight'])) {
                                                        $weights[$item['account_id']] = (int) $item['weight'];
                                                    }
                                                }
                                                $set('account_weights', $weights);
                                            }),

                                        Forms\Components\Hidden::make('account_weights'),
                                    ]),

                                Section::make('Account Priorities')
                                    ->description('Configure priority order for manual selection strategy')
                                    ->schema([
                                        Forms\Components\Repeater::make('account_priorities_repeater')
                                            ->label('Account Priorities')
                                            ->schema([
                                                Forms\Components\Select::make('account_id')
                                                    ->label('Payment Account')
                                                    ->options(function () {
                                                        return PaymentAccount::with('gateway')
                                                            ->get()
                                                            ->mapWithKeys(function ($account) {
                                                                return [$account->account_id => "{$account->gateway->display_name} - {$account->name} ({$account->account_id})"];
                                                            });
                                                    })
                                                    ->searchable()
                                                    ->required(),

                                                Forms\Components\TextInput::make('priority')
                                                    ->label('Priority')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->maxValue(999)
                                                    ->required()
                                                    ->helperText('Lower number = higher priority'),
                                            ])
                                            ->columns(2)
                                            ->visible(fn (Forms\Get $get) => $get('selection_strategy') === 'manual')
                                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                $priorities = [];
                                                foreach ($state ?? [] as $item) {
                                                    if (!empty($item['account_id']) && !empty($item['priority'])) {
                                                        $priorities[$item['account_id']] = (int) $item['priority'];
                                                    }
                                                }
                                                $set('account_priorities', $priorities);
                                            }),

                                        Forms\Components\Hidden::make('account_priorities'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Advanced Settings')
                            ->schema([
                                Section::make('Failure Handling')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('max_fallback_attempts')
                                                    ->label('Max Fallback Attempts')
                                                    ->numeric()
                                                    ->default(3)
                                                    ->minValue(1)
                                                    ->maxValue(10)
                                                    ->helperText('Maximum attempts before giving up'),

                                                Forms\Components\TextInput::make('failed_account_cooldown_minutes')
                                                    ->label('Failed Account Cooldown (minutes)')
                                                    ->numeric()
                                                    ->default(60)
                                                    ->minValue(0)
                                                    ->helperText('Time before retrying a failed account'),
                                            ]),

                                        Forms\Components\Toggle::make('exclude_failed_accounts')
                                            ->label('Exclude Failed Accounts')
                                            ->helperText('Temporarily exclude accounts that recently failed'),
                                    ]),

                                Section::make('Load Balancing')
                                    ->schema([
                                        Forms\Components\TextInput::make('max_account_load_percentage')
                                            ->label('Max Account Load (%)')
                                            ->numeric()
                                            ->default(70)
                                            ->minValue(10)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->helperText('Maximum percentage of transactions for one account'),
                                    ]),

                                Section::make('Strategy Configuration JSON')
                                    ->description('Advanced configuration for specific strategies')
                                    ->schema([
                                        Forms\Components\Textarea::make('strategy_config')
                                            ->label('Strategy Config (JSON)')
                                            ->rows(5)
                                            ->placeholder('{"key": "value"}')
                                            ->helperText('Additional JSON configuration for the selected strategy')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => $state === 'global' ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('selection_strategy')
                    ->label('Strategy')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'least_used' => 'success',
                        'round_robin' => 'info',
                        'weighted' => 'warning',
                        'manual' => 'danger',
                        'random' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('enable_fallback')
                    ->label('Fallback')
                    ->boolean(),

                Tables\Columns\IconColumn::make('enable_load_balancing')
                    ->label('Load Balance')
                    ->boolean(),

                Tables\Columns\TextColumn::make('max_fallback_attempts')
                    ->label('Max Attempts')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('max_account_load_percentage')
                    ->label('Max Load %')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('selection_strategy')
                    ->options(PaymentSelectionConfig::getAvailableStrategies()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\TernaryFilter::make('enable_fallback')
                    ->label('Fallback Enabled'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListPaymentSelectionConfigs::route('/'),
            'create' => Pages\CreatePaymentSelectionConfig::route('/create'),
            'view' => Pages\ViewPaymentSelectionConfig::route('/{record}'),
            'edit' => Pages\EditPaymentSelectionConfig::route('/{record}/edit'),
        ];
    }
}