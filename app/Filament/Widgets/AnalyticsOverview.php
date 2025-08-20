<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Customer;
use App\Models\FraudAlert;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class AnalyticsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Calculate time periods
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Revenue metrics
        $todayRevenue = Payment::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->sum('amount');

        $yesterdayRevenue = Payment::where('status', 'completed')
            ->whereDate('created_at', $yesterday)
            ->sum('amount');

        $monthlyRevenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', $thisMonth)
            ->sum('amount');

        $lastMonthRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$lastMonth, $thisMonth])
            ->sum('amount');

        // Subscription metrics
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $trialSubscriptions = Subscription::where('status', 'trial')->count();
        $cancelledToday = Subscription::whereDate('cancelled_at', $today)->count();

        // Customer metrics
        $totalCustomers = Customer::count();
        $newCustomersToday = Customer::whereDate('created_at', $today)->count();
        $newCustomersThisMonth = Customer::where('created_at', '>=', $thisMonth)->count();

        // Security metrics
        $fraudAlertsToday = FraudAlert::where('status', 'active')
            ->whereDate('created_at', $today)
            ->count();

        // Calculate percentage changes
        $revenueChange = $yesterdayRevenue > 0 
            ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100 
            : 0;

        $monthlyRevenueChange = $lastMonthRevenue > 0 
            ? (($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;

        // Calculate MRR (Monthly Recurring Revenue)
        $mrr = Subscription::join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'active')
            ->sum('plans.price');

        return [
            Stat::make('الإيرادات اليومية', '$' . number_format($todayRevenue, 2))
                ->description($revenueChange >= 0 ? '+' . round($revenueChange, 1) . '% من الأمس' : round($revenueChange, 1) . '% من الأمس')
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger')
                ->chart([
                    $yesterdayRevenue,
                    $todayRevenue,
                ]),

            Stat::make('الإيرادات الشهرية', '$' . number_format($monthlyRevenue, 2))
                ->description($monthlyRevenueChange >= 0 ? '+' . round($monthlyRevenueChange, 1) . '% من الشهر الماضي' : round($monthlyRevenueChange, 1) . '% من الشهر الماضي')
                ->descriptionIcon($monthlyRevenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyRevenueChange >= 0 ? 'success' : 'danger'),

            Stat::make('الإيرادات الشهرية المتكررة', '$' . number_format($mrr, 2))
                ->description('إيرادات الاشتراكات النشطة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('الاشتراكات النشطة', number_format($activeSubscriptions))
                ->description($trialSubscriptions . ' قيد التجربة')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),

            Stat::make('إجمالي العملاء', number_format($totalCustomers))
                ->description($newCustomersToday . ' جديد اليوم، ' . $newCustomersThisMonth . ' هذا الشهر')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('تنبيهات الأمان', number_format($fraudAlertsToday))
                ->description('تنبيهات الاحتيال اليوم')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($fraudAlertsToday > 10 ? 'danger' : ($fraudAlertsToday > 5 ? 'warning' : 'success')),

            Stat::make('معدل الإلغاء', $this->calculateChurnRate() . '%')
                ->description('معدل الإلغاء الشهري')
                ->descriptionIcon('heroicon-m-arrow-right-start-on-rectangle')
                ->color($this->calculateChurnRate() > 5 ? 'danger' : 'success'),

            Stat::make('معدل التحويل', $this->calculateConversionRate() . '%')
                ->description('التحويل من التجربة للدفع')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
        ];
    }

    private function calculateChurnRate(): float
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        
        $subscribersStartOfMonth = Subscription::where('status', 'active')
            ->where('created_at', '<', $startOfMonth)
            ->count();

        $cancelledThisMonth = Subscription::where('status', 'cancelled')
            ->where('cancelled_at', '>=', $startOfMonth)
            ->count();

        if ($subscribersStartOfMonth == 0) {
            return 0;
        }

        return round(($cancelledThisMonth / $subscribersStartOfMonth) * 100, 2);
    }

    private function calculateConversionRate(): float
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        
        $trialSubscriptions = Subscription::where('is_trial', true)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $convertedSubscriptions = Subscription::where('is_trial', false)
            ->where('status', 'active')
            ->where('created_at', '>=', $startOfMonth)
            ->whereNotNull('trial_ends_at')
            ->count();

        if ($trialSubscriptions == 0) {
            return 0;
        }

        return round(($convertedSubscriptions / $trialSubscriptions) * 100, 2);
    }

    protected function getPollingInterval(): ?string
    {
        return '30s';
    }
}