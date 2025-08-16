<?php

namespace App\Filament\Resources\BusinessReportResource\Pages;

use App\Filament\Resources\BusinessReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBusinessReports extends ListRecords
{
    protected static string $resource = BusinessReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analytics_dashboard')
                ->label('Analytics Dashboard')
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->url('/admin')
                ->openUrlInNewTab(),
                
            Actions\Action::make('export_all_data')
                ->label('Export System Data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Export Complete System Data')
                ->modalDescription('This will export all payment, subscription, and customer data in a comprehensive report.')
                ->action(function () {
                    \Filament\Notifications\Notification::make()
                        ->title('Data export initiated')
                        ->body('Complete system data export will be available shortly.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You could add specific widgets for this page here
        ];
    }
}