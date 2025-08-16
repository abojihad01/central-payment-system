<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Website;
use App\Models\GeneratedLink;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use App\Models\Customer;
use App\Jobs\ProcessSubscriptionRenewal;
use App\Jobs\ProcessSubscriptionExpiration;
use App\Notifications\SubscriptionExpiringSoon;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionRenewed;
use App\Notifications\SubscriptionCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Carbon\Carbon;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected $website;
    protected $plan;
    protected $generatedLink;
    protected $paymentAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure no active transactions before starting tests
        if (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }
        
        Notification::fake();
        Queue::fake();
        
        $this->createTestData();
    }
    
    protected function tearDown(): void
    {
        // Clean up any pending transactions
        while (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }
        
        parent::tearDown();
    }

    /** @test */
    public function subscription_creation_from_successful_payment()
    {
        // ترتيب - إنشاء دفعة ناجحة
        $payment = $this->createSuccessfulPayment();
        
        // تنفيذ - إنشاء الاشتراك
        $subscription = Subscription::createFromPayment($payment);
        
        // تحقق - تم إنشاء الاشتراك بشكل صحيح
        $this->assertNotNull($subscription);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($payment->id, $subscription->payment_id);
        $this->assertEquals($payment->customer_email, $subscription->customer_email);
        $this->assertEquals($this->plan->id, $subscription->plan_id);
        $this->assertEquals($this->website->id, $subscription->website_id);
        
        // تحقق - تواريخ الاشتراك
        $this->assertNotNull($subscription->starts_at);
        $this->assertNotNull($subscription->expires_at);
        $expectedEndDate = $subscription->starts_at->addDays($this->plan->duration_days);
        $this->assertEquals(
            $expectedEndDate->format('Y-m-d'), 
            $subscription->expires_at->format('Y-m-d')
        );
        
        // تحقق - الاشتراك مربوط بالدفعة
        $payment->refresh();
        $this->assertEquals($subscription->id, $payment->subscription_id);
    }

    /** @test */
    public function subscription_renewal_process()
    {
        // ترتيب - إنشاء اشتراك نشط قارب على الانتهاء
        $subscription = $this->createActiveSubscription([
            'expires_at' => now()->addDays(3)
        ]);
        
        $originalEndDate = $subscription->expires_at;
        
        // تنفيذ - معالجة التجديد
        $renewalPayment = $this->createRenewalPayment($subscription);
        $subscription->processRenewal($renewalPayment);
        
        // تحقق - تم تجديد الاشتراك
        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertTrue($subscription->expires_at->greaterThan($originalEndDate));
        $this->assertEquals(
            $originalEndDate->addDays($this->plan->duration_days)->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d')
        );
        
        // تحقق - تم إرسال إشعار التجديد
        Notification::assertSentTo(
            $subscription,
            SubscriptionRenewed::class
        );
        
        // تحقق - تم ربط دفعة التجديد
        $renewalPayment->refresh();
        $this->assertEquals($subscription->id, $renewalPayment->subscription_id);
        $this->assertTrue($renewalPayment->is_renewal);
    }

    /** @test */
    public function subscription_expiration_warning_system()
    {
        // ترتيب - إنشاء اشتراكات بتواريخ انتهاء مختلفة
        $subscriptions = collect([
            $this->createActiveSubscription(['expires_at' => now()->addDays(1)]), // ينتهي غداً
            $this->createActiveSubscription(['expires_at' => now()->addDays(3)]), // ينتهي خلال 3 أيام
            $this->createActiveSubscription(['expires_at' => now()->addDays(7)]), // ينتهي خلال أسبوع
            $this->createActiveSubscription(['expires_at' => now()->addDays(15)]), // ينتهي خلال أسبوعين
        ]);
        
        // تنفيذ - تشغيل أمر إشعارات انتهاء الصلاحية
        $this->artisan('subscriptions:notify-expiring')
             ->assertExitCode(0);
        
        // تحقق - تم إرسال إشعارات للاشتراكات المناسبة
        // يجب إرسال إشعارات للاشتراكات التي تنتهي خلال 7 أيام
        Notification::assertSentTimes(SubscriptionExpiringSoon::class, 3);
        
        // تحقق - لم يتم إرسال إشعار للاشتراك الذي ينتهي خلال 15 يوم
        $longTermSubscription = $subscriptions->last();
        Notification::assertNotSentTo(
            $longTermSubscription,
            SubscriptionExpiringSoon::class
        );
    }

    /** @test */
    public function subscription_expiration_process()
    {
        // ترتيب - إنشاء اشتراكات منتهية الصلاحية
        $expiredSubscriptions = collect([
            $this->createActiveSubscription(['expires_at' => now()->subDays(1)]),
            $this->createActiveSubscription(['expires_at' => now()->subHours(12)]),
            $this->createActiveSubscription(['expires_at' => now()->subDays(5)])
        ]);
        
        // إنشاء اشتراك لا يزال نشطاً
        $activeSubscription = $this->createActiveSubscription(['expires_at' => now()->addDays(5)]);
        
        // تنفيذ - تشغيل أمر فحص الاشتراكات المنتهية
        $this->artisan('subscriptions:check-expired')
             ->assertExitCode(0);
        
        // تحقق - تم تحديث حالة الاشتراكات المنتهية
        foreach ($expiredSubscriptions as $subscription) {
            $subscription->refresh();
            $this->assertEquals('expired', $subscription->status);
            $this->assertNotNull($subscription->expired_at);
        }
        
        // تحقق - الاشتراك النشط لم يتأثر
        $activeSubscription->refresh();
        $this->assertEquals('active', $activeSubscription->status);
        $this->assertNull($activeSubscription->expired_at);
        
        // تحقق - تم إرسال إشعارات انتهاء الصلاحية
        Notification::assertSentTimes(SubscriptionExpired::class, 3);
    }

    /** @test */
    public function subscription_cancellation_immediate()
    {
        // ترتيب - إنشاء اشتراك نشط
        $subscription = $this->createActiveSubscription();
        
        // تنفيذ - إلغاء فوري
        $cancellationData = [
            'reason' => 'customer_request',
            'cancellation_type' => 'immediate',
            'notes' => 'Customer requested immediate cancellation'
        ];
        
        $result = $subscription->cancel($cancellationData);
        
        // تحقق - تم الإلغاء فوراً
        $this->assertTrue($result);
        $subscription->refresh();
        
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertEquals('customer_request', $subscription->cancellation_reason);
        $this->assertEquals('immediate', $subscription->cancellation_type);
        $this->assertStringContainsString('Customer requested', $subscription->cancellation_notes);
        
        // تحقق - تاريخ الانتهاء تم تحديثه للآن
        $this->assertEquals(
            now()->format('Y-m-d H:i'),
            $subscription->expires_at->format('Y-m-d H:i')
        );
        
        // تحقق - تم إرسال إشعار الإلغاء
        Notification::assertSentTo(
            $subscription,
            SubscriptionCancelled::class
        );
    }

    /** @test */
    public function subscription_cancellation_at_period_end()
    {
        // ترتيب - إنشاء اشتراك نشط
        $subscription = $this->createActiveSubscription([
            'expires_at' => now()->addDays(15)
        ]);
        $originalEndDate = $subscription->expires_at;
        
        // تنفيذ - إلغاء في نهاية الفترة
        $cancellationData = [
            'reason' => 'financial_constraints',
            'cancellation_type' => 'at_period_end',
            'notes' => 'Cancel at the end of current period'
        ];
        
        $result = $subscription->cancel($cancellationData);
        
        // تحقق - تم جدولة الإلغاء
        $this->assertTrue($result);
        $subscription->refresh();
        
        $this->assertEquals('active', $subscription->status); // لا يزال نشطاً
        $this->assertNotNull($subscription->cancelled_at); // لكن مجدول للإلغاء
        $this->assertEquals('financial_constraints', $subscription->cancellation_reason);
        $this->assertEquals('at_period_end', $subscription->cancellation_type);
        $this->assertTrue($subscription->will_cancel_at_period_end);
        
        // تحقق - تاريخ الانتهاء لم يتغير
        $this->assertEquals(
            $originalEndDate->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d')
        );
        
        // تنفيذ - تشغيل معالج انتهاء الاشتراكات
        Carbon::setTestNow($subscription->expires_at->addDay());
        $this->artisan('subscriptions:check-expired');
        
        // تحقق - تم إلغاء الاشتراك في النهاية
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
    }

    /** @test */
    public function subscription_pause_and_resume()
    {
        // ترتيب - إنشاء اشتراك نشط
        $subscription = $this->createActiveSubscription([
            'expires_at' => now()->addDays(20)
        ]);
        $originalEndDate = $subscription->expires_at->copy();
        
        // تنفيذ - إيقاف مؤقت
        $pauseResult = $subscription->pause([
            'reason' => 'temporary_break',
            'notes' => 'Customer taking a break'
        ]);
        
        // تحقق - تم الإيقاف المؤقت
        $this->assertTrue($pauseResult);
        $subscription->refresh();
        
        $this->assertEquals('paused', $subscription->status);
        $this->assertNotNull($subscription->paused_at);
        $this->assertEquals('temporary_break', $subscription->pause_reason);
        
        // محاكاة مرور 5 أيام
        Carbon::setTestNow(now()->addDays(5));
        
        // تنفيذ - استئناف الاشتراك
        $resumeResult = $subscription->resume();
        
        // تحقق - تم الاستئناف
        $this->assertTrue($resumeResult);
        $subscription->refresh();
        
        $this->assertEquals('active', $subscription->status);
        $this->assertNotNull($subscription->resumed_at);
        
        // تحقق - تم تمديد تاريخ الانتهاء بعدد أيام الإيقاف
        $expectedNewEndDate = $originalEndDate->addDays(5);
        $this->assertEquals(
            $expectedNewEndDate->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d')
        );
    }

    /** @test */
    public function subscription_plan_upgrade()
    {
        // ترتيب - إنشاء خطة أغلى
        $premiumPlan = Plan::factory()->create([
            'name' => 'Premium Plan',
            'price' => 199.99,
            'duration_days' => 30
        ]);
        
        $subscription = $this->createActiveSubscription([
            'expires_at' => now()->addDays(15) // 15 يوم متبقي
        ]);
        
        // تنفيذ - ترقية الخطة مع التناسب
        $upgradeResult = $subscription->upgradePlan($premiumPlan, true);
        
        // تحقق - تم تغيير الخطة
        $this->assertTrue($upgradeResult['success']);
        $subscription->refresh();
        
        $this->assertEquals($premiumPlan->id, $subscription->plan_id);
        
        // تحقق - تم حساب التناسب (prorated amount)
        $this->assertArrayHasKey('prorated_amount', $upgradeResult);
        $this->assertGreaterThan(0, $upgradeResult['prorated_amount']);
        
        // تحقق - تم إنشاء دفعة التناسب
        $proratedPayment = Payment::where('subscription_id', $subscription->id)
                                 ->where('type', 'upgrade')
                                 ->first();
        
        $this->assertNotNull($proratedPayment);
        $this->assertEquals($upgradeResult['prorated_amount'], $proratedPayment->amount);
    }

    /** @test */
    public function subscription_plan_downgrade()
    {
        // ترتيب - إنشاء خطة أرخص
        $basicPlan = Plan::factory()->create([
            'name' => 'Basic Plan', 
            'price' => 49.99,
            'duration_days' => 30
        ]);
        
        // اشتراك بخطة مكلفة
        $subscription = $this->createActiveSubscription([
            'expires_at' => now()->addDays(20)
        ]);
        
        // تنفيذ - تخفيض الخطة في نهاية الفترة
        $downgradeResult = $subscription->downgradePlan($basicPlan, false);
        
        // تحقق - تم جدولة التخفيض
        $this->assertTrue($downgradeResult['success']);
        $subscription->refresh();
        
        // الخطة الحالية لا تزال كما هي حتى نهاية الفترة
        $this->assertEquals($this->plan->id, $subscription->plan_id);
        $this->assertEquals($basicPlan->id, $subscription->scheduled_plan_change);
        $this->assertEquals('downgrade', $subscription->plan_change_type);
        
        // محاكاة وصول نهاية الفترة
        Carbon::setTestNow($subscription->expires_at);
        
        // تنفيذ - معالجة تغييرات الخطة المجدولة
        $this->artisan('subscriptions:process-plan-changes');
        
        // تحقق - تم تطبيق التخفيض
        $subscription->refresh();
        $this->assertEquals($basicPlan->id, $subscription->plan_id);
        $this->assertNull($subscription->scheduled_plan_change);
    }

    /** @test */
    public function subscription_grace_period_handling()
    {
        // ترتيب - إنشاء اشتراك مع فترة سماح
        $subscription = $this->createActiveSubscription([
            'expires_at' => now()->subDays(2), // انتهى منذ يومين
            'grace_period_days' => 7
        ]);
        
        // تنفيذ - فحص الاشتراكات المنتهية
        $this->artisan('subscriptions:check-expired');
        
        // تحقق - الاشتراك في فترة السماح
        $subscription->refresh();
        $this->assertEquals('grace_period', $subscription->status);
        $this->assertNotNull($subscription->grace_period_ends_at);
        
        // محاكاة انتهاء فترة السماح
        Carbon::setTestNow($subscription->grace_period_ends_at->addDay());
        $this->artisan('subscriptions:check-expired');
        
        // تحقق - تم انتهاء الاشتراك نهائياً
        $subscription->refresh();
        $this->assertEquals('expired', $subscription->status);
    }

    /** @test */
    public function subscription_reactivation_after_expiration()
    {
        // ترتيب - إنشاء اشتراك منتهي
        $subscription = $this->createActiveSubscription([
            'status' => 'expired',
            'expires_at' => now()->subDays(5),
            'expired_at' => now()->subDays(5)
        ]);
        
        // تنفيذ - إعادة تفعيل الاشتراك
        $reactivationPayment = $this->createSuccessfulPayment([
            'customer_email' => $subscription->customer_email,
            'amount' => $this->plan->price
        ]);
        
        $reactivationResult = $subscription->reactivate($reactivationPayment);
        
        // تحقق - تم إعادة التفعيل
        $this->assertTrue($reactivationResult);
        $subscription->refresh();
        
        $this->assertEquals('active', $subscription->status);
        $this->assertNotNull($subscription->reactivated_at);
        $this->assertNull($subscription->expired_at);
        
        // تحقق - تم تحديث تاريخ الانتهاء
        $expectedEndDate = now()->addDays($this->plan->duration_days);
        $this->assertEquals(
            $expectedEndDate->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d')
        );
    }

    /** @test */
    public function subscription_transfer_between_customers()
    {
        // ترتيب - إنشاء اشتراك
        $subscription = $this->createActiveSubscription([
            'customer_email' => 'old@example.com'
        ]);
        
        // تنفيذ - نقل الاشتراك لعميل آخر
        $transferResult = $subscription->transferToCustomer('new@example.com', [
            'reason' => 'account_change',
            'authorized_by' => 'admin@company.com'
        ]);
        
        // تحقق - تم النقل
        $this->assertTrue($transferResult);
        $subscription->refresh();
        
        $this->assertEquals('new@example.com', $subscription->customer_email);
        $this->assertNotNull($subscription->transferred_at);
        $this->assertEquals('old@example.com', $subscription->previous_customer_email);
        $this->assertEquals('account_change', $subscription->transfer_reason);
    }

    // Helper Methods

    protected function createTestData()
    {
        $this->website = Website::factory()->create();
        $this->plan = Plan::factory()->create([
            'price' => 99.99,
            'duration_days' => 30
        ]);
        $this->generatedLink = GeneratedLink::factory()->create([
            'website_id' => $this->website->id,
            'plan_id' => $this->plan->id
        ]);
        
        $gateway = PaymentGateway::factory()->create();
        $this->paymentAccount = PaymentAccount::factory()->create([
            'payment_gateway_id' => $gateway->id
        ]);
    }

    protected function createSuccessfulPayment($attributes = [])
    {
        return Payment::factory()->create(array_merge([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'amount' => $this->plan->price,
            'status' => 'completed',
            'confirmed_at' => now(),
            'customer_email' => 'test@example.com'
        ], $attributes));
    }

    protected function createActiveSubscription($attributes = [])
    {
        $payment = $this->createSuccessfulPayment();
        
        return Subscription::factory()->create(array_merge([
            'payment_id' => $payment->id,
            'plan_id' => $this->plan->id,
            'website_id' => $this->website->id,
            'customer_email' => $payment->customer_email,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays($this->plan->duration_days)
        ], $attributes));
    }

    protected function createRenewalPayment($subscription)
    {
        return Payment::factory()->create([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'subscription_id' => $subscription->id,
            'amount' => $this->plan->price,
            'status' => 'completed',
            'confirmed_at' => now(),
            'customer_email' => $subscription->customer_email,
            'is_renewal' => true
        ]);
    }
}