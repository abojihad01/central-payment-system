<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Website;
use App\Models\GeneratedLink;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\Refund;
use App\Models\Invoice;
use App\Services\ReportService;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class ReportsAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected $website;
    protected $basicPlan;
    protected $premiumPlan;
    protected $generatedLink;
    protected $stripeAccount;
    protected $paypalAccount;
    protected $reportService;
    protected $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createTestEnvironment();
        $this->createTestData();
        
        $this->reportService = app(ReportService::class);
        $this->analyticsService = app(AnalyticsService::class);
    }

    /** @test */
    public function payment_summary_report()
    {
        // تنفيذ - إنشاء تقرير ملخص الدفعات
        $response = $this->getJson('/api/reports/payment-summary', [
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'group_by' => 'day'
        ]);
        
        // تحقق - بنية الاستجابة صحيحة
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'summary' => [
                         'total_payments',
                         'successful_payments',
                         'failed_payments',
                         'pending_payments',
                         'total_amount',
                         'average_amount',
                         'success_rate'
                     ],
                     'breakdown' => [
                         'by_gateway',
                         'by_amount_range',
                         'by_plan'
                     ],
                     'trends' => [
                         'daily_totals',
                         'success_rate_trend'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - دقة البيانات
        $this->assertEquals(15, $data['summary']['total_payments']); // 10 ناجحة + 3 فاشلة + 2 معلقة
        $this->assertEquals(10, $data['summary']['successful_payments']);
        $this->assertEquals(3, $data['summary']['failed_payments']);
        $this->assertEquals(2, $data['summary']['pending_payments']);
        $this->assertEquals(66.67, round($data['summary']['success_rate'], 2)); // 10/15 * 100
        
        // تحقق - تفصيل البوابات
        $this->assertArrayHasKey('stripe', $data['breakdown']['by_gateway']);
        $this->assertArrayHasKey('paypal', $data['breakdown']['by_gateway']);
    }

    /** @test */
    public function subscription_analytics_report()
    {
        // تنفيذ - إنشاء تقرير تحليلات الاشتراكات
        $response = $this->getJson('/api/reports/subscription-analytics', [
            'period' => 'last_30_days',
            'include_cohort_analysis' => true
        ]);
        
        // تحقق - البنية والبيانات
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'overview' => [
                         'total_subscriptions',
                         'active_subscriptions',
                         'expired_subscriptions',
                         'cancelled_subscriptions',
                         'monthly_recurring_revenue',
                         'annual_recurring_revenue',
                         'churn_rate',
                         'retention_rate'
                     ],
                     'lifecycle_metrics' => [
                         'new_subscriptions',
                         'renewals',
                         'upgrades',
                         'downgrades',
                         'reactivations'
                     ],
                     'plan_performance' => [
                         'most_popular_plan',
                         'highest_revenue_plan',
                         'conversion_rates_by_plan'
                     ],
                     'cohort_analysis' => [
                         'retention_by_month',
                         'revenue_by_cohort'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - المقاييس الأساسية
        $this->assertEquals(12, $data['overview']['total_subscriptions']);
        $this->assertEquals(8, $data['overview']['active_subscriptions']);
        $this->assertEquals(2, $data['overview']['expired_subscriptions']);
        $this->assertEquals(2, $data['overview']['cancelled_subscriptions']);
        
        // تحقق - الإيرادات المتكررة
        $this->assertGreaterThan(0, $data['overview']['monthly_recurring_revenue']);
        $this->assertIsNumeric($data['overview']['churn_rate']);
        $this->assertLessThanOrEqual(100, $data['overview']['churn_rate']);
    }

    /** @test */
    public function revenue_analysis_report()
    {
        // تنفيذ - تحليل الإيرادات
        $response = $this->getJson('/api/reports/revenue-analysis', [
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'breakdown' => ['plan', 'gateway', 'region']
        ]);
        
        // تحقق - تفصيل الإيرادات
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'total_revenue',
                     'net_revenue', // بعد خصم الاستردادات
                     'gross_revenue',
                     'refunded_amount',
                     'revenue_breakdown' => [
                         'by_plan',
                         'by_gateway',
                         'by_month'
                     ],
                     'growth_metrics' => [
                         'month_over_month_growth',
                         'year_over_year_growth',
                         'projected_annual_revenue'
                     ],
                     'payment_method_performance' => [
                         'stripe_revenue',
                         'paypal_revenue',
                         'processing_fees'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - حسابات الإيرادات
        $expectedGrossRevenue = 1199.9; // 10 دفعات ناجحة × 99.99 + 2 مميزة × 199.99
        $this->assertEquals($expectedGrossRevenue, $data['gross_revenue']);
        
        // الإيرادات الصافية = الإجمالية - الاستردادات
        $this->assertLessThanOrEqual($data['gross_revenue'], $data['net_revenue']);
        
        // تحقق - معدلات النمو
        $this->assertIsNumeric($data['growth_metrics']['month_over_month_growth']);
        $this->assertGreaterThan(0, $data['growth_metrics']['projected_annual_revenue']);
    }

    /** @test */
    public function customer_lifetime_value_analysis()
    {
        // تنفيذ - تحليل قيمة العميل مدى الحياة
        $response = $this->getJson('/api/analytics/customer-lifetime-value', [
            'segment_by' => ['plan', 'acquisition_channel', 'region'],
            'cohort_months' => 12
        ]);
        
        // تحقق - التحليل التفصيلي
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'overall_clv' => [
                         'average_clv',
                         'median_clv',
                         'clv_by_plan'
                     ],
                     'cohort_clv' => [
                         'by_signup_month',
                         'by_plan_type'
                     ],
                     'predictive_clv' => [
                         'projected_12_month',
                         'projected_24_month'
                     ],
                     'clv_factors' => [
                         'subscription_length_impact',
                         'plan_upgrade_impact',
                         'payment_method_impact'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - منطقية القيم
        $this->assertGreaterThan(0, $data['overall_clv']['average_clv']);
        $this->assertIsArray($data['cohort_clv']['by_signup_month']);
        $this->assertGreaterThan(0, $data['predictive_clv']['projected_12_month']);
    }

    /** @test */
    public function churn_analysis_report()
    {
        // تنفيذ - تحليل معدل الإلغاء
        $response = $this->getJson('/api/analytics/churn-analysis', [
            'period' => 'last_6_months',
            'segment_by' => ['plan', 'tenure', 'payment_failures']
        ]);
        
        // تحقق - تحليل شامل للإلغاء
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'churn_overview' => [
                         'overall_churn_rate',
                         'monthly_churn_rates',
                         'voluntary_vs_involuntary_churn'
                     ],
                     'churn_by_segment' => [
                         'by_plan',
                         'by_tenure',
                         'by_payment_method'
                     ],
                     'churn_reasons' => [
                         'top_cancellation_reasons',
                         'payment_failure_impact'
                     ],
                     'retention_insights' => [
                         'at_risk_customers',
                         'retention_recommendations'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - معدلات الإلغاء
        $this->assertIsNumeric($data['churn_overview']['overall_churn_rate']);
        $this->assertLessThanOrEqual(100, $data['churn_overview']['overall_churn_rate']);
        $this->assertIsArray($data['churn_by_segment']['by_plan']);
        $this->assertIsArray($data['churn_reasons']['top_cancellation_reasons']);
    }

    /** @test */
    public function payment_gateway_performance_report()
    {
        // تنفيذ - تقرير أداء بوابات الدفع
        $response = $this->getJson('/api/reports/gateway-performance', [
            'compare_gateways' => ['stripe', 'paypal'],
            'metrics' => ['success_rate', 'processing_time', 'fees', 'disputes']
        ]);
        
        // تحقق - مقارنة البوابات
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'gateway_comparison' => [
                         'stripe' => [
                             'total_transactions',
                             'success_rate',
                             'average_processing_time',
                             'total_fees',
                             'dispute_rate'
                         ],
                         'paypal' => [
                             'total_transactions',
                             'success_rate',
                             'average_processing_time',
                             'total_fees',
                             'dispute_rate'
                         ]
                     ],
                     'recommendations' => [
                         'preferred_gateway_by_amount',
                         'routing_optimization_suggestions'
                     ],
                     'cost_analysis' => [
                         'total_processing_fees',
                         'fees_by_gateway',
                         'potential_savings'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - بيانات Stripe
        $stripeData = $data['gateway_comparison']['stripe'];
        $this->assertGreaterThan(0, $stripeData['total_transactions']);
        $this->assertLessThanOrEqual(100, $stripeData['success_rate']);
        
        // تحقق - بيانات PayPal
        $paypalData = $data['gateway_comparison']['paypal'];
        $this->assertGreaterThan(0, $paypalData['total_transactions']);
        $this->assertIsNumeric($paypalData['success_rate']);
    }

    /** @test */
    public function financial_reconciliation_report()
    {
        // تنفيذ - تقرير المطابقة المالية
        $response = $this->getJson('/api/reports/financial-reconciliation', [
            'date' => now()->format('Y-m-d'),
            'include_pending' => false,
            'gateway_settlements' => true
        ]);
        
        // تحقق - مطابقة شاملة
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'reconciliation_summary' => [
                         'total_processed',
                         'total_settled',
                         'pending_settlement',
                         'discrepancies'
                     ],
                     'by_gateway' => [
                         'stripe' => [
                             'processed_amount',
                             'settled_amount',
                             'fees_deducted',
                             'net_settlement'
                         ],
                         'paypal' => [
                             'processed_amount',
                             'settled_amount',
                             'fees_deducted',
                             'net_settlement'
                         ]
                     ],
                     'discrepancy_details' => [
                         'missing_settlements',
                         'amount_mismatches',
                         'timing_differences'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - توازن المبالغ
        $totalProcessed = $data['reconciliation_summary']['total_processed'];
        $totalSettled = $data['reconciliation_summary']['total_settled'];
        $pendingSettlement = $data['reconciliation_summary']['pending_settlement'];
        
        $this->assertEquals($totalProcessed, $totalSettled + $pendingSettlement);
    }

    /** @test */
    public function custom_dashboard_metrics()
    {
        // تنفيذ - مقاييس لوحة القيادة المخصصة
        $response = $this->getJson('/api/dashboard/metrics', [
            'widgets' => [
                'revenue_today',
                'new_subscriptions_today',
                'active_users',
                'payment_success_rate_7d',
                'top_plans',
                'recent_transactions'
            ]
        ]);
        
        // تحقق - بيانات لوحة القيادة
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'widgets' => [
                         'revenue_today' => [
                             'value',
                             'change_from_yesterday',
                             'trend'
                         ],
                         'new_subscriptions_today' => [
                             'count',
                             'change_from_yesterday'
                         ],
                         'active_users' => [
                             'total',
                             'growth_rate'
                         ],
                         'payment_success_rate_7d' => [
                             'rate',
                             'trend_data'
                         ],
                         'top_plans' => [
                             'by_revenue',
                             'by_subscriptions'
                         ],
                         'recent_transactions' => [
                             'successful',
                             'failed',
                             'pending'
                         ]
                     ],
                     'alerts' => [
                         'low_success_rate',
                         'high_churn_detected',
                         'revenue_decline'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - القيم المعقولة
        $this->assertIsNumeric($data['widgets']['revenue_today']['value']);
        $this->assertIsArray($data['widgets']['top_plans']['by_revenue']);
        $this->assertLessThanOrEqual(100, $data['widgets']['payment_success_rate_7d']['rate']);
    }

    /** @test */
    public function advanced_cohort_analysis()
    {
        // تنفيذ - تحليل الفوج المتقدم
        $response = $this->getJson('/api/analytics/cohort-analysis', [
            'cohort_type' => 'monthly',
            'metric' => 'revenue_retention',
            'periods' => 12
        ]);
        
        // تحقق - تحليل الفوج
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'cohort_table' => [
                         'headers',
                         'rows'
                     ],
                     'cohort_insights' => [
                         'best_performing_cohort',
                         'worst_performing_cohort',
                         'average_retention_by_period'
                     ],
                     'retention_curves' => [
                         'by_cohort_month',
                         'average_curve'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - بنية جدول الفوج
        $this->assertIsArray($data['cohort_table']['headers']);
        $this->assertIsArray($data['cohort_table']['rows']);
        $this->assertNotEmpty($data['cohort_table']['rows']);
        
        // تحقق - رؤى الاحتفاظ
        $this->assertArrayHasKey('month', $data['cohort_insights']['best_performing_cohort']);
        $this->assertArrayHasKey('retention_rate', $data['cohort_insights']['best_performing_cohort']);
    }

    /** @test */
    public function export_functionality()
    {
        // تنفيذ - تصدير التقارير
        $exportFormats = ['csv', 'excel', 'pdf'];
        
        foreach ($exportFormats as $format) {
            $response = $this->getJson("/api/reports/payment-summary/export", [
                'format' => $format,
                'start_date' => now()->subDays(30)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d')
            ]);
            
            // تحقق - التصدير ناجح
            $response->assertStatus(200);
            
            // تحقق - نوع المحتوى صحيح
            switch ($format) {
                case 'csv':
                    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
                    break;
                case 'excel':
                    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    break;
                case 'pdf':
                    $response->assertHeader('Content-Type', 'application/pdf');
                    break;
            }
        }
    }

    /** @test */
    public function real_time_analytics_performance()
    {
        // ترتيب - إنشاء بيانات ضخمة للاختبار
        $this->createLargeDataset();
        
        // تنفيذ - قياس أداء الاستعلامات
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/analytics/real-time-dashboard');
        
        $queryTime = microtime(true) - $startTime;
        
        // تحقق - الاستجابة السريعة
        $response->assertStatus(200);
        $this->assertLessThan(3.0, $queryTime, 'يجب أن تكون الاستعلامات التحليلية أقل من 3 ثوان');
        
        // تحقق - دقة البيانات مع الحجم الكبير
        $data = $response->json();
        $this->assertArrayHasKey('metrics', $data);
        $this->assertGreaterThan(0, $data['metrics']['total_revenue']);
        
        echo "\nأداء التحليلات في الوقت الفعلي:\n";
        echo "- وقت الاستعلام: {$queryTime}s\n";
        echo "- عدد السجلات المعالجة: " . (Payment::count() + Subscription::count()) . "\n";
    }

    // Helper Methods

    protected function createTestEnvironment()
    {
        $this->website = Website::factory()->create(['name' => 'Test IPTV']);
        
        $this->basicPlan = Plan::factory()->create([
            'name' => 'Basic Plan',
            'price' => 99.99,
            'duration_days' => 30
        ]);
        
        $this->premiumPlan = Plan::factory()->create([
            'name' => 'Premium Plan',
            'price' => 199.99,
            'duration_days' => 30
        ]);
        
        $this->generatedLink = GeneratedLink::factory()->create([
            'website_id' => $this->website->id,
            'plan_id' => $this->basicPlan->id
        ]);
        
        // إنشاء حسابات الدفع
        $stripeGateway = PaymentGateway::factory()->create(['name' => 'stripe']);
        $this->stripeAccount = PaymentAccount::factory()->create([
            'payment_gateway_id' => $stripeGateway->id
        ]);
        
        $paypalGateway = PaymentGateway::factory()->create(['name' => 'paypal']);
        $this->paypalAccount = PaymentAccount::factory()->create([
            'payment_gateway_id' => $paypalGateway->id
        ]);
    }

    protected function createTestData()
    {
        // إنشاء دفعات ناجحة متنوعة
        for ($i = 0; $i < 8; $i++) {
            $payment = Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->stripeAccount->id,
                'plan_id' => $this->basicPlan->id,
                'amount' => $this->basicPlan->price,
                'status' => 'completed',
                'confirmed_at' => now()->subDays(rand(1, 30)),
                'customer_email' => "customer{$i}@example.com"
            ]);
            
            // إنشاء اشتراك لكل دفعة ناجحة
            Subscription::factory()->create([
                'payment_id' => $payment->id,
                'plan_id' => $this->basicPlan->id,
                'website_id' => $this->website->id,
                'customer_email' => $payment->customer_email,
                'status' => 'active',
                'starts_at' => $payment->confirmed_at,
                'expires_at' => $payment->confirmed_at->addDays(30)
            ]);
        }
        
        // إنشاء دفعات مميزة
        for ($i = 0; $i < 2; $i++) {
            $payment = Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->paypalAccount->id,
                'plan_id' => $this->premiumPlan->id,
                'amount' => $this->premiumPlan->price,
                'status' => 'completed',
                'confirmed_at' => now()->subDays(rand(1, 15)),
                'customer_email' => "premium{$i}@example.com"
            ]);
            
            Subscription::factory()->create([
                'payment_id' => $payment->id,
                'plan_id' => $this->premiumPlan->id,
                'website_id' => $this->website->id,
                'customer_email' => $payment->customer_email,
                'status' => 'active'
            ]);
        }
        
        // إنشاء دفعات فاشلة
        for ($i = 0; $i < 3; $i++) {
            Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->stripeAccount->id,
                'amount' => $this->basicPlan->price,
                'status' => 'failed',
                'customer_email' => "failed{$i}@example.com"
            ]);
        }
        
        // إنشاء دفعات معلقة
        for ($i = 0; $i < 2; $i++) {
            Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->stripeAccount->id,
                'amount' => $this->basicPlan->price,
                'status' => 'pending',
                'customer_email' => "pending{$i}@example.com"
            ]);
        }
        
        // إنشاء اشتراكات منتهية ومُلغاة
        for ($i = 0; $i < 2; $i++) {
            $expiredPayment = Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->stripeAccount->id,
                'amount' => $this->basicPlan->price,
                'status' => 'completed',
                'confirmed_at' => now()->subDays(60)
            ]);
            
            Subscription::factory()->create([
                'payment_id' => $expiredPayment->id,
                'plan_id' => $this->basicPlan->id,
                'website_id' => $this->website->id,
                'customer_email' => $expiredPayment->customer_email,
                'status' => $i === 0 ? 'expired' : 'cancelled',
                'expires_at' => now()->subDays(30),
                'expired_at' => $i === 0 ? now()->subDays(30) : null,
                'cancelled_at' => $i === 1 ? now()->subDays(15) : null
            ]);
        }
        
        // إنشاء استردادات
        $refundPayment = Payment::factory()->create([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->stripeAccount->id,
            'amount' => -49.99,
            'type' => 'refund',
            'status' => 'completed',
            'customer_email' => 'refund@example.com'
        ]);
    }

    protected function createLargeDataset()
    {
        // إنشاء مجموعة بيانات كبيرة لاختبار الأداء
        for ($i = 0; $i < 1000; $i++) {
            Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => rand(0, 1) ? $this->stripeAccount->id : $this->paypalAccount->id,
                'amount' => rand(0, 1) ? $this->basicPlan->price : $this->premiumPlan->price,
                'status' => ['completed', 'failed', 'pending'][rand(0, 2)],
                'created_at' => now()->subDays(rand(1, 365))
            ]);
        }
    }
}