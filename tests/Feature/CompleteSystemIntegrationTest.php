<?php

namespace Tests\Feature;

use App\Jobs\ProcessPendingPayment;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use App\Models\GeneratedLink;
use App\Models\Website;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Customer;
use App\Notifications\PaymentCompleted;
use App\Notifications\SubscriptionActivated;
use App\Notifications\SubscriptionExpired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Carbon\Carbon;
use Mockery;

class CompleteSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $website;
    protected $plan;
    protected $generatedLink;
    protected $stripeAccount;
    protected $paypalAccount;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // تعطيل الإشعارات والأحداث للاختبار
        Notification::fake();
        Event::fake();
        Mail::fake();
        
        $this->createCompleteTestEnvironment();
    }

    /** @test */
    public function complete_payment_and_subscription_creation_workflow()
    {
        // ترتيب - إنشاء دفعة جديدة
        $customerEmail = 'customer@example.com';
        $payment = $this->createPendingPayment($customerEmail);
        
        // محاكاة استجابة Stripe ناجحة
        $this->mockStripeSuccessfulResponse();
        
        // تنفيذ - معالجة الدفع في الخلفية
        Queue::fake();
        config(['queue.default' => 'sync']); // استخدام المعالجة المتزامنة للاختبار
        
        $job = new ProcessPendingPayment($payment);
        $job->handle();
        
        // تحقق - الدفع تم بنجاح
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->confirmed_at);
        
        // تحقق - تم إنشاء الاشتراك
        $subscription = Subscription::where('payment_id', $payment->id)->first();
        $this->assertNotNull($subscription, 'يجب إنشاء اشتراك بعد نجاح الدفع');
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($customerEmail, $subscription->customer_email);
        $this->assertEquals($this->plan->id, $subscription->plan_id);
        $this->assertEquals($this->website->id, $subscription->website_id);
        
        // تحقق - تواريخ الاشتراك صحيحة
        $expectedEndDate = now()->addDays($this->plan->duration_days);
        $this->assertEquals(
            $expectedEndDate->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d')
        );
        
        // تحقق - تم إرسال الإشعارات
        Notification::assertSentTo(
            $subscription,
            SubscriptionActivated::class
        );
    }

    /** @test */
    public function subscription_renewal_workflow()
    {
        // ترتيب - إنشاء اشتراك نشط قارب على الانتهاء
        $subscription = $this->createActiveSubscription([
            'expires_at' => now()->addDays(3) // ينتهي خلال 3 أيام
        ]);
        
        // إنشاء دفعة تجديد
        $renewalPayment = $this->createPendingPayment($subscription->customer_email, [
            'subscription_id' => $subscription->id,
            'amount' => $this->plan->price,
            'is_renewal' => true
        ]);
        
        $this->mockStripeSuccessfulResponse();
        
        // تنفيذ - معالجة دفعة التجديد
        $job = new ProcessPendingPayment($renewalPayment);
        $job->handle();
        
        // تحقق - تم تجديد الاشتراك
        $subscription->refresh();
        $renewalPayment->refresh();
        
        $this->assertEquals('completed', $renewalPayment->status);
        $this->assertEquals('active', $subscription->status);
        
        // تحقق - تم تمديد تاريخ الانتهاء
        $expectedNewEndDate = now()->addDays(3)->addDays($this->plan->duration_days);
        $this->assertEquals(
            $expectedNewEndDate->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d')
        );
    }

    /** @test */
    public function failed_payment_handling_and_subscription_impact()
    {
        // ترتيب - إنشاء دفعة
        $payment = $this->createPendingPayment('failed@example.com');
        
        // محاكاة فشل Stripe
        $this->mockStripeFailure();
        
        // تنفيذ - معالجة الدفع الفاشل
        $job = new ProcessPendingPayment($payment);
        $job->handle();
        
        // تحقق - الدفع فشل
        $payment->refresh();
        $this->assertEquals('failed', $payment->status);
        
        // تحقق - لم يتم إنشاء اشتراك
        $subscription = Subscription::where('payment_id', $payment->id)->first();
        $this->assertNull($subscription, 'يجب عدم إنشاء اشتراك عند فشل الدفع');
        
        // تحقق - تم تسجيل سبب الفشل
        $this->assertStringContainsString('Failed in background verification', $payment->notes ?? '');
    }

    /** @test */
    public function subscription_expiration_workflow()
    {
        // ترتيب - إنشاء اشتراك منتهي الصلاحية
        $expiredSubscription = $this->createActiveSubscription([
            'expires_at' => now()->subDays(1) // انتهى بالأمس
        ]);
        
        // تنفيذ - تشغيل أمر انتهاء الاشتراكات
        $this->artisan('subscriptions:check-expired')
             ->assertExitCode(0);
        
        // تحقق - تم تحديث حالة الاشتراك
        $expiredSubscription->refresh();
        $this->assertEquals('expired', $expiredSubscription->status);
        
        // تحقق - تم إرسال إشعار انتهاء الصلاحية
        Notification::assertSentTo(
            $expiredSubscription,
            SubscriptionExpired::class
        );
    }

    /** @test */
    public function multi_gateway_payment_workflow()
    {
        // ترتيب - إنشاء دفعات بطرق دفع مختلفة
        $stripePayment = $this->createPendingPayment('stripe@example.com', [
            'payment_account_id' => $this->stripeAccount->id,
            'payment_gateway' => 'stripe'
        ]);
        
        $paypalPayment = $this->createPendingPayment('paypal@example.com', [
            'payment_account_id' => $this->paypalAccount->id,
            'payment_gateway' => 'paypal',
            'gateway_session_id' => 'PAYPAL_ORDER_123'
        ]);
        
        // محاكاة استجابات ناجحة لكلا البوابتين
        $this->mockStripeSuccessfulResponse();
        $this->mockPayPalSuccessfulResponse();
        
        // تنفيذ - معالجة كلا الدفعتين
        $stripeJob = new ProcessPendingPayment($stripePayment);
        $stripeJob->handle();
        
        $paypalJob = new ProcessPendingPayment($paypalPayment);
        $paypalJob->handle();
        
        // تحقق - نجح كلا الدفعتين
        $stripePayment->refresh();
        $paypalPayment->refresh();
        
        $this->assertEquals('completed', $stripePayment->status);
        $this->assertEquals('completed', $paypalPayment->status);
        
        // تحقق - تم إنشاء اشتراكين
        $stripeSubscription = Subscription::where('payment_id', $stripePayment->id)->first();
        $paypalSubscription = Subscription::where('payment_id', $paypalPayment->id)->first();
        
        $this->assertNotNull($stripeSubscription);
        $this->assertNotNull($paypalSubscription);
        $this->assertEquals('active', $stripeSubscription->status);
        $this->assertEquals('active', $paypalSubscription->status);
    }

    /** @test */
    public function subscription_cancellation_and_refund_workflow()
    {
        // ترتيب - إنشاء اشتراك نشط
        $subscription = $this->createActiveSubscription();
        $originalPayment = $subscription->payment;
        
        // تنفيذ - إلغاء الاشتراك مع استرداد
        $cancellationData = [
            'reason' => 'customer_request',
            'refund_amount' => $originalPayment->amount * 0.5, // استرداد 50%
            'effective_date' => now()
        ];
        
        $response = $this->postJson("/api/subscriptions/{$subscription->id}/cancel", $cancellationData);
        
        // تحقق - تم إلغاء الاشتراك
        $response->assertStatus(200);
        
        $subscription->refresh();
        
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertEquals('customer_request', $subscription->cancellation_reason);
        
        // تحقق - تم إنشاء دفعة استرداد
        $refundPayment = Payment::where('subscription_id', $subscription->id)
                               ->where('amount', '<', 0)
                               ->first();
        
        $this->assertNotNull($refundPayment, 'يجب إنشاء دفعة استرداد');
        $this->assertEquals(round(-($originalPayment->amount * 0.5), 2), (float)$refundPayment->amount);
        $this->assertEquals('refund', $refundPayment->type);
    }

    /** @test */
    public function subscription_upgrade_downgrade_workflow()
    {
        // ترتيب - إنشاء خطة أخرى بسعر مختلف
        $premiumPlan = Plan::factory()->create([
            'name' => 'Premium Plan',
            'price' => 199.99,
            'duration_days' => 30
        ]);
        
        $subscription = $this->createActiveSubscription();
        $originalPlan = $subscription->plan;
        
        // تنفيذ - ترقية الاشتراك
        $upgradeData = [
            'new_plan_id' => $premiumPlan->id,
            'prorate' => true
        ];
        
        $response = $this->postJson("/api/subscriptions/{$subscription->id}/change-plan", $upgradeData);
        
        // تحقق - تم تغيير الخطة
        $response->assertStatus(200);
        $subscription->refresh();
        
        $this->assertEquals($premiumPlan->id, $subscription->plan_id);
        
        // تحقق - تم إنشاء دفعة التناسب (prorated payment)
        $proratedPayment = Payment::where('subscription_id', $subscription->id)
                                 ->where('type', 'upgrade')
                                 ->first();
        
        $this->assertNotNull($proratedPayment, 'يجب إنشاء دفعة تناسب للترقية');
        $this->assertGreaterThan(0, $proratedPayment->amount);
    }

    /** @test */
    public function bulk_subscription_management()
    {
        // ترتيب - إنشاء عدة اشتراكات
        $subscriptions = collect();
        for ($i = 0; $i < 10; $i++) {
            $subscriptions->push($this->createActiveSubscription([
                'customer_email' => "customer{$i}@example.com"
            ]));
        }
        
        // بعض الاشتراكات ستنتهي قريباً
        $expiringSubscriptions = $subscriptions->take(3);
        foreach ($expiringSubscriptions as $sub) {
            $sub->update(['expires_at' => now()->addDays(2)]);
        }
        
        // تنفيذ - تشغيل أمر إشعارات انتهاء الصلاحية
        $this->artisan('subscriptions:notify-expiring')
             ->assertExitCode(0);
        
        // تحقق - تم إرسال إشعارات للاشتراكات المنتهية قريباً
        Notification::assertSentTimes(
            \App\Notifications\SubscriptionExpiringSoon::class,
            3
        );
        
        // إنشاء دفعات pending للاختبار مع تعديل التوقيت
        $pendingPayments = collect();
        for ($i = 0; $i < 5; $i++) {
            $payment = $this->createPendingPayment("pending{$i}@example.com");
            // تعديل كلا من created_at و updated_at ليكونا قديمين باستخدام DB مباشرة
            $oldTime = now()->subMinutes(15);
            \DB::table('payments')
                ->where('id', $payment->id)
                ->update([
                    'created_at' => $oldTime,
                    'updated_at' => $oldTime
                ]);
            $payment->refresh(); // إعادة تحميل البيانات من قاعدة البيانات
            $pendingPayments->push($payment);
        }
        
        // تحقق من وجود الدفعات في قاعدة البيانات
        $actualPendingCount = \App\Models\Payment::where('status', 'pending')->count();
        $this->assertEquals(5, $actualPendingCount, 'يجب أن يكون هناك 5 دفعات pending في قاعدة البيانات');
        
        // تنفيذ - معالجة دفعات متعددة
        Queue::fake();
        $this->artisan('payments:verify-pending', [
            '--limit' => 50,
            '--min-age' => 0
        ])->assertExitCode(0);
        
        // تحقق - تم معالجة الدفعات
        Queue::assertPushed(ProcessPendingPayment::class, 5);
    }

    /** @test */
    public function system_analytics_and_reporting()
    {
        // ترتيب - إنشاء بيانات متنوعة
        $this->createReportingTestData();
        
        // تنفيذ - إنشاء تقرير شامل
        $response = $this->getJson('/api/reports/payment-subscription-summary', [
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d')
        ]);
        
        // تحقق - بنية التقرير صحيحة
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'summary' => [
                         'total_payments',
                         'successful_payments',
                         'failed_payments',
                         'total_revenue',
                         'active_subscriptions',
                         'expired_subscriptions',
                         'cancelled_subscriptions'
                     ],
                     'payment_methods' => [
                         'stripe',
                         'paypal'
                     ],
                     'subscription_metrics' => [
                         'new_subscriptions',
                         'renewals',
                         'cancellations',
                         'average_lifetime_value'
                     ]
                 ]);
        
        $data = $response->json();
        
        // تحقق - البيانات منطقية
        $this->assertGreaterThan(0, $data['summary']['total_payments']);
        $this->assertGreaterThanOrEqual(0, $data['summary']['total_revenue']);
        $this->assertEquals(
            $data['summary']['successful_payments'] + $data['summary']['failed_payments'],
            $data['summary']['total_payments']
        );
    }

    /** @test */
    public function system_performance_under_load()
    {
        // ترتيب - إنشاء حمولة كبيرة من البيانات
        $startTime = microtime(true);
        
        $payments = collect();
        for ($i = 0; $i < 100; $i++) {
            $payment = $this->createPendingPayment("load_test_{$i}@example.com");
            // تعديل التوقيت لتجنب مشاكل العمر باستخدام DB مباشرة
            $oldTime = now()->subMinutes(15);
            \DB::table('payments')
                ->where('id', $payment->id)
                ->update([
                    'created_at' => $oldTime,
                    'updated_at' => $oldTime
                ]);
            $payment->refresh();
            $payments->push($payment);
        }
        
        $creationTime = microtime(true) - $startTime;
        
        // تنفيذ - معالجة متوازية
        Queue::fake();
        $processingStart = microtime(true);
        
        $this->artisan('payments:verify-pending', [
            '--limit' => 100,
            '--min-age' => 0
        ])->assertExitCode(0);
        
        $processingTime = microtime(true) - $processingStart;
        
        // تحقق - الأداء ضمن المعايير المقبولة
        $this->assertLessThan(10.0, $creationTime, 'إنشاء 100 دفعة يجب أن يكون أقل من 10 ثوان');
        $this->assertLessThan(15.0, $processingTime, 'معالجة 100 دفعة يجب أن تكون أقل من 15 ثانية');
        
        // تحقق - تم طابور جميع الدفعات
        Queue::assertPushed(ProcessPendingPayment::class, 100);
        
        echo "\nأداء النظام تحت الحمولة:\n";
        echo "- إنشاء البيانات: {$creationTime}s\n";
        echo "- معالجة الطلبات: {$processingTime}s\n";
        echo "- الإنتاجية: " . round(100 / $processingTime, 2) . " دفعة/ثانية\n";
    }

    /** @test */
    public function error_recovery_and_system_resilience()
    {
        // ترتيب - إنشاء سيناريوهات أخطاء مختلفة
        $payments = collect([
            $this->createPendingPayment('normal@example.com'), // دفعة عادية
            $this->createPendingPayment('timeout@example.com'), // ستنتهي مهلتها
            $this->createPendingPayment('error@example.com') // ستفشل
        ]);
        
        // محاكاة سيناريوهات مختلفة
        $this->mockMixedGatewayResponses();
        
        // تنفيذ - معالجة مع توقع أخطاء
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($payments as $payment) {
            try {
                $job = new ProcessPendingPayment($payment);
                $job->handle();
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                // تسجيل الخطأ للمراجعة
                \Log::error("Payment processing error: " . $e->getMessage());
            }
        }
        
        // تحقق - النظام يتعامل مع الأخطاء بشكل مناسب
        $this->assertGreaterThan(0, $successCount, 'يجب نجاح بعض الدفعات على الأقل');
        
        // تحقق - الأخطاء لا توقف النظام بالكامل
        foreach ($payments as $payment) {
            $payment->refresh();
            $this->assertContains($payment->status, ['completed', 'failed', 'pending']);
        }
    }

    // Helper Methods

    protected function createCompleteTestEnvironment()
    {
        $this->website = Website::factory()->create([
            'name' => 'Test IPTV Service',
            'language' => 'ar'
        ]);
        
        $this->plan = Plan::factory()->create([
            'name' => 'خطة شهرية',
            'price' => 99.99,
            'currency' => 'USD',
            'duration_days' => 30
        ]);
        
        $this->generatedLink = GeneratedLink::factory()->create([
            'website_id' => $this->website->id,
            'plan_id' => $this->plan->id
        ]);
        
        // إنشاء حسابات الدفع
        $stripeGateway = PaymentGateway::factory()->create(['name' => 'stripe']);
        $this->stripeAccount = PaymentAccount::factory()->create([
            'payment_gateway_id' => $stripeGateway->id,
            'credentials' => [
                'secret_key' => 'sk_test_123',
                'publishable_key' => 'pk_test_123'
            ]
        ]);
        
        $paypalGateway = PaymentGateway::factory()->create(['name' => 'paypal']);
        $this->paypalAccount = PaymentAccount::factory()->create([
            'payment_gateway_id' => $paypalGateway->id,
            'credentials' => [
                'client_id' => 'paypal_client',
                'client_secret' => 'paypal_secret'
            ]
        ]);
        
        $this->user = User::factory()->create();
    }

    protected function createPendingPayment($email, $attributes = [])
    {
        return Payment::factory()->create(array_merge([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->stripeAccount->id,
            'payment_gateway' => 'stripe',
            'gateway_payment_id' => 'pi_test_' . rand(1000, 9999),
            'amount' => $this->plan->price,
            'currency' => $this->plan->currency,
            'status' => 'pending',
            'customer_email' => $email
        ], $attributes));
    }

    protected function createActiveSubscription($attributes = [])
    {
        $payment = $this->createPendingPayment('subscriber@example.com');
        $payment->update(['status' => 'completed', 'confirmed_at' => now()]);
        
        $subscription = Subscription::factory()->create(array_merge([
            'payment_id' => $payment->id,
            'plan_id' => $this->plan->id,
            'website_id' => $this->website->id,
            'customer_email' => $payment->customer_email,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays($this->plan->duration_days)
        ], $attributes));
        
        // Update payment to reference the subscription
        $payment->update(['subscription_id' => $subscription->id]);
        
        return $subscription;
    }

    protected function createReportingTestData()
    {
        // إنشاء دفعات ناجحة وفاشلة
        for ($i = 0; $i < 5; $i++) {
            $payment = $this->createPendingPayment("success_{$i}@example.com");
            $payment->update(['status' => 'completed', 'confirmed_at' => now()]);
            $this->createActiveSubscription(['payment_id' => $payment->id]);
        }
        
        for ($i = 0; $i < 2; $i++) {
            $payment = $this->createPendingPayment("failed_{$i}@example.com");
            $payment->update(['status' => 'failed']);
        }
        
        // اشتراكات منتهية ومُلغاة
        $expiredSub = $this->createActiveSubscription();
        $expiredSub->update(['status' => 'expired', 'expires_at' => now()->subDays(5)]);
        
        $cancelledSub = $this->createActiveSubscription();
        $cancelledSub->update(['status' => 'cancelled', 'cancelled_at' => now()->subDays(2)]);
    }

    protected function mockStripeSuccessfulResponse()
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

    protected function mockPayPalSuccessfulResponse()
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

    protected function mockMixedGatewayResponses()
    {
        // محاكاة ردود متنوعة للاختبار
        Http::fake([
            'https://api.stripe.com/*' => Http::sequence()
                ->push(['status' => 'succeeded'], 200) // نجاح
                ->push(['error' => 'timeout'], 408) // انتهاء مهلة
                ->push(['status' => 'payment_failed'], 200), // فشل
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}