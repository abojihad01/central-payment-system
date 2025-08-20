<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'مخطط الإيرادات اليومية (آخر 30 يوم)';
    protected static ?int $sort = 3;
    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        // Get last 30 days revenue data
        $data = [];
        $labels = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $revenue = Payment::where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('amount');
            
            $labels[] = $date->format('M d');
            $data[] = $revenue;
        }

        return [
            'datasets' => [
                [
                    'label' => 'الإيرادات اليومية',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) { return '$' + value.toLocaleString(); }",
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) { return context.dataset.label + ': $' + context.parsed.y.toLocaleString(); }",
                    ],
                ],
            ],
        ];
    }
}