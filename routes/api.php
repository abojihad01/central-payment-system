<?php

use App\Http\Controllers\Api\PaymentLinkController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\FraudDetectionController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\BusinessIntelligenceController;
use App\Http\Controllers\Api\StripeProductController;
use App\Http\Controllers\Api\PaymentVerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Payment Link API Routes
Route::prefix('payment-links')->group(function () {
    Route::post('/generate', [PaymentLinkController::class, 'generate']);
    Route::post('/validate', [PaymentLinkController::class, 'validate']);
});

// Subscription Management API Routes
Route::prefix('subscriptions')->group(function () {
    Route::get('/', [SubscriptionController::class, 'index']);
    Route::post('/', [SubscriptionController::class, 'store']);
    Route::get('/{subscription}', [SubscriptionController::class, 'show']);
    Route::put('/{subscription}', [SubscriptionController::class, 'update']);
    
    // Subscription Actions
    Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/{subscription}/change-plan', [SubscriptionController::class, 'changePlan']);
    Route::post('/{subscription}/pause', [SubscriptionController::class, 'pause']);
    Route::post('/{subscription}/resume', [SubscriptionController::class, 'resume']);
    Route::post('/{subscription}/upgrade', [SubscriptionController::class, 'upgradePlan']);
    
    // Analytics
    Route::get('/analytics/overview', [SubscriptionController::class, 'analytics']);
    Route::get('/analytics/mrr', [SubscriptionController::class, 'mrr']);
});

// Customer Management API Routes
Route::prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::get('/{customer}', [CustomerController::class, 'show']);
    Route::put('/{customer}', [CustomerController::class, 'update']);
    
    // Customer Actions
    Route::post('/{customer}/block', [CustomerController::class, 'block']);
    Route::post('/{customer}/unblock', [CustomerController::class, 'unblock']);
    
    // Customer Analytics
    Route::get('/{customer}/analytics', [CustomerController::class, 'analytics']);
    Route::get('/{customer}/ltv-prediction', [CustomerController::class, 'ltvPrediction']);
    Route::get('/{customer}/events', [CustomerController::class, 'events']);
    Route::get('/{customer}/communications', [CustomerController::class, 'communications']);
    
    // Segmentation
    Route::get('/segments/overview', [CustomerController::class, 'segments']);
});

// Fraud Detection API Routes
Route::prefix('fraud')->group(function () {
    // Analysis
    Route::post('/analyze', [FraudDetectionController::class, 'analyzePayment']);
    Route::get('/statistics', [FraudDetectionController::class, 'statistics']);
    
    // Alerts
    Route::get('/alerts', [FraudDetectionController::class, 'alerts']);
    
    // Rules Management
    Route::get('/rules', [FraudDetectionController::class, 'rules']);
    Route::post('/rules', [FraudDetectionController::class, 'createRule']);
    Route::put('/rules/{rule}', [FraudDetectionController::class, 'updateRule']);
    
    // Blacklist Management
    Route::get('/blacklist', [FraudDetectionController::class, 'blacklist']);
    Route::post('/blacklist', [FraudDetectionController::class, 'addToBlacklist']);
    
    // Whitelist Management
    Route::get('/whitelist', [FraudDetectionController::class, 'whitelist']);
    Route::post('/whitelist', [FraudDetectionController::class, 'addToWhitelist']);
    
    // Risk Profiles
    Route::get('/risk-profiles', [FraudDetectionController::class, 'riskProfiles']);
});

// Analytics & Business Intelligence API Routes
Route::prefix('analytics')->group(function () {
    // Dashboard & Overview
    Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('/real-time', [AnalyticsController::class, 'realTime']);
    Route::get('/real-time-dashboard', [AnalyticsController::class, 'realTimeDashboard']);
    
    // Specific Analytics
    Route::get('/revenue', [AnalyticsController::class, 'revenue']);
    Route::get('/subscriptions', [AnalyticsController::class, 'subscriptions']);
    Route::get('/customers', [AnalyticsController::class, 'customers']);
    Route::get('/fraud', [AnalyticsController::class, 'fraud']);
    Route::get('/payment-subscription', [AnalyticsController::class, 'paymentSubscription']);
    
    // Advanced Analytics
    Route::get('/conversion-funnel', [AnalyticsController::class, 'conversionFunnel']);
    Route::get('/cohort-analysis', [AnalyticsController::class, 'cohortAnalysis']);
    Route::get('/customer-lifetime-value', [AnalyticsController::class, 'customerLifetimeValue']);
    Route::get('/churn-analysis', [AnalyticsController::class, 'churnAnalysis']);
    
    // Export
    Route::post('/export', [AnalyticsController::class, 'export']);
});

// Business Intelligence & Advanced Features API Routes
Route::prefix('business-intelligence')->group(function () {
    // Revenue Forecasting
    Route::get('/revenue-forecast', [BusinessIntelligenceController::class, 'revenueForecast']);
    Route::get('/ltv-analysis', [BusinessIntelligenceController::class, 'ltvAnalysis']);
    Route::get('/churn-prediction', [BusinessIntelligenceController::class, 'churnPrediction']);
    Route::get('/market-insights', [BusinessIntelligenceController::class, 'marketInsights']);
    Route::get('/business-insights', [BusinessIntelligenceController::class, 'businessInsights']);
    
    // Tax & Accounting
    Route::post('/calculate-tax', [BusinessIntelligenceController::class, 'calculateTax']);
    Route::get('/tax-report', [BusinessIntelligenceController::class, 'taxReport']);
    Route::get('/financial-report', [BusinessIntelligenceController::class, 'financialReport']);
    Route::post('/export-accounting', [BusinessIntelligenceController::class, 'exportAccounting']);
    Route::post('/sync-accounting', [BusinessIntelligenceController::class, 'syncAccounting']);
    
    // Security & Compliance
    Route::get('/pci-compliance-check', [BusinessIntelligenceController::class, 'pciComplianceCheck']);
    Route::get('/security-audit-report', [BusinessIntelligenceController::class, 'securityAuditReport']);
    Route::post('/detect-anomalies', [BusinessIntelligenceController::class, 'detectAnomalies']);
    
    // Performance & Scaling
    Route::post('/optimize-performance', [BusinessIntelligenceController::class, 'optimizePerformance']);
    Route::get('/database-optimization', [BusinessIntelligenceController::class, 'databaseOptimization']);
    Route::post('/multi-tenancy-setup', [BusinessIntelligenceController::class, 'multiTenancySetup']);
    Route::get('/performance-report', [BusinessIntelligenceController::class, 'performanceReport']);
    
    // System Health
    Route::get('/system-health', [BusinessIntelligenceController::class, 'systemHealth']);
});

// Stripe Product Management API Routes
Route::prefix('stripe')->group(function () {
    Route::get('/products', [StripeProductController::class, 'index']);
    Route::post('/products', [StripeProductController::class, 'store']);
    Route::post('/products/import', [StripeProductController::class, 'import']);
});

// Payment Verification API Routes
Route::prefix('payment')->group(function () {
    Route::get('/verify/{payment}', [PaymentVerificationController::class, 'verify'])->name('api.payment.verify');
    Route::post('/{payment}/abandon', [PaymentVerificationController::class, 'abandon'])->name('api.payment.abandon');
    Route::post('/{payment}/recover', [PaymentVerificationController::class, 'recover'])->name('api.payment.recover');
});

// Reports API Routes
Route::prefix('reports')->group(function () {
    // Existing simple summary endpoint (kept for compatibility)
    Route::get('/payment-subscription-summary', function () {
        return response()->json([
            'summary' => [
                'total_payments' => \App\Models\Payment::count(),
                'successful_payments' => \App\Models\Payment::where('status', 'completed')->count(),
                'failed_payments' => \App\Models\Payment::where('status', 'failed')->count(),
                'total_revenue' => \App\Models\Payment::where('status', 'completed')->sum('amount'),
                'active_subscriptions' => \App\Models\Subscription::where('status', 'active')->count(),
                'expired_subscriptions' => \App\Models\Subscription::where('status', 'expired')->count(),
                'cancelled_subscriptions' => \App\Models\Subscription::where('status', 'cancelled')->count()
            ],
            'payment_methods' => [
                'stripe' => \App\Models\Payment::where('payment_gateway', 'stripe')->count(),
                'paypal' => \App\Models\Payment::where('payment_gateway', 'paypal')->count()
            ],
            'subscription_metrics' => [
                'new_subscriptions' => \App\Models\Subscription::whereDate('created_at', '>=', now()->subDays(30))->count(),
                'renewals' => \App\Models\Payment::where('is_renewal', true)->count(),
                'cancellations' => \App\Models\Subscription::where('status', 'cancelled')->whereDate('updated_at', '>=', now()->subDays(30))->count(),
                'average_lifetime_value' => round(\App\Models\Payment::where('status', 'completed')->avg('amount'), 2)
            ]
        ]);
    });

    // New report endpoints used by tests
    Route::get('/payment-summary', [ReportsController::class, 'paymentSummary']);
    Route::get('/subscription-analytics', [ReportsController::class, 'subscriptionAnalytics']);
    Route::get('/revenue-analysis', [ReportsController::class, 'revenueAnalysis']);
    Route::get('/gateway-performance', [ReportsController::class, 'gatewayPerformance']);
    Route::get('/financial-reconciliation', [ReportsController::class, 'financialReconciliation']);
    Route::get('/payment-summary/export', [ReportsController::class, 'exportPaymentSummary']);
});

// Dashboard metrics endpoint
Route::prefix('dashboard')->group(function () {
    Route::get('/metrics', [DashboardController::class, 'metrics']);
});