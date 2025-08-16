<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Customer;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
    * GET /api/dashboard/metrics
    * Returns dashboard widgets and alerts for quick overview
    */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $today = today();
            $yesterday = Carbon::yesterday();
            $sevenDaysAgo = now()->subDays(7);

            // Revenue Today and change from yesterday
            $revenueToday = (float) Payment::where('status', 'completed')
                ->whereDate('created_at', $today)
                ->sum('amount');

            $revenueYesterday = (float) Payment::where('status', 'completed')
                ->whereDate('created_at', $yesterday)
                ->sum('amount');

            $changeFromYesterday = $revenueYesterday > 0
                ? round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 2)
                : ($revenueToday > 0 ? 100.0 : 0.0);

            // Revenue trend for last 7 days
            $dayExpr = $this->periodExpression('created_at', 'day');
            $trend = Payment::where('status', 'completed')
                ->whereDate('created_at', '>=', $sevenDaysAgo)
                ->select(DB::raw($dayExpr . ' as day'), DB::raw('SUM(amount) as revenue'))
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn($r) => [
                    'day' => $r->day,
                    'revenue' => round((float) $r->revenue, 2),
                ]);

            // New subscriptions today
            $newSubsToday = (int) Subscription::whereDate('created_at', $today)->count();
            $newSubsYesterday = (int) Subscription::whereDate('created_at', $yesterday)->count();
            $newSubsChange = $newSubsYesterday > 0
                ? round((($newSubsToday - $newSubsYesterday) / $newSubsYesterday) * 100, 2)
                : ($newSubsToday > 0 ? 100.0 : 0.0);

            // Active users - use Customers if available, fallback to active subscriptions count
            $activeUsers = (int) (Customer::count() ?: Subscription::where('status', 'active')->count());
            $activeUsersLastWeek = (int) (Customer::whereDate('created_at', '<=', $sevenDaysAgo)->count() ?: 1);
            $activeUsersGrowth = $activeUsersLastWeek > 0
                ? round((($activeUsers - $activeUsersLastWeek) / $activeUsersLastWeek) * 100, 2)
                : 0.0;

            // Payment success rate (7 days)
            $payments7d = Payment::whereDate('created_at', '>=', $sevenDaysAgo);
            $total7d = (int) (clone $payments7d)->count();
            $success7d = (int) (clone $payments7d)->where('status', 'completed')->count();
            $rate7d = $total7d > 0 ? round(($success7d / $total7d) * 100, 2) : 0.0;

            $trendData = Payment::whereDate('created_at', '>=', $sevenDaysAgo)
                ->select(DB::raw($dayExpr . ' as day'),
                         DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success"),
                         DB::raw('COUNT(*) as total'))
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(function ($r) {
                    $rate = $r->total > 0 ? round(($r->success / $r->total) * 100, 2) : 0.0;
                    return ['day' => $r->day, 'rate' => $rate];
                });

            // Top plans by revenue and subscriptions
            $topByRevenue = Payment::join('subscriptions', 'subscriptions.payment_id', '=', 'payments.id')
                ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->where('payments.status', 'completed')
                ->select('plans.name', DB::raw('SUM(payments.amount) as revenue'))
                ->groupBy('plans.name')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get()
                ->map(fn($r) => ['plan' => $r->name, 'revenue' => round((float) $r->revenue, 2)]);

            $topBySubscriptions = Subscription::join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->select('plans.name', DB::raw('COUNT(*) as count'))
                ->groupBy('plans.name')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn($r) => ['plan' => $r->name, 'count' => (int) $r->count]);

            // Recent transactions
            $recentSuccessful = Payment::where('status', 'completed')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'amount', 'currency', 'created_at']);

            $recentFailed = Payment::where('status', 'failed')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'amount', 'currency', 'created_at']);

            $recentPending = Payment::where('status', 'pending')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'amount', 'currency', 'created_at']);

            // Alerts
            $lowSuccess = $rate7d < 60; // arbitrary threshold for demo
            $churnRate = (function () {
                $total = Subscription::count();
                $cancelled = Subscription::where('status', 'cancelled')->count();
                return $total > 0 ? round(($cancelled / $total) * 100, 2) : 0.0;
            })();
            $highChurn = $churnRate > 10; // arbitrary threshold

            $revenue7d = Payment::where('status', 'completed')
                ->whereDate('created_at', '>=', $sevenDaysAgo)
                ->select(DB::raw($dayExpr . ' as day'), DB::raw('SUM(amount) as revenue'))
                ->groupBy('day')->orderBy('day')->pluck('revenue')->toArray();
            $revenueDecline = false;
            if (count($revenue7d) >= 2) {
                $first = $revenue7d[0];
                $last = end($revenue7d);
                $revenueDecline = $last < $first;
            }

            $payload = [
                'widgets' => [
                    'revenue_today' => [
                        'value' => round($revenueToday, 2),
                        'change_from_yesterday' => $changeFromYesterday,
                        'trend' => $trend,
                    ],
                    'new_subscriptions_today' => [
                        'count' => $newSubsToday,
                        'change_from_yesterday' => $newSubsChange,
                    ],
                    'active_users' => [
                        'total' => $activeUsers,
                        'growth_rate' => $activeUsersGrowth,
                    ],
                    'payment_success_rate_7d' => [
                        'rate' => $rate7d,
                        'trend_data' => $trendData,
                    ],
                    'top_plans' => [
                        'by_revenue' => $topByRevenue,
                        'by_subscriptions' => $topBySubscriptions,
                    ],
                    'recent_transactions' => [
                        'successful' => $recentSuccessful,
                        'failed' => $recentFailed,
                        'pending' => $recentPending,
                    ],
                ],
                'alerts' => [
                    'low_success_rate' => $lowSuccess,
                    'high_churn_detected' => $highChurn,
                    'revenue_decline' => $revenueDecline,
                ],
            ];

            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json([
                'widgets' => [
                    'revenue_today' => [
                        'value' => 0,
                        'change_from_yesterday' => 0,
                        'trend' => [],
                    ],
                    'new_subscriptions_today' => [
                        'count' => 0,
                        'change_from_yesterday' => 0,
                    ],
                    'active_users' => [
                        'total' => 0,
                        'growth_rate' => 0,
                    ],
                    'payment_success_rate_7d' => [
                        'rate' => 0,
                        'trend_data' => [],
                    ],
                    'top_plans' => [
                        'by_revenue' => [],
                        'by_subscriptions' => [],
                    ],
                    'recent_transactions' => [
                        'successful' => [],
                        'failed' => [],
                        'pending' => [],
                    ],
                ],
                'alerts' => [
                    'low_success_rate' => false,
                    'high_churn_detected' => false,
                    'revenue_decline' => false,
                ],
                'error' => 'Failed to load dashboard metrics: ' . $e->getMessage(),
            ], 200);
        }

    }

    /**
     * Build a database-agnostic date formatting expression for grouping by day/month.
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
}
