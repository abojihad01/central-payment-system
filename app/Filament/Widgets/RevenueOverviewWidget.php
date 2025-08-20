<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Website;
use App\Models\GeneratedLink;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RevenueOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // Get current period data
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $thisYear = Carbon::now()->startOfYear();

        // Today's revenue
        $todayRevenue = Payment::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->sum('amount');

        $yesterdayRevenue = Payment::where('status', 'completed')
            ->whereDate('created_at', $yesterday)
            ->sum('amount');

        // This month's revenue
        $thisMonthRevenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', $thisMonth)
            ->sum('amount');

        $lastMonthRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$lastMonth, $thisMonth])
            ->sum('amount');

        // This year's revenue
        $thisYearRevenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', $thisYear)
            ->sum('amount');

        // Calculate percentage changes
        $dailyChange = $yesterdayRevenue > 0 
            ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100 
            : 0;

        $monthlyChange = $lastMonthRevenue > 0 
            ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;

        // Top performing website
        $topWebsite = DB::table('websites')
            ->leftJoin('generated_links', 'websites.id', '=', 'generated_links.website_id')
            ->leftJoin('payments', 'generated_links.id', '=', 'payments.generated_link_id')
            ->select([
                'websites.name',
                DB::raw('COALESCE(SUM(CASE WHEN payments.status = "completed" THEN payments.amount ELSE 0 END), 0) as total_revenue')
            ])
            ->where('payments.created_at', '>=', $thisMonth)
            ->groupBy('websites.id', 'websites.name')
            ->orderBy('total_revenue', 'desc')
            ->first();

        // Average payment value this month
        $avgPaymentThisMonth = Payment::where('status', 'completed')
            ->where('created_at', '>=', $thisMonth)
            ->avg('amount') ?: 0;

        // Total generated links
        $totalGeneratedLinks = GeneratedLink::count();
        $activeGeneratedLinks = GeneratedLink::where('is_active', true)->count();

        // Conversion rate this month
        $totalPaymentsThisMonth = Payment::where('created_at', '>=', $thisMonth)->count();
        $completedPaymentsThisMonth = Payment::where('status', 'completed')
            ->where('created_at', '>=', $thisMonth)
            ->count();
        
        $conversionRate = $totalPaymentsThisMonth > 0 
            ? ($completedPaymentsThisMonth / $totalPaymentsThisMonth) * 100 
            : 0;

        return [
            Stat::make('إيرادات اليوم', '$' . number_format($todayRevenue, 2))
                ->description($dailyChange >= 0 ? '+' . round($dailyChange, 1) . '% من الأمس' : round($dailyChange, 1) . '% من الأمس')
                ->descriptionIcon($dailyChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($dailyChange >= 0 ? 'success' : 'danger')
                ->chart([
                    $yesterdayRevenue,
                    $todayRevenue,
                ]),

            Stat::make('إيرادات هذا الشهر', '$' . number_format($thisMonthRevenue, 2))
                ->description($monthlyChange >= 0 ? '+' . round($monthlyChange, 1) . '% من الشهر الماضي' : round($monthlyChange, 1) . '% من الشهر الماضي')
                ->descriptionIcon($monthlyChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyChange >= 0 ? 'success' : 'danger'),

            Stat::make('إيرادات هذا العام', '$' . number_format($thisYearRevenue, 2))
                ->description('إجمالي إيرادات العام الحالي')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('متوسط قيمة الدفعة', '$' . number_format($avgPaymentThisMonth, 2))
                ->description('هذا الشهر')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('أفضل موقع أداءً', $topWebsite ? $topWebsite->name : 'لا يوجد')
                ->description($topWebsite ? '$' . number_format($topWebsite->total_revenue, 2) . ' هذا الشهر' : 'لا توجد بيانات')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('warning'),

            Stat::make('معدل نجاح المدفوعات', round($conversionRate, 1) . '%')
                ->description('هذا الشهر')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($conversionRate > 80 ? 'success' : ($conversionRate > 60 ? 'warning' : 'danger')),

            Stat::make('الروابط المُولدة', number_format($totalGeneratedLinks))
                ->description($activeGeneratedLinks . ' رابط نشط')
                ->descriptionIcon('heroicon-m-link')
                ->color('primary'),

            Stat::make('المدفوعات اليوم', number_format(Payment::whereDate('created_at', $today)->count()))
                ->description(Payment::where('status', 'completed')->whereDate('created_at', $today)->count() . ' مكتملة')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),
        ];
    }
}