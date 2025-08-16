<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\BotProtectionSettings;
use App\Models\BotDetection;
use Filament\Support\Enums\IconPosition;

class BotProtectionStatusWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $enabled = BotProtectionSettings::get('protection_enabled', true);
        $todayDetections = BotDetection::whereDate('detected_at', today())->count();
        $weekDetections = BotDetection::where('detected_at', '>=', now()->subWeek())->count();
        $uniqueIpsToday = BotDetection::whereDate('detected_at', today())
            ->distinct('ip_address')
            ->count('ip_address');

        return [
            Stat::make('Bot Protection Status', $enabled ? 'ENABLED' : 'DISABLED')
                ->description($enabled ? 'System is protected' : 'System is vulnerable')
                ->descriptionIcon($enabled ? 'heroicon-m-shield-check' : 'heroicon-m-shield-exclamation')
                ->color($enabled ? 'success' : 'danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ])
                ->url(route('filament.admin.resources.bot-protection-settings.index')),

            Stat::make('Bot Detections Today', $todayDetections)
                ->description('Threats blocked today')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($todayDetections > 10 ? 'warning' : ($todayDetections > 0 ? 'info' : 'success'))
                ->url(route('filament.admin.resources.bot-detections.index')),

            Stat::make('Weekly Detections', $weekDetections)
                ->description('Past 7 days')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info')
                ->url(route('filament.admin.resources.bot-detections.index')),

            Stat::make('Unique IPs Today', $uniqueIpsToday)
                ->description('Different attackers')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($uniqueIpsToday > 5 ? 'warning' : 'info')
                ->url(route('filament.admin.resources.bot-detections.index')),
        ];
    }

    public function getDisplayName(): string
    {
        return 'Bot Protection Overview';
    }

    protected function getColumns(): int
    {
        return 4;
    }
}