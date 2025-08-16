<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Customer;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get comprehensive revenue analytics
     */
    public function getRevenueAnalytics(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? now()->subMonths(12);
        $endDate = $filters['end_date'] ?? now();
        
        return [
            'overview' => $this->getRevenueOverview($startDate, $endDate),
            'monthly_breakdown' => $this->getMonthlyRevenueBreakdown($startDate, $endDate),
            'payment_methods' => $this->getRevenueByPaymentMethod($startDate, $endDate),
            'subscription_vs_oneoff' => $this->getSubscriptionVsOneOffRevenue($startDate, $endDate),
            'geographic_breakdown' => $this->getGeographicRevenue($startDate, $endDate),
            'plan_performance' => $this->getPlanPerformance($startDate, $endDate),
        ];
    }

    /**
     * Get revenue overview metrics
     */
    private function getRevenueOverview($startDate, $endDate): array
    {
        $totalRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $previousPeriod = Payment::where('status', 'completed')
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->subDays(Carbon::parse($endDate)->diffInDays($startDate)),
                $startDate
            ])
            ->sum('amount');

        $mrr = $this->calculateMRR();
        $arr = $mrr * 12; // Annual Recurring Revenue
        
        return [
            'total_revenue' => round($totalRevenue, 2),
            'previous_period_revenue' => round($previousPeriod, 2),
            'growth_rate' => $previousPeriod > 0 ? round((($totalRevenue - $previousPeriod) / $previousPeriod) * 100, 2) : 0,
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
            'average_transaction_value' => round($this->getAverageTransactionValue($startDate, $endDate), 2),
            'transactions_count' => Payment::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
        ];
    }

    /**
     * Get monthly revenue breakdown
     */
    private function getMonthlyRevenueBreakdown($startDate, $endDate): array
    {
        $periodExpr = $this->periodExpression('created_at', 'month');
        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw($periodExpr . ' as month'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as transactions'),
                DB::raw('AVG(amount) as avg_transaction')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'revenue' => round($item->revenue, 2),
                    'transactions' => $item->transactions,
                    'avg_transaction' => round($item->avg_transaction, 2),
                ];
            });

        return $monthlyRevenue->toArray();
    }

    /**
     * Get revenue by payment method
     */
    private function getRevenueByPaymentMethod($startDate, $endDate): array
    {
        return Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('payment_gateway', DB::raw('SUM(amount) as revenue'), DB::raw('COUNT(*) as transactions'))
            ->groupBy('payment_gateway')
            ->orderBy('revenue', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'payment_method' => ucfirst($item->payment_gateway),
                    'revenue' => round($item->revenue, 2),
                    'transactions' => $item->transactions,
                ];
            })
            ->toArray();
    }

    /**
     * Get subscription analytics
     */
    public function getSubscriptionAnalytics(): array
    {
        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $trialSubscriptions = Subscription::where('status', 'trial')->count();
        $cancelledSubscriptions = Subscription::where('status', 'cancelled')->count();
        
        $churnRate = $totalSubscriptions > 0 ? round(($cancelledSubscriptions / $totalSubscriptions) * 100, 2) : 0;
        $mrr = $this->calculateMRR();
        
        return [
            'overview' => [
                'total_subscriptions' => $totalSubscriptions,
                'active_subscriptions' => $activeSubscriptions,
                'trial_subscriptions' => $trialSubscriptions,
                'cancelled_subscriptions' => $cancelledSubscriptions,
                'churn_rate' => $churnRate,
                'mrr' => round($mrr, 2),
            ],
            'plan_distribution' => $this->getSubscriptionsByPlan(),
            'lifecycle_analysis' => $this->getSubscriptionLifecycle(),
            'cohort_analysis' => $this->getCohortAnalysis(),
        ];
    }

    /**
     * Get customer analytics
     */
    public function getCustomerAnalytics(): array
    {
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();
        $newCustomersThisMonth = Customer::where('created_at', '>=', now()->startOfMonth())->count();
        
        return [
            'overview' => [
                'total_customers' => $totalCustomers,
                'active_customers' => $activeCustomers,
                'new_customers_this_month' => $newCustomersThisMonth,
                'average_ltv' => round(Customer::avg('lifetime_value'), 2),
            ],
            'segmentation' => [
                'high_value' => Customer::where('lifetime_value', '>=', 1000)->count(),
                'frequent_buyers' => Customer::where('successful_payments', '>', 5)->count(),
                'at_risk' => Customer::where('risk_score', '>', 50)->count(),
                'subscription_customers' => Customer::where('active_subscriptions', '>', 0)->count(),
            ],
            'acquisition_channels' => $this->getCustomerAcquisition(),
            'geographic_distribution' => $this->getCustomerGeographics(),
            'ltv_distribution' => $this->getLTVDistribution(),
        ];
    }

    /**
     * Get fraud and security analytics
     */
    public function getFraudAnalytics(): array
    {
        return [
            'risk_overview' => [
                'total_risk_profiles' => \App\Models\RiskProfile::count(),
                'high_risk_profiles' => \App\Models\RiskProfile::where('risk_score', '>=', 60)->count(),
                'blocked_profiles' => \App\Models\RiskProfile::where('is_blocked', true)->count(),
                'fraud_alerts_today' => \App\Models\FraudAlert::whereDate('created_at', today())->count(),
            ],
            'rule_effectiveness' => [
                'active_rules' => \App\Models\FraudRule::where('is_active', true)->count(),
                'average_accuracy' => round(\App\Models\FraudRule::active()->avg('accuracy_rate'), 2),
                'most_triggered_rules' => \App\Models\FraudRule::orderBy('times_triggered', 'desc')->limit(5)->get([
                    'name', 'times_triggered', 'accuracy_rate'
                ]),
            ],
            'blacklist_stats' => [
                'total_entries' => \App\Models\Blacklist::active()->count(),
                'by_type' => \App\Models\Blacklist::active()->groupBy('type')->selectRaw('type, COUNT(*) as count')->get(),
            ],
        ];
    }

    /**
     * Calculate Monthly Recurring Revenue
     */
    private function calculateMRR(): float
    {
        $monthlyPlans = Plan::where('billing_interval', 'monthly')->get();
        $yearlyPlans = Plan::where('billing_interval', 'yearly')->get();
        
        $monthlyMRR = 0;
        foreach ($monthlyPlans as $plan) {
            $activeSubscriptions = Subscription::where('plan_id', $plan->id)
                ->where('status', 'active')
                ->count();
            $monthlyMRR += $activeSubscriptions * $plan->price;
        }
        
        $yearlyMRR = 0;
        foreach ($yearlyPlans as $plan) {
            $activeSubscriptions = Subscription::where('plan_id', $plan->id)
                ->where('status', 'active')
                ->count();
            $yearlyMRR += $activeSubscriptions * ($plan->price / 12);
        }
        
        return $monthlyMRR + $yearlyMRR;
    }

    /**
     * Get average transaction value
     */
    private function getAverageTransactionValue($startDate, $endDate): float
    {
        return Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->avg('amount') ?? 0;
    }

    /**
     * Get subscriptions by plan
     */
    private function getSubscriptionsByPlan(): array
    {
        return Subscription::join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->select('plans.name', DB::raw('COUNT(*) as count'))
            ->where('subscriptions.status', 'active')
            ->groupBy('plans.name')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get customer acquisition by channel
     */
    private function getCustomerAcquisition(): array
    {
        return Customer::select('acquisition_source', DB::raw('COUNT(*) as count'))
            ->whereNotNull('acquisition_source')
            ->groupBy('acquisition_source')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get customer geographic distribution
     */
    private function getCustomerGeographics(): array
    {
        return Customer::select('country_code', DB::raw('COUNT(*) as count'))
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get LTV distribution
     */
    private function getLTVDistribution(): array
    {
        return [
            'minimal' => Customer::where('lifetime_value', '<', 100)->count(),
            'low_value' => Customer::whereBetween('lifetime_value', [100, 499])->count(),
            'medium_value' => Customer::whereBetween('lifetime_value', [500, 999])->count(),
            'high_value' => Customer::whereBetween('lifetime_value', [1000, 4999])->count(),
            'champion' => Customer::where('lifetime_value', '>=', 5000)->count(),
        ];
    }

    /**
     * Get subscription lifecycle analysis
     */
    private function getSubscriptionLifecycle(): array
    {
        $avgTrialLength = Subscription::whereNotNull('trial_ends_at')
            ->select(DB::raw($this->dayDiffExpression('trial_ends_at', 'starts_at') . ' as avg_days'))
            ->value('avg_days') ?? 0;

        $avgSubscriptionLength = Subscription::where('status', 'cancelled')
            ->whereNotNull('cancelled_at')
            ->select(DB::raw($this->dayDiffExpression('cancelled_at', 'starts_at') . ' as avg_days'))
            ->value('avg_days') ?? 0;

        return [
            'average_trial_length_days' => round($avgTrialLength, 1),
            'average_subscription_length_days' => round($avgSubscriptionLength, 1),
            'trial_to_paid_conversion' => $this->getTrialConversionRate(),
        ];
    }

    /**
     * Get trial conversion rate
     */
    private function getTrialConversionRate(): float
    {
        $totalTrials = Subscription::where('trial_ends_at', '<', now())->count();
        if ($totalTrials === 0) return 0;

        $convertedTrials = Subscription::where('trial_ends_at', '<', now())
            ->where('status', 'active')
            ->count();

        return round(($convertedTrials / $totalTrials) * 100, 2);
    }

    /**
     * Get basic cohort analysis
     */
    private function getCohortAnalysis(): array
    {
        // Simplified cohort analysis - group by month joined
        $periodExpr = $this->periodExpression('starts_at', 'month');
        return Subscription::select(
                DB::raw($periodExpr . ' as cohort_month'),
                DB::raw('COUNT(*) as customers'),
                DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as retained")
            )
            ->where('starts_at', '>=', now()->subMonths(12))
            ->groupBy('cohort_month')
            ->orderBy('cohort_month')
            ->get()
            ->map(function ($cohort) {
                $retentionRate = $cohort->customers > 0 ? 
                    round(($cohort->retained / $cohort->customers) * 100, 2) : 0;
                
                return [
                    'month' => $cohort->cohort_month,
                    'customers' => $cohort->customers,
                    'retained' => $cohort->retained,
                    'retention_rate' => $retentionRate
                ];
            })
            ->toArray();
    }

    /**
     * Build a database-agnostic date formatting expression for grouping.
     */
    private function periodExpression(string $column, string $groupBy): string
    {
        $driver = DB::getDriverName();
        $isMonth = $groupBy === 'month';
        return match ($driver) {
            'sqlite' => $isMonth ? "strftime('%Y-%m', $column)" : "strftime('%Y-%m-%d', $column)",
            'mysql', 'mariadb' => $isMonth ? "DATE_FORMAT($column, '%Y-%m')" : "DATE_FORMAT($column, '%Y-%m-%d')",
            'pgsql' => $isMonth ? "to_char($column, 'YYYY-MM')" : "to_char($column, 'YYYY-MM-DD')",
            default => $isMonth ? "strftime('%Y-%m', $column)" : "strftime('%Y-%m-%d', $column)",
        };
    }

    /**
     * Build a database-agnostic AVG day difference expression between two timestamps.
     */
    private function dayDiffExpression(string $endCol, string $startCol): string
    {
        $driver = DB::getDriverName();
        return match ($driver) {
            'sqlite' => "AVG(julianday($endCol) - julianday($startCol))",
            'mysql', 'mariadb' => "AVG(DATEDIFF($endCol, $startCol))",
            'pgsql' => "AVG(EXTRACT(EPOCH FROM ($endCol - $startCol)) / 86400)",
            default => "AVG(DATEDIFF($endCol, $startCol))",
        };
    }

    /**
     * Get subscription vs one-off revenue breakdown
     */
    private function getSubscriptionVsOneOffRevenue($startDate, $endDate): array
    {
        $subscriptionRevenue = Payment::where('status', 'completed')
            ->whereNotNull('subscription_id')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $oneOffRevenue = Payment::where('status', 'completed')
            ->whereNull('subscription_id')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $total = $subscriptionRevenue + $oneOffRevenue;

        return [
            'subscription_revenue' => round($subscriptionRevenue, 2),
            'subscription_percentage' => $total > 0 ? round(($subscriptionRevenue / $total) * 100, 2) : 0,
            'oneoff_revenue' => round($oneOffRevenue, 2),
            'oneoff_percentage' => $total > 0 ? round(($oneOffRevenue / $total) * 100, 2) : 0,
            'total_revenue' => round($total, 2),
        ];
    }

    /**
     * Get geographic revenue breakdown
     */
    private function getGeographicRevenue($startDate, $endDate): array
    {
        return Payment::join('customers', 'payments.customer_email', '=', 'customers.email')
            ->where('payments.status', 'completed')
            ->whereBetween('payments.created_at', [$startDate, $endDate])
            ->whereNotNull('customers.country_code')
            ->select('customers.country_code', DB::raw('SUM(payments.amount) as revenue'))
            ->groupBy('customers.country_code')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get plan performance metrics
     */
    private function getPlanPerformance($startDate, $endDate): array
    {
        return Plan::leftJoin('subscriptions', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoin('payments', 'subscriptions.id', '=', 'payments.subscription_id')
            ->select(
                'plans.name',
                'plans.price',
                DB::raw('COUNT(DISTINCT subscriptions.id) as total_subscriptions'),
                DB::raw("SUM(CASE WHEN subscriptions.status = 'active' THEN 1 ELSE 0 END) as active_subscriptions"),
                DB::raw("COALESCE(SUM(CASE WHEN payments.status = 'completed' AND payments.created_at BETWEEN ? AND ? THEN payments.amount ELSE 0 END), 0) as revenue")
            )
            ->groupBy('plans.id', 'plans.name', 'plans.price')
            ->orderBy('revenue', 'desc')
            ->setBindings([$startDate, $endDate])
            ->get()
            ->map(function ($plan) {
                return [
                    'plan_name' => $plan->name,
                    'plan_price' => $plan->price,
                    'total_subscriptions' => $plan->total_subscriptions,
                    'active_subscriptions' => $plan->active_subscriptions,
                    'revenue' => round($plan->revenue, 2),
                ];
            })
            ->toArray();
    }
}