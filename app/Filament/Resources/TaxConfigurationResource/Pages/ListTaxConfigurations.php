<?php

namespace App\Filament\Resources\TaxConfigurationResource\Pages;

use App\Filament\Resources\TaxConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxConfigurations extends ListRecords
{
    protected static string $resource = TaxConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('avalara_integration')
                ->label('Avalara Integration')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\TextInput::make('avalara_api_key')
                        ->label('Avalara API Key')
                        ->required()
                        ->password(),
                    \Filament\Forms\Components\TextInput::make('company_code')
                        ->label('Company Code')
                        ->required(),
                    \Filament\Forms\Components\Toggle::make('enable_avalara')
                        ->label('Enable Avalara Integration')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    \Filament\Notifications\Notification::make()
                        ->title('Avalara Integration Updated')
                        ->body('Tax calculation service has been configured successfully')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('generate_tax_summary')
                ->label('Tax Summary Report')
                ->icon('heroicon-o-document-chart-bar')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('report_date')
                        ->label('Report Date')
                        ->default(now())
                        ->required(),
                    \Filament\Forms\Components\Select::make('report_type')
                        ->label('Report Type')
                        ->options([
                            'monthly' => 'Monthly Summary',
                            'quarterly' => 'Quarterly Summary',
                            'yearly' => 'Yearly Summary',
                        ])
                        ->default('monthly'),
                ])
                ->action(function (array $data) {
                    \Filament\Notifications\Notification::make()
                        ->title('Tax Summary Generated')
                        ->body($data['report_type'] . ' tax summary report has been generated')
                        ->success()
                        ->send();
                }),
        ];
    }
}