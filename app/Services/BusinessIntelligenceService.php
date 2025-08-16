<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Customer;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BusinessIntelligenceService
{
    /**
     * Generate revenue forecasting using historical data
     */
    public function getRevenueForecast(int $months = 12): array
    {
        $cacheKey = "revenue_forecast_{$months}";
        
        return Cache::remember($cacheKey, 3600, function () use ($months) {
            // Get historical revenue data for the past 24 months
            $historicalData = $this->getHistoricalRevenueData(24);
            
            // Calculate growth trends
            $growthRates = $this->calculateGrowthRates($historicalData);
            $avgGrowthRate = collect($growthRates)->avg();
            
            // Generate forecast
            $lastRevenue = end($historicalData)['revenue'] ?? 0;
            $forecast = [];
            
            for ($i = 1; $i <= $months; $i++) {
                $predictedRevenue = $lastRevenue * (1 + ($avgGrowthRate / 100));
                $forecast[] = [
                    'month' => now()->addMonths($i)->format('Y-m'),
                    'predicted_revenue' => round($predictedRevenue, 2),
                    'confidence' => $this->calculateConfidence($growthRates, $i),
                    'scenario_optimistic' => round($predictedRevenue * 1.2, 2),
                    'scenario_pessimistic' => round($predictedRevenue * 0.8, 2),
                ];
                $lastRevenue = $predictedRevenue;
            }
            
            return [
                'historical_data' => array_slice($historicalData, -12), // Last 12 months
                'forecast' => $forecast,
                'insights' => [
                    'avg_monthly_growth_rate' => round($avgGrowthRate, 2),
                    'revenue_volatility' => $this->calculateVolatility($historicalData),
                    'seasonal_patterns' => $this->detectSeasonalPatterns($historicalData),
                ]
            ];
        });
    }

    /**
     * Get customer lifetime value predictions with segmentation
     */
    public function getAdvancedLTVAnalysis(): array
    {
        $cacheKey = 'advanced_ltv_analysis';
        
        return Cache::remember($cacheKey, 1800, function () {
            $segments = [
                'high_value' => Customer::where('lifetime_value', '>=', 1000)->get(),
                'medium_value' => Customer::whereBetween('lifetime_value', [500, 999])->get(),
                'low_value' => Customer::whereBetween('lifetime_value', [100, 499])->get(),
                'new_customers' => Customer::where('lifetime_value', '<', 100)->get(),
            ];

            $analysis = [];
            foreach ($segments as $segment => $customers) {
                $analysis[$segment] = [
                    'count' => $customers->count(),
                    'avg_ltv' => round($customers->avg('lifetime_value'), 2),
                    'total_ltv' => round($customers->sum('lifetime_value'), 2),
                    'avg_purchase_frequency' => round($customers->avg('successful_payments'), 1),
                    'avg_days_since_first_purchase' => round($customers->where('first_purchase_at', '!=', null)->avg(function ($customer) {
                        return $customer->first_purchase_at ? $customer->first_purchase_at->diffInDays(now()) : 0;
                    }), 1),
                    'churn_risk_score' => round($customers->avg('risk_score'), 1),
                ];
            }

            return [
                'segments' => $analysis,
                'predictive_models' => $this->buildLTVPredictiveModels(),
                'recommendations' => $this->generateLTVRecommendations($analysis),
            ];
        });
    }

    /**
     * Generate subscription churn prediction
     */
    public function getChurnPrediction(): array
    {
        $cacheKey = 'churn_prediction';
        
        return Cache::remember($cacheKey, 1800, function () {
            $activeSubscriptions = Subscription::where('status', 'active')->get();
            $churnRisk = [];

            foreach ($activeSubscriptions as $subscription) {
                $risk = $this->calculateChurnRisk($subscription);
                if ($risk['risk_score'] >= 60) { // High risk threshold
                    $churnRisk[] = $risk;
                }
            }

            // Sort by risk score descending
            usort($churnRisk, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);

            return [
                'high_risk_subscriptions' => array_slice($churnRisk, 0, 100), // Top 100 at risk
                'churn_statistics' => [
                    'total_active_subscriptions' => $activeSubscriptions->count(),
                    'high_risk_count' => count($churnRisk),
                    'high_risk_percentage' => $activeSubscriptions->count() > 0 ? 
                        round((count($churnRisk) / $activeSubscriptions->count()) * 100, 2) : 0,
                    'potential_revenue_at_risk' => round(collect($churnRisk)->sum('monthly_value'), 2),
                ],
                'prevention_strategies' => $this->generateChurnPreventionStrategies($churnRisk),
            ];
        });
    }

    /**
     * Generate market insights and competitive analysis
     */
    public function getMarketInsights(): array
    {
        $cacheKey = 'market_insights';
        
        return Cache::remember($cacheKey, 3600, function () {
            return [
                'pricing_analysis' => $this->analyzePricing(),
                'product_performance' => $this->analyzeProductPerformance(),
                'customer_acquisition_cost' => $this->calculateCAC(),
                'market_penetration' => $this->analyzeMarketPenetration(),
                'competitive_positioning' => $this->getCompetitivePositioning(),
            ];
        });
    }

    /**
     * Generate automated business insights and alerts
     */
    public function getBusinessInsights(): array
    {
        $insights = [];

        // Revenue insights
        $revenueGrowth = $this->getRevenueGrowthInsight();
        if ($revenueGrowth['alert']) {
            $insights[] = $revenueGrowth;
        }

        // Customer acquisition insights
        $acquisitionInsight = $this->getAcquisitionInsight();
        if ($acquisitionInsight['alert']) {
            $insights[] = $acquisitionInsight;
        }

        // Subscription insights
        $subscriptionInsight = $this->getSubscriptionInsight();
        if ($subscriptionInsight['alert']) {
            $insights[] = $subscriptionInsight;
        }

        // Fraud insights
        $fraudInsight = $this->getFraudInsight();
        if ($fraudInsight['alert']) {
            $insights[] = $fraudInsight;
        }

        return [
            'insights' => $insights,
            'recommendations' => $this->generateBusinessRecommendations($insights),
            'kpi_trends' => $this->getKPITrends(),
        ];
    }

    // Private helper methods

    private function getHistoricalRevenueData(int $months): array
    {
        $startDate = now()->subMonths($months);
        
        $monthExpr = $this->periodExpression('created_at', 'month');
        return Payment::where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw($monthExpr . ' as month'), DB::raw('SUM(amount) as revenue'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'revenue' => (float) $item->revenue,
                ];
            })
            ->toArray();
    }

    private function calculateGrowthRates(array $data): array
    {
        $rates = [];
        for ($i = 1; $i < count($data); $i++) {
            $previous = $data[$i - 1]['revenue'];
            $current = $data[$i]['revenue'];
            
            if ($previous > 0) {
                $rates[] = (($current - $previous) / $previous) * 100;
            }
        }
        return $rates;
    }

    private function calculateConfidence(array $growthRates, int $monthsOut): float
    {
        $baseConfidence = 95; // Start with 95% confidence
        $volatility = count($growthRates) > 0 ? sqrt(collect($growthRates)->map(fn($x) => pow($x, 2))->avg()) : 0;
        $timeDecay = $monthsOut * 5; // Confidence decreases by 5% per month
        
        return max(50, $baseConfidence - ($volatility * 0.5) - $timeDecay);
    }

    private function calculateVolatility(array $data): float
    {
        $revenues = collect($data)->pluck('revenue');
        $mean = $revenues->avg();
        $variance = $revenues->map(fn($x) => pow($x - $mean, 2))->avg();
        
        return $mean > 0 ? round(sqrt($variance) / $mean * 100, 2) : 0;
    }

    private function detectSeasonalPatterns(array $data): array
    {
        $monthlyAverages = [];
        
        foreach ($data as $item) {
            $month = (int) substr($item['month'], -2);
            if (!isset($monthlyAverages[$month])) {
                $monthlyAverages[$month] = [];
            }
            $monthlyAverages[$month][] = $item['revenue'];
        }
        
        $patterns = [];
        foreach ($monthlyAverages as $month => $revenues) {
            $patterns[] = [
                'month' => $month,
                'avg_revenue' => round(collect($revenues)->avg(), 2),
                'pattern' => count($revenues) > 1 ? 'recurring' : 'limited_data'
            ];
        }
        
        return $patterns;
    }

    private function buildLTVPredictiveModels(): array
    {
        // Simplified predictive model based on customer behavior
        $correlations = [
            'subscription_customers_ltv_multiplier' => 2.5,
            'frequent_buyer_threshold' => 5,
            'high_engagement_ltv_boost' => 1.3,
            'low_risk_ltv_multiplier' => 1.1,
        ];
        
        return [
            'model_accuracy' => 78.5, // Simulated accuracy
            'key_factors' => [
                'subscription_status' => 0.45,
                'purchase_frequency' => 0.25,
                'engagement_score' => 0.15,
                'risk_score' => 0.10,
                'acquisition_channel' => 0.05,
            ],
            'correlations' => $correlations,
        ];
    }

    private function generateLTVRecommendations(array $analysis): array
    {
        $recommendations = [];
        
        if ($analysis['low_value']['count'] > $analysis['high_value']['count'] * 3) {
            $recommendations[] = [
                'type' => 'customer_development',
                'priority' => 'high',
                'message' => 'High proportion of low-value customers. Focus on upselling and engagement.',
                'action' => 'Implement targeted upselling campaigns for low-value segment'
            ];
        }
        
        if ($analysis['high_value']['churn_risk_score'] > 30) {
            $recommendations[] = [
                'type' => 'retention',
                'priority' => 'critical',
                'message' => 'High-value customers showing elevated churn risk.',
                'action' => 'Implement VIP retention program and proactive support'
            ];
        }
        
        return $recommendations;
    }

    private function calculateChurnRisk(Subscription $subscription): array
    {
        $riskFactors = 0;
        $factors = [];
        
        // Payment failures
        $failedPayments = Payment::where('subscription_id', $subscription->subscription_id)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        
        if ($failedPayments > 0) {
            $riskFactors += $failedPayments * 15;
            $factors[] = "Failed payments: {$failedPayments}";
        }
        
        // Subscription age
        $ageInDays = $subscription->starts_at->diffInDays(now());
        if ($ageInDays < 30) {
            $riskFactors += 20; // New subscriptions are higher risk
            $factors[] = "New subscription ({$ageInDays} days)";
        }
        
        // Plan downgrades
        $downgrades = \App\Models\SubscriptionEvent::where('subscription_id', $subscription->id)
            ->where('event_type', 'plan_downgraded')
            ->where('created_at', '>=', now()->subDays(60))
            ->count();
        
        if ($downgrades > 0) {
            $riskFactors += 25;
            $factors[] = "Recent downgrades: {$downgrades}";
        }
        
        // Customer risk score
        $customer = Customer::where('email', $subscription->customer_email)->first();
        if ($customer && $customer->risk_score > 50) {
            $riskFactors += $customer->risk_score * 0.3;
            $factors[] = "Customer risk score: {$customer->risk_score}";
        }
        
        $planData = $subscription->plan_data;
        
        return [
            'subscription_id' => $subscription->subscription_id,
            'customer_email' => $subscription->customer_email,
            'plan_name' => $planData['name'] ?? 'Unknown',
            'monthly_value' => $planData['price'] ?? 0,
            'risk_score' => min(100, $riskFactors),
            'risk_level' => $riskFactors >= 70 ? 'critical' : ($riskFactors >= 40 ? 'high' : 'medium'),
            'risk_factors' => $factors,
            'days_active' => $ageInDays,
        ];
    }

    private function generateChurnPreventionStrategies(array $churnRisk): array
    {
        $strategies = [
            'immediate_actions' => [
                'Reach out to critical risk customers with personalized offers',
                'Implement payment retry logic for failed payments',
                'Deploy in-app engagement campaigns for at-risk users'
            ],
            'medium_term_actions' => [
                'Develop customer success onboarding for new subscribers',
                'Create loyalty programs for long-term customers',
                'Implement predictive engagement scoring'
            ],
            'long_term_initiatives' => [
                'Build comprehensive customer health scoring',
                'Develop AI-powered churn prediction models',
                'Create automated intervention workflows'
            ]
        ];
        
        return $strategies;
    }

    // Additional insight generation methods
    private function getRevenueGrowthInsight(): array
    {
        $currentMonth = Payment::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');
            
        $lastMonth = Payment::where('status', 'completed')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('amount');
            
        $growthRate = $lastMonth > 0 ? (($currentMonth - $lastMonth) / $lastMonth) * 100 : 0;
        
        return [
            'type' => 'revenue_growth',
            'alert' => abs($growthRate) > 20, // Alert if growth/decline > 20%
            'message' => $growthRate > 0 ? 
                "Revenue increased by " . round($growthRate, 1) . "% this month" :
                "Revenue declined by " . round(abs($growthRate), 1) . "% this month",
            'impact' => $growthRate > 20 ? 'positive' : ($growthRate < -20 ? 'negative' : 'neutral'),
            'value' => $growthRate,
        ];
    }

    private function getAcquisitionInsight(): array
    {
        $newCustomers = Customer::whereDate('created_at', '>=', now()->subDays(7))->count();
        $avgWeekly = Customer::where('created_at', '>=', now()->subDays(30))->count() / 4;
        
        $change = $avgWeekly > 0 ? (($newCustomers - $avgWeekly) / $avgWeekly) * 100 : 0;
        
        return [
            'type' => 'customer_acquisition',
            'alert' => abs($change) > 30,
            'message' => "Customer acquisition " . ($change > 0 ? 'increased' : 'decreased') . 
                         " by " . round(abs($change), 1) . "% this week",
            'impact' => $change > 0 ? 'positive' : 'negative',
            'value' => $change,
        ];
    }

    private function getSubscriptionInsight(): array
    {
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $cancelledThisWeek = Subscription::where('status', 'cancelled')
            ->where('cancelled_at', '>=', now()->subDays(7))
            ->count();
            
        $churnRate = $activeSubscriptions > 0 ? ($cancelledThisWeek / $activeSubscriptions) * 100 : 0;
        
        return [
            'type' => 'subscription_churn',
            'alert' => $churnRate > 5, // Alert if weekly churn > 5%
            'message' => "Weekly churn rate is " . round($churnRate, 2) . "%",
            'impact' => $churnRate > 5 ? 'negative' : 'neutral',
            'value' => $churnRate,
        ];
    }

    private function getFraudInsight(): array
    {
        $fraudAlertsToday = \App\Models\FraudAlert::whereDate('created_at', today())->count();
        $avgDaily = \App\Models\FraudAlert::where('created_at', '>=', now()->subDays(7))->count() / 7;
        
        $increase = $avgDaily > 0 ? (($fraudAlertsToday - $avgDaily) / $avgDaily) * 100 : 0;
        
        return [
            'type' => 'fraud_activity',
            'alert' => $increase > 50,
            'message' => "Fraud alerts " . ($increase > 0 ? 'increased' : 'decreased') . 
                         " by " . round(abs($increase), 1) . "% today",
            'impact' => $increase > 50 ? 'negative' : 'neutral',
            'value' => $increase,
        ];
    }

    private function generateBusinessRecommendations(array $insights): array
    {
        $recommendations = [];
        
        foreach ($insights as $insight) {
            switch ($insight['type']) {
                case 'revenue_growth':
                    if ($insight['value'] < -15) {
                        $recommendations[] = 'Consider promotional campaigns to boost revenue';
                    }
                    break;
                    
                case 'customer_acquisition':
                    if ($insight['value'] < -20) {
                        $recommendations[] = 'Review and optimize marketing channels';
                    }
                    break;
                    
                case 'subscription_churn':
                    if ($insight['value'] > 5) {
                        $recommendations[] = 'Implement customer retention initiatives';
                    }
                    break;
                    
                case 'fraud_activity':
                    if ($insight['value'] > 50) {
                        $recommendations[] = 'Review and tighten fraud detection rules';
                    }
                    break;
            }
        }
        
        return $recommendations;
    }

    private function getKPITrends(): array
    {
        return [
            'mrr_trend' => $this->calculateMRRTrend(),
            'customer_growth_trend' => $this->calculateCustomerGrowthTrend(),
            'churn_trend' => $this->calculateChurnTrend(),
            'ltv_trend' => $this->calculateLTVTrend(),
        ];
    }

    private function calculateMRRTrend(): array
    {
        // Simplified MRR trend calculation
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $mrr = $this->calculateMRRForMonth($date);
            $months[] = [
                'month' => $date->format('Y-m'),
                'mrr' => $mrr
            ];
        }
        
        return $months;
    }

    private function calculateMRRForMonth(Carbon $date): float
    {
        // This is a simplified calculation
        $monthlyPlans = Plan::where('billing_interval', 'monthly')->get();
        $mrr = 0;
        
        foreach ($monthlyPlans as $plan) {
            $activeSubscriptions = Subscription::where('plan_id', $plan->id)
                ->where('status', 'active')
                ->whereDate('starts_at', '<=', $date->endOfMonth())
                ->where(function ($query) use ($date) {
                    $query->whereNull('cancelled_at')
                          ->orWhereDate('cancelled_at', '>', $date->endOfMonth());
                })
                ->count();
            
            $mrr += $activeSubscriptions * $plan->price;
        }
        
        return round($mrr, 2);
    }

    private function calculateCustomerGrowthTrend(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $newCustomers = Customer::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            
            $months[] = [
                'month' => $date->format('Y-m'),
                'new_customers' => $newCustomers
            ];
        }
        
        return $months;
    }

    private function calculateChurnTrend(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $churned = Subscription::where('status', 'cancelled')
                ->whereYear('cancelled_at', $date->year)
                ->whereMonth('cancelled_at', $date->month)
                ->count();
            
            $total = Subscription::whereDate('starts_at', '<=', $date->endOfMonth())
                ->count();
                
            $churnRate = $total > 0 ? ($churned / $total) * 100 : 0;
            
            $months[] = [
                'month' => $date->format('Y-m'),
                'churn_rate' => round($churnRate, 2)
            ];
        }
        
        return $months;
    }

    private function calculateLTVTrend(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $avgLTV = Customer::whereDate('created_at', '<=', $date->endOfMonth())
                ->avg('lifetime_value');
            
            $months[] = [
                'month' => $date->format('Y-m'),
                'avg_ltv' => round($avgLTV ?? 0, 2)
            ];
        }
        
        return $months;
    }

    // Placeholder methods for market analysis
    private function analyzePricing(): array
    {
        return ['status' => 'placeholder', 'message' => 'Pricing analysis would integrate with market data APIs'];
    }

    private function analyzeProductPerformance(): array
    {
        return Plan::withCount(['subscriptions'])->get()->map(function ($plan) {
            return [
                'plan_name' => $plan->name,
                'subscription_count' => $plan->subscriptions_count,
                'performance_rating' => $plan->subscriptions_count > 10 ? 'high' : 'low'
            ];
        })->toArray();
    }

    private function calculateCAC(): array
    {
        // Simplified CAC calculation - would normally integrate with marketing spend data
        $newCustomersThisMonth = Customer::whereMonth('created_at', now()->month)->count();
        $estimatedMarketingSpend = 5000; // This would come from marketing APIs
        
        $cac = $newCustomersThisMonth > 0 ? $estimatedMarketingSpend / $newCustomersThisMonth : 0;
        
        return [
            'cac' => round($cac, 2),
            'new_customers' => $newCustomersThisMonth,
            'marketing_spend' => $estimatedMarketingSpend
        ];
    }

    private function analyzeMarketPenetration(): array
    {
        return [
            'total_customers' => Customer::count(),
            'market_share_estimate' => '2.3%', // Would integrate with market data
            'growth_opportunity' => 'high'
        ];
    }

    private function getCompetitivePositioning(): array
    {
        return [
            'pricing_position' => 'competitive',
            'feature_completeness' => 'advanced',
            'market_position' => 'growing'
        ];
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