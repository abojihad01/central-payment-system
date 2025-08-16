<?php

namespace App\Filament\Widgets;

use App\Models\FraudAlert;
use App\Models\Payment;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FraudDetectionStats extends BaseWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        // Fraud alerts
        $alertsToday = FraudAlert::whereDate('created_at', $today)->count();
        $alertsThisWeek = FraudAlert::where('created_at', '>=', $thisWeek)->count();
        $alertsThisMonth = FraudAlert::where('created_at', '>=', $thisMonth)->count();

        // High risk transactions (check if column exists)
        try {
            $highRiskTransactions = Payment::where('risk_score', '>=', 70)
                ->whereDate('created_at', $today)
                ->count();
        } catch (\Exception $e) {
            $highRiskTransactions = 0; // Default if column doesn't exist
        }

        // Blocked transactions (check if status column supports 'blocked')
        try {
            $blockedTransactions = Payment::where('status', 'blocked')
                ->whereDate('created_at', $today)
                ->count();
        } catch (\Exception $e) {
            $blockedTransactions = 0; // Default if status doesn't support 'blocked'
        }

        // False positive rate (approximate)
        $totalAlerts = FraudAlert::where('created_at', '>=', $thisMonth)->count();
        $falsePositives = FraudAlert::where('created_at', '>=', $thisMonth)
            ->where('status', 'false_positive')
            ->count();

        $falsePositiveRate = $totalAlerts > 0 ? round(($falsePositives / $totalAlerts) * 100, 1) : 0;

        // Success rate (legitimate transactions processed)
        $totalTransactions = Payment::whereDate('created_at', $today)->count();
        $successfulTransactions = Payment::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->count();

        $successRate = $totalTransactions > 0 ? round(($successfulTransactions / $totalTransactions) * 100, 1) : 0;

        return [
            Stat::make('Fraud Alerts Today', number_format($alertsToday))
                ->description($alertsThisWeek . ' this week, ' . $alertsThisMonth . ' this month')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($alertsToday > 20 ? 'danger' : ($alertsToday > 10 ? 'warning' : 'success')),

            Stat::make('High Risk Transactions', number_format($highRiskTransactions))
                ->description('Risk score ≥ 70 today')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($highRiskTransactions > 50 ? 'danger' : ($highRiskTransactions > 20 ? 'warning' : 'success')),

            Stat::make('Blocked Transactions', number_format($blockedTransactions))
                ->description('Automatically blocked today')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color($blockedTransactions > 10 ? 'warning' : 'primary'),

            Stat::make('False Positive Rate', $falsePositiveRate . '%')
                ->description('This month')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($falsePositiveRate > 15 ? 'danger' : ($falsePositiveRate > 10 ? 'warning' : 'success')),

            Stat::make('Transaction Success Rate', $successRate . '%')
                ->description('Legitimate transactions processed today')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($successRate >= 95 ? 'success' : ($successRate >= 90 ? 'warning' : 'danger')),
        ];
    }

    protected function getPollingInterval(): ?string
    {
        return '60s';
    }
}