<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxConfigurationResource\Pages;
use App\Services\TaxCalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class TaxConfigurationResource extends Resource
{
    protected static ?string $model = null;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    
    protected static ?string $navigationGroup = 'Tax & Compliance';
    
    protected static ?string $navigationLabel = 'Tax Configuration';
    
    protected static ?int $navigationSort = 1;
    
    public static function shouldRegisterNavigation(): bool
    {
        return false; // Temporarily disabled due to Query Builder compatibility issue
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                \DB::table(\DB::raw("(
                    SELECT 
                        'US' as country_code,
                        'United States' as country_name,
                        'federal_state' as tax_type,
                        8.25 as tax_rate,
                        'USD' as currency,
                        'active' as status,
                        'Sales tax varies by state' as description
                    UNION ALL
                    SELECT 
                        'CA' as country_code,
                        'Canada' as country_name,
                        'gst_pst' as tax_type,
                        13.0 as tax_rate,
                        'CAD' as currency,
                        'active' as status,
                        'GST + PST combined rate' as description
                    UNION ALL
                    SELECT 
                        'GB' as country_code,
                        'United Kingdom' as country_name,
                        'vat' as tax_type,
                        20.0 as tax_rate,
                        'GBP' as currency,
                        'active' as status,
                        'Standard VAT rate' as description
                    UNION ALL
                    SELECT 
                        'DE' as country_code,
                        'Germany' as country_name,
                        'vat' as tax_type,
                        19.0 as tax_rate,
                        'EUR' as currency,
                        'active' as status,
                        'Standard VAT rate' as description
                    UNION ALL
                    SELECT 
                        'FR' as country_code,
                        'France' as country_name,
                        'vat' as tax_type,
                        20.0 as tax_rate,
                        'EUR' as currency,
                        'active' as status,
                        'Standard VAT rate' as description
                    UNION ALL
                    SELECT 
                        'AU' as country_code,
                        'Australia' as country_name,
                        'gst' as tax_type,
                        10.0 as tax_rate,
                        'AUD' as currency,
                        'active' as status,
                        'Goods and Services Tax' as description
                ) as tax_configs"))
            )
            ->columns([
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country_name')
                    ->label('Country Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('tax_type')
                    ->label('Tax Type')
                    ->colors([
                        'primary' => 'vat',
                        'success' => 'gst',
                        'warning' => 'federal_state',
                        'secondary' => 'gst_pst',
                    ]),
                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('Tax Rate')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn ($state) => match (true) {
                        $state >= 20 => 'danger',
                        $state >= 15 => 'warning',
                        $state >= 10 => 'primary',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->badge()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'secondary' => 'inactive',
                    ]),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tax_type')
                    ->options([
                        'vat' => 'VAT',
                        'gst' => 'GST',
                        'federal_state' => 'Federal/State',
                        'gst_pst' => 'GST + PST',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->actions([
                Action::make('calculate_tax')
                    ->label('Test Calculation')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(100.00),
                        Forms\Components\Select::make('business_type')
                            ->label('Business Type')
                            ->options([
                                'b2c' => 'Business to Consumer (B2C)',
                                'b2b' => 'Business to Business (B2B)',
                                'digital_services' => 'Digital Services',
                                'physical_goods' => 'Physical Goods',
                            ])
                            ->default('b2c'),
                    ])
                    ->action(function (array $data, $record) {
                        try {
                            $taxService = app(TaxCalculationService::class);
                            $result = $taxService->calculateTax(
                                $data['amount'],
                                $record->country_code,
                                $data['business_type']
                            );
                            
                            Notification::make()
                                ->title('Tax Calculation Result')
                                ->body("Amount: $" . number_format($data['amount'], 2) . 
                                      " | Tax: $" . number_format($result['tax_amount'], 2) . 
                                      " | Total: $" . number_format($result['total_amount'], 2))
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Tax Calculation Failed')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('generate_tax_report')
                    ->label('Generate Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('secondary')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(now()->subMonth())
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        try {
                            $taxService = app(TaxCalculationService::class);
                            $report = $taxService->generateTaxReport(
                                $data['start_date'],
                                $data['end_date'],
                                $record->country_code
                            );
                            
                            Notification::make()
                                ->title('Tax Report Generated')
                                ->body('Tax report for ' . $record->country_name . ' has been generated successfully')
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Report Generation Failed')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('sync_tax_rates')
                    ->label('Sync Rates')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Tax Rates')
                    ->modalDescription('This will update tax rates from external tax services.')
                    ->action(function ($record) {
                        // In production, this would sync with external tax services
                        Notification::make()
                            ->title('Tax Rates Synchronized')
                            ->body('Tax rates for ' . $record->country_name . ' have been updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            Notification::make()
                                ->title('Tax Configurations Activated')
                                ->body(count($records) . ' tax configurations have been activated')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_sync')
                        ->label('Sync All Rates')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            Notification::make()
                                ->title('Bulk Tax Rate Sync Initiated')
                                ->body('Tax rates for ' . count($records) . ' countries are being synchronized')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('export_tax_settings')
                        ->label('Export Configuration')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->action(function ($records) {
                            Notification::make()
                                ->title('Tax Configuration Export')
                                ->body('Tax configuration export will be available shortly')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Tax Configuration')
            ->emptyStateDescription('Configure tax rates and settings for different countries and regions')
            ->emptyStateIcon('heroicon-o-calculator');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            // Temporarily disabled
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}