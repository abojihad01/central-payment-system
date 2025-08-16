<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class SubscriptionMetrics extends ChartWidget
{
    protected static ?string $heading = 'Subscription Status Distribution';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $subscriptions = Subscription::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statusColors = [
            'active' => '#10b981',
            'trial' => '#3b82f6',
            'past_due' => '#f59e0b',
            'cancelled' => '#ef4444',
            'paused' => '#8b5cf6',
            'expired' => '#6b7280',
            'pending_cancellation' => '#f97316',
        ];

        $labels = array_keys($subscriptions);
        $data = array_values($subscriptions);
        $colors = array_map(fn($label) => $statusColors[$label] ?? '#6b7280', $labels);

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_map(fn($label) => ucfirst(str_replace('_', ' ', $label)), $labels),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}