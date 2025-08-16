<?php

namespace App\Filament\Resources\PaymentAccountSelectionResource\Widgets;

use App\Models\PaymentAccountSelection;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AccountSelectionStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get stats for the last 24 hours
        $last24Hours = PaymentAccountSelection::where('created_at', '>=', now()->subDay());
        
        // Total selections in last 24 hours
        $totalSelections = $last24Hours->count();
        
        // Average selection time
        $avgSelectionTime = $last24Hours->avg('selection_time_ms');
        
        // Fallback rate
        $fallbackCount = $last24Hours->where('was_fallback', true)->count();
        $fallbackRate = $totalSelections > 0 ? ($fallbackCount / $totalSelections) * 100 : 0;
        
        // Most used strategy
        $strategyStats = $last24Hours
            ->select('selection_method', DB::raw('count(*) as count'))
            ->groupBy('selection_method')
            ->orderBy('count', 'desc')
            ->first();
        
        $mostUsedStrategy = $strategyStats ? ucfirst(str_replace('_', ' ', $strategyStats->selection_method)) : 'None';
        
        // Gateway distribution
        $gatewayStats = $last24Hours
            ->select('gateway_name', DB::raw('count(*) as count'))
            ->groupBy('gateway_name')
            ->get()
            ->mapWithKeys(fn($stat) => [$stat->gateway_name => $stat->count]);
        
        return [
            Stat::make('Total Selections (24h)', number_format($totalSelections))
                ->description('Account selections in last 24 hours')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            Stat::make('Avg Selection Time', $avgSelectionTime ? number_format($avgSelectionTime, 2) . ' ms' : 'N/A')
                ->description('Average time to select account')
                ->descriptionIcon('heroicon-m-clock')
                ->color($avgSelectionTime && $avgSelectionTime < 50 ? 'success' : 'warning'),

            Stat::make('Fallback Rate', number_format($fallbackRate, 1) . '%')
                ->description($fallbackCount . ' fallback selections')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($fallbackRate < 5 ? 'success' : ($fallbackRate < 15 ? 'warning' : 'danger')),

            Stat::make('Top Strategy', $mostUsedStrategy)
                ->description('Most used selection method')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),

            Stat::make('Stripe Selections', number_format($gatewayStats['stripe'] ?? 0))
                ->description('Stripe gateway usage')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),

            Stat::make('PayPal Selections', number_format($gatewayStats['paypal'] ?? 0))
                ->description('PayPal gateway usage')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
        ];
    }

    protected static ?string $pollingInterval = '30s';

    public function getDescription(): ?string
    {
        return 'Real-time statistics for payment account selection behavior';
    }
}