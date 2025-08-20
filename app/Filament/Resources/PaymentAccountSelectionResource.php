<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentAccountSelectionResource\Pages;
use App\Models\PaymentAccountSelection;
use App\Models\PaymentGateway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;

class PaymentAccountSelectionResource extends Resource
{
    protected static ?string $model = PaymentAccountSelection::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Account Selection Analytics';

    protected static ?string $modelLabel = 'Account Selection';

    protected static ?string $pluralModelLabel = 'Account Selection Analytics';

    protected static ?string $navigationGroup = 'Payment Analytics';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // This resource is read-only for analytics purposes
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment.reference')
                    ->label('Payment Ref')
                    ->searchable()
                    ->sortable()
                    ->limit(15),

                Tables\Columns\TextColumn::make('gateway_name')
                    ->label('Gateway')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stripe' => 'success',
                        'paypal' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('paymentAccount.name')
                    ->label('Account')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->tooltip(function (PaymentAccountSelection $record): ?string {
                        return $record->paymentAccount?->name;
                    }),

                Tables\Columns\TextColumn::make('selection_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'least_used' => 'success',
                        'round_robin' => 'info',
                        'weighted' => 'warning',
                        'manual' => 'danger',
                        'random' => 'gray',
                        'unused' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('selection_reason')
                    ->label('Reason')
                    ->wrap()
                    ->tooltip(function (PaymentAccountSelection $record): string {
                        return $record->selection_reason;
                    }),

                Tables\Columns\TextColumn::make('selection_priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 1 => 'success',
                        $state <= 3 => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('was_fallback')
                    ->label('Fallback')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('selection_time_ms')
                    ->label('Time (ms)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state < 10 => 'success',
                        $state < 50 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('payment.amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment.status')
                    ->label('Payment Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Selected At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('gateway_name')
                    ->options(function () {
                        return PaymentGateway::pluck('display_name', 'name')->toArray();
                    })
                    ->multiple(),

                SelectFilter::make('selection_method')
                    ->options([
                        'least_used' => 'Least Used',
                        'round_robin' => 'Round Robin',
                        'weighted' => 'Weighted',
                        'manual' => 'Manual',
                        'random' => 'Random',
                        'unused' => 'Unused Account',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('was_fallback')
                    ->label('Was Fallback'),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From Date'),
                        DatePicker::make('created_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

                Filter::make('selection_time')
                    ->form([
                        Forms\Components\TextInput::make('min_time')
                            ->label('Min Selection Time (ms)')
                            ->numeric(),
                        Forms\Components\TextInput::make('max_time')
                            ->label('Max Selection Time (ms)')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_time'],
                                fn (Builder $query, $time): Builder => $query->where('selection_time_ms', '>=', $time),
                            )
                            ->when(
                                $data['max_time'],
                                fn (Builder $query, $time): Builder => $query->where('selection_time_ms', '<=', $time),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // No bulk actions - read-only resource
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Selection Overview')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('payment.reference')
                                    ->label('Payment Reference'),
                                TextEntry::make('gateway_name')
                                    ->label('Gateway')
                                    ->badge(),
                                TextEntry::make('paymentAccount.name')
                                    ->label('Selected Account'),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextEntry::make('selection_method')
                                    ->label('Selection Method')
                                    ->badge(),
                                TextEntry::make('selection_priority')
                                    ->label('Priority')
                                    ->badge(),
                                TextEntry::make('was_fallback')
                                    ->label('Was Fallback')
                                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'warning' : 'success'),
                                TextEntry::make('selection_time_ms')
                                    ->label('Selection Time')
                                    ->suffix(' ms'),
                            ]),

                        TextEntry::make('selection_reason')
                            ->label('Selection Reason')
                            ->columnSpanFull(),
                    ]),

                Section::make('Selection Criteria')
                    ->schema([
                        TextEntry::make('selection_criteria')
                            ->label('Criteria Used')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return 'No criteria data';
                                if (!is_array($state)) return 'Invalid data format';
                                
                                try {
                                    $items = [];
                                    foreach ($state as $key => $value) {
                                        $displayValue = is_array($value) ? json_encode($value) : (string)$value;
                                        $items[] = "<strong>{$key}:</strong> {$displayValue}";
                                    }
                                    return implode('<br>', $items);
                                } catch (\Exception $e) {
                                    return 'Error displaying criteria: ' . $e->getMessage();
                                }
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Available Accounts at Time of Selection')
                    ->schema([
                        TextEntry::make('available_accounts')
                            ->label('Available Accounts')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return 'No accounts data';
                                if (!is_array($state)) return 'Invalid data format';
                                
                                try {
                                    $output = '';
                                    foreach ($state as $account) {
                                        if (!is_array($account)) continue;
                                        
                                        $name = $account['name'] ?? 'Unknown';
                                        $accountId = $account['account_id'] ?? 'Unknown';
                                        $successTx = $account['successful_transactions'] ?? 0;
                                        $failedTx = $account['failed_transactions'] ?? 0;
                                        $totalAmount = $account['total_amount'] ?? 0;
                                        $lastUsed = $account['last_used_at'] ?? 'Never';
                                        
                                        $output .= "<div style='margin-bottom: 10px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;'>";
                                        $output .= "<strong>• {$name}</strong> ({$accountId})<br>";
                                        $output .= "Success: {$successTx}, Failed: {$failedTx}<br>";
                                        $output .= "Total Amount: " . number_format((float)$totalAmount, 2) . "<br>";
                                        $output .= "Last Used: {$lastUsed}";
                                        $output .= "</div>";
                                    }
                                    return $output ?: 'No valid account data';
                                } catch (\Exception $e) {
                                    return 'Error displaying accounts: ' . $e->getMessage();
                                }
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Selected Account Statistics')
                    ->schema([
                        TextEntry::make('account_stats')
                            ->label('Account Stats at Selection Time')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return 'No stats data';
                                if (!is_array($state)) return 'Invalid data format';
                                
                                try {
                                    $output = '';
                                    foreach ($state as $section => $data) {
                                        $output .= "<h4 style='margin-bottom: 8px; color: #374151;'>{$section}:</h4>";
                                        if (is_array($data)) {
                                            $output .= "<div style='margin-left: 16px; margin-bottom: 12px;'>";
                                            foreach ($data as $key => $value) {
                                                $displayValue = is_array($value) ? json_encode($value) : (string)$value;
                                                $output .= "<strong>{$key}:</strong> {$displayValue}<br>";
                                            }
                                            $output .= "</div>";
                                        } else {
                                            $output .= "<div style='margin-left: 16px; margin-bottom: 12px;'>" . (string)$data . "</div>";
                                        }
                                    }
                                    return $output ?: 'No valid stats data';
                                } catch (\Exception $e) {
                                    return 'Error displaying stats: ' . $e->getMessage();
                                }
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Payment Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('payment.amount')
                                    ->label('Amount')
                                    ->money('USD'),
                                TextEntry::make('payment.currency')
                                    ->label('Currency'),
                                TextEntry::make('payment.status')
                                    ->label('Payment Status')
                                    ->badge(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Selection Time')
                                    ->dateTime(),
                                TextEntry::make('payment.created_at')
                                    ->label('Payment Created')
                                    ->dateTime(),
                            ]),
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
            'index' => Pages\ListPaymentAccountSelections::route('/'),
            'view' => Pages\ViewPaymentAccountSelection::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Read-only resource
    }

    public static function canEdit($record): bool
    {
        return false; // Read-only resource
    }

    public static function canDelete($record): bool
    {
        return false; // Read-only resource
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}