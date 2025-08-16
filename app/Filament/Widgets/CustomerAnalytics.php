<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Payment;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class CustomerAnalytics extends ChartWidget
{
    protected static ?string $heading = 'Customer Growth & Revenue by Risk Level';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Get the last 12 months of customer growth
        $months = collect(range(11, 0))->map(function ($monthsAgo) {
            $date = Carbon::now()->subMonths($monthsAgo);
            
            $newCustomers = Customer::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            return [
                'month' => $date->format('M Y'),
                'customers' => $newCustomers,
            ];
        });

        // Get revenue by customer risk level
        $riskRevenue = Customer::join('payments', 'customers.email', '=', 'payments.customer_email')
            ->where('payments.status', 'completed')
            ->selectRaw('customers.risk_level, SUM(payments.amount) as total_revenue')
            ->groupBy('customers.risk_level')
            ->pluck('total_revenue', 'risk_level')
            ->toArray();

        return [
            'datasets' => [
                [
                    'type' => 'line',
                    'label' => 'New Customers',
                    'data' => $months->pluck('customers')->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'yAxisID' => 'y',
                    'tension' => 0.4,
                ],
                [
                    'type' => 'bar',
                    'label' => 'Low Risk Revenue',
                    'data' => array_fill(0, 12, $riskRevenue['low'] ?? 0),
                    'backgroundColor' => '#10b981',
                    'yAxisID' => 'y1',
                ],
                [
                    'type' => 'bar',
                    'label' => 'Medium Risk Revenue',
                    'data' => array_fill(0, 12, $riskRevenue['medium'] ?? 0),
                    'backgroundColor' => '#f59e0b',
                    'yAxisID' => 'y1',
                ],
                [
                    'type' => 'bar',
                    'label' => 'High Risk Revenue',
                    'data' => array_fill(0, 12, $riskRevenue['high'] ?? 0),
                    'backgroundColor' => '#ef4444',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $months->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Month',
                    ],
                ],
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'New Customers',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue ($)',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];
    }
}