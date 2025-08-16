<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Website;
use App\Models\GeneratedLink;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use App\Models\Invoice;
use App\Models\Refund;
use App\Jobs\ProcessPendingPayment;
use App\Jobs\ProcessSubscriptionRenewal;
use App\Events\PaymentCompleted;
use App\Events\SubscriptionCreated;
use App\Events\PaymentFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Carbon\Carbon;
use Mockery;

class PaymentSubscriptionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $website;
    protected $basicPlan;
    protected $premiumPlan;
    protected $generatedLink;
    protected $paymentAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        Event::fake();
        Queue::fake();
        
        $this->createTestEnvironment();
    }

    /** @test */
    public function successful_payment_creates_subscription_and_invoice()
    {
        // ترتيب - إنشاء دفعة معلقة
        $payment = $this->createPendingPayment();
        
        // محاكاة نجاح Stripe
        \Tests\TestMockState::clearMockBehavior();
        
        // تنفيذ - معالجة الدفع
        $job = new ProcessPendingPayment($payment);
        $job->handle();
        
        // تحقق - تم إكمال الدفع
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->confirmed_at);
        
        // تحقق - تم إنشاء الاشتراك
        $subscription = Subscription::where('payment_id', $payment->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($payment->customer_email, $subscription->customer_email);
        
        // تحقق - تم إنشاء الفاتورة
        $invoice = Invoice::where('payment_id', $payment->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($payment->amount, $invoice->amount);
        $this->assertEquals('paid', $invoice->status);
        
        // تحقق - تم إرسال الأحداث
        Event::assertDispatched(PaymentCompleted::class);
        Event::assertDispatched(SubscriptionCreated::class);
    }

    /** @test */
    public function failed_payment_prevents_subscription_creation()
    {
        // ترتيب - إنشاء دفعة معلقة
        $payment = $this->createPendingPayment();
        
        // محاكاة فشل Stripe
        \Tests\TestMockState::setMockBehavior('failure');
        
        // تنفيذ - معالجة الدفع
        $job = new ProcessPendingPayment($payment);
        $job->handle();
        
        // تحقق - فشل الدفع (يبقى pending للfallback)
        $payment->refresh();
        $this->assertEquals('pending', $payment->status);
        
        // تحقق - لم يتم إنشاء اشتراك
        $subscription = Subscription::where('payment_id', $payment->id)->first();
        $this->assertNull($subscription);
        
        // تحقق - لم يتم إنشاء فاتورة (الدفع معلق للfallback)
        $invoice = Invoice::where('payment_id', $payment->id)->first();
        $this->assertNull($invoice);
        
        // تحقق - تم إرسال حدث فشل الدفع
        Event::assertDispatched(PaymentFailed::class);
        Event::assertNotDispatched(SubscriptionCreated::class);
        
        // تنظيف
        \Tests\TestMockState::clearMockBehavior();
    }

    /** @test */
    public function subscription_renewal_payment_workflow()
    {
        // ترتيب - إنشاء اشتراك نشط قارب على الانتهاء
        $subscription = $this->createActiveSubscription([
            'expires_at' => now()->addDays(2)
        ]);
        
        // إنشاء دفعة تجديد
        $renewalPayment = Payment::factory()->create([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'subscription_id' => $subscription->id,
            'amount' => $this->basicPlan->price,
            'currency' => $this->basicPlan->currency,
            'status' => 'pending',
            'customer_email' => $subscription->customer_email,
            'payment_gateway' => 'stripe',
            'gateway_payment_id' => 'pi_renewal_123',
            'is_renewal' => true
        ]);
        
        // Clear any existing mock state and use simple success behavior
        \Tests\TestMockState::clearMockBehavior();
        
        // تنفيذ - معالجة دفعة التجديد
        $job = new ProcessPendingPayment($renewalPayment);
        $job->handle();
        
        // تحقق - تم إكمال دفعة التجديد
        $renewalPayment->refresh();
        $this->assertEquals('completed', $renewalPayment->status);
        
        // تحقق - تم تجديد الاشتراك
        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertTrue($subscription->expires_at->greaterThan(now()->addDays(25))); // تم التمديد
        
        // تحقق - تم إنشاء فاتورة التجديد
        $renewalInvoice = Invoice::where('payment_id', $renewalPayment->id)->first();
        $this->assertNotNull($renewalInvoice);
        $this->assertEquals('renewal', $renewalInvoice->type);
        
        // تحقق - تم إرسال أحداث التجديد
        Event::assertDispatched(PaymentCompleted::class);
    }

    /** @test */
    public function subscription_upgrade_with_prorated_payment()
    {
        // ترتيب - إنشاء اشتراك نشط بخطة أساسية
        $subscription = $this->createActiveSubscription([
            'plan_id' => $this->basicPlan->id,
            'expires_at' => now()->addDays(15) // 15 يوم متبقي
        ]);
        
        // تنفيذ - طلب ترقية للخطة المميزة
        $response = $this->postJson("/api/subscriptions/{$subscription->id}/upgrade", [
            'new_plan_id' => $this->premiumPlan->id,
            'apply_proration' => true
        ]);
        
        // تحقق - نجح طلب الترقية
        $response->assertStatus(200);
        $responseData = $response->json();
        
        $this->assertArrayHasKey('prorated_amount', $responseData);
        $this->assertGreaterThan(0, $responseData['prorated_amount']);
        
        // تحقق - تم إنشاء دفعة التناسب
        $proratedPayment = Payment::where('subscription_id', $subscription->id)
                                 ->where('type', 'upgrade')
                                 ->first();
        
        $this->assertNotNull($proratedPayment);
        $this->assertEquals($responseData['prorated_amount'], $proratedPayment->amount);
        $this->assertEquals('pending', $proratedPayment->status);
        
        // محاكاة إكمال دفعة التناسب
        $this->mockStripeSuccess();
        $job = new ProcessPendingPayment($proratedPayment);
        $job->handle();
        
        // تحقق - تم تطبيق الترقية
        $subscription->refresh();
        $proratedPayment->refresh();
        
        $this->assertEquals($this->premiumPlan->id, $subscription->plan_id);
        $this->assertEquals('completed', $proratedPayment->status);
        
        // تحقق - تم إنشاء فاتورة الترقية
        $upgradeInvoice = Invoice::where('payment_id', $proratedPayment->id)->first();
        $this->assertNotNull($upgradeInvoice);
        $this->assertEquals('upgrade', $upgradeInvoice->type);
    }

    /** @test */
    public function subscription_cancellation_with_refund()
    {
        // ترتيب - إنشاء اشتراك نشط مدفوع حديثاً
        $subscription = $this->createActiveSubscription([
            'created_at' => now()->subDays(5),
            'expires_at' => now()->addDays(25)
        ]);
        
        $originalPayment = $subscription->payment;
        
        // تنفيذ - طلب إلغاء مع استرداد
        $response = $this->postJson("/api/subscriptions/{$subscription->id}/cancel", [
            'reason' => 'not_satisfied',
            'request_refund' => true,
            'refund_percentage' => 80 // استرداد 80%
        ]);
        
        // تحقق - نجح طلب الإلغاء
        $response->assertStatus(200);
        
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        
        // تحقق - تم إنشاء طلب استرداد
        $refund = Refund::where('payment_id', $originalPayment->id)->first();
        $this->assertNotNull($refund);
        
        $expectedRefundAmount = $originalPayment->amount * 0.8;
        $this->assertEquals($expectedRefundAmount, $refund->amount);
        $this->assertEquals('pending', $refund->status);
        
        // محاكاة معالجة الاسترداد
        $this->mockStripeRefundSuccess();
        $refund->process();
        
        // تحقق - تم إكمال الاسترداد
        $refund->refresh();
        $this->assertEquals('completed', $refund->status);
        
        // تحقق - تم إنشاء دفعة استرداد سالبة
        $refundPayment = Payment::where('subscription_id', $subscription->id)
                               ->where('amount', '<', 0)
                               ->first();
        
        $this->assertNotNull($refundPayment);
        $this->assertEquals(-$expectedRefundAmount, $refundPayment->amount);
        $this->assertEquals('refund', $refundPayment->type);
    }

    /** @test */
    public function multiple_payment_gateways_for_same_subscription()
    {
        // ترتيب - إنشاء حسابات دفع متعددة
        $paypalGateway = PaymentGateway::factory()->create(['name' => 'paypal']);
        $paypalAccount = PaymentAccount::factory()->create([
            'payment_gateway_id' => $paypalGateway->id,
            'credentials' => [
                'client_id' => 'paypal_client',
                'client_secret' => 'paypal_secret'
            ]
        ]);
        
        // إنشاء اشتراك بدفعة Stripe
        $subscription = $this->createActiveSubscription();
        
        // إنشاء دفعة تجديد بـ PayPal
        $paypalRenewalPayment = Payment::factory()->create([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $paypalAccount->id,
            'subscription_id' => $subscription->id,
            'payment_gateway' => 'paypal',
            'gateway_session_id' => 'PAYPAL_ORDER_123',
            'amount' => $this->basicPlan->price,
            'status' => 'pending',
            'customer_email' => $subscription->customer_email,
            'is_renewal' => true
        ]);
        
        // محاكاة نجاح PayPal
        $this->mockPayPalSuccess();
        
        // تنفيذ - معالجة دفعة PayPal
        $job = new ProcessPendingPayment($paypalRenewalPayment);
        $job->handle();
        
        // تحقق - تم قبول الدفعة من بوابة مختلفة
        $paypalRenewalPayment->refresh();
        $this->assertEquals('completed', $paypalRenewalPayment->status);
        
        // تحقق - تم تجديد الاشتراك بنجاح
        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        
        // تحقق - توجد دفعات من بوابتين مختلفتين
        $stripePayments = Payment::where('subscription_id', $subscription->id)
                                ->where('payment_gateway', 'stripe')
                                ->count();
        $paypalPayments = Payment::where('subscription_id', $subscription->id)
                                ->where('payment_gateway', 'paypal')
                                ->count();
        
        $this->assertGreaterThan(0, $stripePayments);
        $this->assertGreaterThan(0, $paypalPayments);
    }

    /** @test */
    public function failed_renewal_payment_handling()
    {
        // ترتيب - إنشاء اشتراك قارب على الانتهاء
        $subscription = $this->createActiveSubscription([
            'expires_at' => now()->addHours(12)
        ]);
        
        // إنشاء دفعة تجديد
        $renewalPayment = Payment::factory()->create([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'subscription_id' => $subscription->id,
            'amount' => $this->basicPlan->price,
            'status' => 'pending',
            'customer_email' => $subscription->customer_email,
            'is_renewal' => true
        ]);
        
        // محاكاة فشل الدفع
        $this->mockStripeFailure();
        
        // تنفيذ - معالجة دفعة التجديد الفاشلة
        $job = new ProcessPendingPayment($renewalPayment);
        $job->handle();
        
        // تحقق - فشلت دفعة التجديد
        $renewalPayment->refresh();
        $this->assertEquals('failed', $renewalPayment->status);
        
        // تنفيذ - تشغيل معالج انتهاء الاشتراكات
        Carbon::setTestNow($subscription->expires_at->addHour());
        $this->artisan('subscriptions:check-expired');
        
        // تحقق - انتهت صلاحية الاشتراك بسبب فشل التجديد
        $subscription->refresh();
        $this->assertEquals('expired', $subscription->status);
        $this->assertNotNull($subscription->expired_at);
        
        // تحقق - تم تسجيل سبب انتهاء الصلاحية
        $this->assertStringContainsString('renewal payment failed', $subscription->expiration_reason);
    }

    /** @test */
    public function payment_dispute_handling()
    {
        // ترتيب - إنشاء اشتراك نشط
        $subscription = $this->createActiveSubscription();
        $originalPayment = $subscription->payment;
        
        // محاكاة استلام webhook بخصوص نزاع
        $disputeData = [
            'type' => 'payment_intent.dispute_created',
            'data' => [
                'object' => [
                    'id' => 'dp_test_123',
                    'payment_intent' => $originalPayment->gateway_payment_id,
                    'amount' => $originalPayment->amount * 100, // Stripe uses cents
                    'reason' => 'fraudulent',
                    'status' => 'needs_response'
                ]
            ]
        ];
        
        // تنفيذ - معالجة webhook النزاع
        $response = $this->postJson('/api/webhooks/stripe', $disputeData);
        
        // تحقق - تم تسجيل النزاع
        $response->assertStatus(200);
        
        $originalPayment->refresh();
        $this->assertEquals('disputed', $originalPayment->status);
        $this->assertNotNull($originalPayment->dispute_id);
        $this->assertEquals('dp_test_123', $originalPayment->dispute_id);
        
        // تحقق - تم تعليق الاشتراك
        $subscription->refresh();
        $this->assertEquals('suspended', $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
        $this->assertEquals('payment_dispute', $subscription->suspension_reason);
    }

    /** @test */
    public function comprehensive_payment_subscription_analytics()
    {
        // ترتيب - إنشاء بيانات متنوعة للتحليل
        $this->createAnalyticsTestData();
        
        // تنفيذ - الحصول على تحليلات شاملة
        $response = $this->getJson('/api/analytics/payment-subscription', [
            'period' => 'last_30_days',
            'include_details' => true
        ]);
        
        // تحقق - البيانات التحليلية شاملة
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'summary' => [
                         'total_revenue',
                         'successful_payments',
                         'failed_payments',
                         'active_subscriptions',
                         'new_subscriptions',
                         'cancelled_subscriptions',
                         'churn_rate',
                         'average_revenue_per_user'
                     ],
                     'payment_breakdown' => [
                         'by_gateway',
                         'by_plan',
                         'by_amount_range'
                     ],
                     'subscription_metrics' => [
                         'lifetime_value',
                         'renewal_rate',
                         'upgrade_rate',
                         'downgrade_rate'
                     ],
                     'trends' => [
                         'daily_revenue',
                         'weekly_signups',
                         'monthly_churn'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - منطقية البيانات
        $this->assertIsNumeric($data['summary']['total_revenue']);
        $this->assertGreaterThanOrEqual(0, $data['summary']['churn_rate']);
        $this->assertLessThanOrEqual(100, $data['summary']['churn_rate']);
        $this->assertIsArray($data['payment_breakdown']['by_gateway']);
        $this->assertIsArray($data['trends']['daily_revenue']);
    }

    // Helper Methods

    protected function createTestEnvironment()
    {
        $this->website = Website::factory()->create();
        
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
        
        $gateway = PaymentGateway::factory()->create(['name' => 'stripe']);
        $this->paymentAccount = PaymentAccount::factory()->create([
            'payment_gateway_id' => $gateway->id,
            'credentials' => [
                'secret_key' => 'sk_test_123',
                'publishable_key' => 'pk_test_123'
            ]
        ]);
    }

    protected function createPendingPayment($attributes = [])
    {
        return Payment::factory()->create(array_merge([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'amount' => $this->basicPlan->price,
            'currency' => $this->basicPlan->currency,
            'status' => 'pending',
            'customer_email' => 'test@example.com',
            'gateway_payment_id' => 'pi_test_123'
        ], $attributes));
    }

    protected function createActiveSubscription($attributes = [])
    {
        $payment = Payment::factory()->create([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'amount' => $this->basicPlan->price,
            'status' => 'completed',
            'confirmed_at' => now(),
            'customer_email' => 'success@example.com'
        ]);
        
        return Subscription::factory()->create(array_merge([
            'payment_id' => $payment->id,
            'plan_id' => $this->basicPlan->id,
            'website_id' => $this->website->id,
            'customer_email' => $payment->customer_email,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays($this->basicPlan->duration_days)
        ], $attributes));
    }

    protected function createAnalyticsTestData()
    {
        // إنشاء دفعات ناجحة متنوعة
        for ($i = 0; $i < 10; $i++) {
            $payment = Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->paymentAccount->id,
                'amount' => rand(50, 200),
                'status' => 'completed',
                'confirmed_at' => now()->subDays(rand(1, 30)),
                'customer_email' => "customer{$i}@example.com"
            ]);
            
            Subscription::factory()->create([
                'payment_id' => $payment->id,
                'plan_id' => rand(0, 1) ? $this->basicPlan->id : $this->premiumPlan->id,
                'website_id' => $this->website->id,
                'customer_email' => $payment->customer_email,
                'status' => 'active'
            ]);
        }
        
        // إنشاء دفعات فاشلة
        for ($i = 0; $i < 3; $i++) {
            Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->paymentAccount->id,
                'status' => 'failed',
                'customer_email' => "failed{$i}@example.com"
            ]);
        }
        
        // إنشاء اشتراكات ملغية
        for ($i = 0; $i < 2; $i++) {
            $subscription = $this->createActiveSubscription();
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now()->subDays(rand(1, 15))
            ]);
        }
    }

    protected function mockStripeSuccess()
    {
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $paymentIntentMock = Mockery::mock();
        $paymentIntentMock->status = 'succeeded';
        
        $stripeMock->shouldReceive('__construct');
        $stripeMock->paymentIntents = Mockery::mock();
        $stripeMock->paymentIntents->shouldReceive('retrieve')->andReturn($paymentIntentMock);
    }

    protected function mockStripeFailure()
    {
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $paymentIntentMock = Mockery::mock();
        $paymentIntentMock->status = 'payment_failed';
        
        $stripeMock->shouldReceive('__construct');
        $stripeMock->paymentIntents = Mockery::mock();
        $stripeMock->paymentIntents->shouldReceive('retrieve')->andReturn($paymentIntentMock);
    }

    protected function mockStripeRefundSuccess()
    {
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $refundMock = Mockery::mock();
        $refundMock->status = 'succeeded';
        
        $stripeMock->shouldReceive('__construct');
        $stripeMock->refunds = Mockery::mock();
        $stripeMock->refunds->shouldReceive('create')->andReturn($refundMock);
    }

    protected function mockPayPalSuccess()
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'test_token'
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/*' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [
                    ['payments' => ['captures' => [['status' => 'COMPLETED']]]]
                ]
            ], 200)
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}