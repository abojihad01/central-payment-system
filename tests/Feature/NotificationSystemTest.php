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
use App\Models\NotificationLog;
use App\Notifications\PaymentCompleted;
use App\Notifications\PaymentFailed;
use App\Notifications\SubscriptionActivated;
use App\Notifications\SubscriptionExpiring;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionRenewed;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\PaymentRefunded;
use App\Notifications\SubscriptionUpgraded;
use App\Notifications\AdminPaymentAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Carbon\Carbon;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $website;
    protected $plan;
    protected $generatedLink;
    protected $paymentAccount;
    protected $user;
    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        Notification::fake();
        Mail::fake();
        Queue::fake();
        
        $this->createTestEnvironment();
    }

    /** @test */
    public function payment_completion_notifications()
    {
        // ترتيب - إنشاء دفعة مكتملة
        $payment = $this->createCompletedPayment();
        
        // تنفيذ - إرسال إشعار إكمال الدفع
        $payment->sendCompletionNotification();
        
        // تحقق - تم إرسال الإشعار للعميل
        Notification::assertSentTo(
            $payment,
            PaymentCompleted::class,
            function ($notification, $channels) use ($payment) {
                return in_array('mail', $channels) && 
                       $notification->payment->id === $payment->id;
            }
        );
        
        // تحقق - تم تسجيل الإشعار في قاعدة البيانات
        $this->assertDatabaseHas('notification_logs', [
            'type' => 'payment_completed',
            'recipient_email' => $payment->customer_email,
            'status' => 'sent'
        ]);
    }

    /** @test */
    public function payment_failure_notifications()
    {
        // ترتيب - إنشاء دفعة فاشلة
        $payment = $this->createFailedPayment();
        
        // تنفيذ - إرسال إشعار فشل الدفع
        $payment->sendFailureNotification();
        
        // تحقق - تم إرسال الإشعار للعميل
        Notification::assertSentTo(
            $payment,
            PaymentFailed::class
        );
        
        // تحقق - تم إرسال تنبيه للمشرف
        Notification::assertSentTo(
            $this->adminUser,
            AdminPaymentAlert::class,
            function ($notification) use ($payment) {
                return $notification->alertType === 'payment_failed' &&
                       $notification->payment->id === $payment->id;
            }
        );
        
        // تحقق - تم تسجيل الإشعارات
        $this->assertDatabaseHas('notification_logs', [
            'type' => 'payment_failed',
            'recipient_email' => $payment->customer_email
        ]);
    }

    /** @test */
    public function subscription_activation_notifications()
    {
        // ترتيب - إنشاء اشتراك نشط
        $subscription = $this->createActiveSubscription();
        
        // تنفيذ - إرسال إشعار تفعيل الاشتراك
        $subscription->sendActivationNotification();
        
        // تحقق - تم إرسال الإشعار
        Notification::assertSentTo(
            $subscription,
            SubscriptionActivated::class,
            function ($notification, $channels) use ($subscription) {
                return in_array('mail', $channels) &&
                       $notification->subscription->id === $subscription->id;
            }
        );
        
        // تحقق - تم تسجيل الإشعار في قاعدة البيانات
        $this->assertDatabaseHas('notification_logs', [
            'type' => 'subscription_activated',
            'recipient_email' => $subscription->customer_email,
            'status' => 'sent'
        ]);
    }

    /** @test */
    public function subscription_expiring_notifications()
    {
        // ترتيب - إنشاء اشتراكات بتواريخ انتهاء مختلفة
        $subscriptions = collect([
            $this->createActiveSubscription(['expires_at' => now()->addDays(1)]), // ينتهي غداً
            $this->createActiveSubscription(['expires_at' => now()->addDays(3)]), // خلال 3 أيام
            $this->createActiveSubscription(['expires_at' => now()->addDays(7)]), // خلال أسبوع
            $this->createActiveSubscription(['expires_at' => now()->addDays(15)]) // خلال أسبوعين
        ]);
        
        // تنفيذ - تشغيل أمر إشعارات انتهاء الصلاحية
        $this->artisan('notifications:send-expiring-subscriptions')
             ->assertExitCode(0);
        
        // تحقق - تم إرسال إشعارات للاشتراكات المناسبة
        Notification::assertSentTimes(SubscriptionExpiring::class, 3); // للاشتراكات التي تنتهي خلال أسبوع
        
        // تحقق - لم يتم إرسال إشعار للاشتراك طويل المدى
        $longTermSubscription = $subscriptions->last();
        Notification::assertNotSentTo($longTermSubscription, SubscriptionExpiring::class);
        
        // تحقق - تم تسجيل جميع الإشعارات
        $this->assertEquals(3, NotificationLog::where('type', 'subscription_expiring')->count());
    }

    /** @test */
    public function subscription_expiration_notifications()
    {
        // ترتيب - إنشاء اشتراكات منتهية
        $expiredSubscriptions = collect([
            $this->createActiveSubscription(['expires_at' => now()->subDays(1)]),
            $this->createActiveSubscription(['expires_at' => now()->subHours(12)])
        ]);
        
        // تنفيذ - تشغيل معالج انتهاء الصلاحية
        $this->artisan('subscriptions:check-expired')
             ->assertExitCode(0);
        
        // تحقق - تم إرسال إشعارات انتهاء الصلاحية
        Notification::assertSentTimes(SubscriptionExpired::class, 2);
        
        foreach ($expiredSubscriptions as $subscription) {
            Notification::assertSentTo($subscription, SubscriptionExpired::class);
            
            // تحقق - تحديث حالة الاشتراك
            $subscription->refresh();
            $this->assertEquals('expired', $subscription->status);
        }
        
        // تحقق - تم إرسال تنبيه إداري
        Notification::assertSentTo(
            $this->adminUser,
            AdminPaymentAlert::class,
            function ($notification) {
                return $notification->alertType === 'subscriptions_expired';
            }
        );
    }

    /** @test */
    public function subscription_renewal_notifications()
    {
        // ترتيب - إنشاء اشتراك مجدد
        $subscription = $this->createActiveSubscription();
        $renewalPayment = $this->createCompletedPayment([
            'subscription_id' => $subscription->id,
            'is_renewal' => true
        ]);
        
        // تنفيذ - معالجة التجديد
        $subscription->processRenewal($renewalPayment);
        
        // تحقق - تم إرسال إشعار التجديد
        Notification::assertSentTo(
            $subscription,
            SubscriptionRenewed::class,
            function ($notification) use ($subscription, $renewalPayment) {
                return $notification->subscription->id === $subscription->id &&
                       $notification->renewalPayment->id === $renewalPayment->id;
            }
        );
        
        // تحقق - الإشعار يحتوي على التفاصيل الصحيحة
        $notificationData = Notification::sent($subscription, SubscriptionRenewed::class)->first();
        $mailData = $notificationData->notification->toMail($subscription);
        
        $this->assertStringContainsString('تجديد الاشتراك', $mailData->subject);
        $this->assertStringContainsString($subscription->expires_at->format('Y-m-d'), $mailData->render());
    }

    /** @test */
    public function subscription_cancellation_notifications()
    {
        // ترتيب - إنشاء اشتراك ملغي
        $subscription = $this->createActiveSubscription();
        
        // تنفيذ - إلغاء الاشتراك
        $subscription->cancel([
            'reason' => 'customer_request',
            'cancellation_type' => 'immediate'
        ]);
        
        // تحقق - تم إرسال إشعار الإلغاء
        Notification::assertSentTo(
            $subscription,
            SubscriptionCancelled::class,
            function ($notification) use ($subscription) {
                return $notification->subscription->id === $subscription->id &&
                       $notification->cancellationType === 'immediate';
            }
        );
        
        // تحقق - تم إرسال تنبيه إداري
        Notification::assertSentTo(
            $this->adminUser,
            AdminPaymentAlert::class,
            function ($notification) use ($subscription) {
                return $notification->alertType === 'subscription_cancelled' &&
                       $notification->subscription->id === $subscription->id;
            }
        );
    }

    /** @test */
    public function payment_refund_notifications()
    {
        // ترتيب - إنشاء استرداد
        $payment = $this->createCompletedPayment();
        $refundAmount = $payment->amount * 0.8;
        
        // إنشاء دفعة استرداد
        $refundPayment = Payment::factory()->create([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'original_payment_id' => $payment->id,
            'amount' => -$refundAmount,
            'type' => 'refund',
            'status' => 'completed',
            'customer_email' => $payment->customer_email
        ]);
        
        // تنفيذ - إرسال إشعار الاسترداد
        $refundPayment->sendRefundNotification();
        
        // تحقق - تم إرسال الإشعار
        Notification::assertSentTo(
            $refundPayment,
            PaymentRefunded::class,
            function ($notification) use ($refundPayment, $refundAmount) {
                return $notification->refundPayment->id === $refundPayment->id &&
                       $notification->refundAmount === $refundAmount;
            }
        );
        
        // تحقق - الإشعار يحتوي على تفاصيل الاسترداد
        $notificationData = Notification::sent($refundPayment, PaymentRefunded::class)->first();
        $mailData = $notificationData->notification->toMail($refundPayment);
        
        $this->assertStringContainsString('استرداد', $mailData->subject);
        $this->assertStringContainsString((string)$refundAmount, $mailData->render());
    }

    /** @test */
    public function subscription_upgrade_notifications()
    {
        // ترتيب - إنشاء ترقية اشتراك
        $basicPlan = $this->plan;
        $premiumPlan = Plan::factory()->create([
            'name' => 'خطة مميزة',
            'price' => 199.99,
            'duration_days' => 30
        ]);
        
        $subscription = $this->createActiveSubscription(['plan_id' => $basicPlan->id]);
        
        // تنفيذ - ترقية الاشتراك
        $upgradeResult = $subscription->upgradePlan($premiumPlan, true);
        
        // تحقق - تم إرسال إشعار الترقية
        Notification::assertSentTo(
            $subscription,
            SubscriptionUpgraded::class,
            function ($notification) use ($subscription, $basicPlan, $premiumPlan) {
                return $notification->subscription->id === $subscription->id &&
                       $notification->oldPlan->id === $basicPlan->id &&
                       $notification->newPlan->id === $premiumPlan->id;
            }
        );
        
        // تحقق - الإشعار يحتوي على تفاصيل الترقية
        $notificationData = Notification::sent($subscription, SubscriptionUpgraded::class)->first();
        $mailData = $notificationData->notification->toMail($subscription);
        
        $this->assertStringContainsString('ترقية', $mailData->subject);
        $this->assertStringContainsString($premiumPlan->name, $mailData->render());
    }

    /** @test */
    public function admin_payment_alerts()
    {
        // ترتيب - إنشاء دفعات بمبالغ كبيرة
        $highValuePayments = collect([
            $this->createCompletedPayment(['amount' => 1000.00]),
            $this->createCompletedPayment(['amount' => 1500.00])
        ]);
        
        // دفعات مشبوهة
        $suspiciousPayments = collect([
            $this->createFailedPayment(['attempts' => 5]),
            $this->createFailedPayment(['failure_reason' => 'suspected_fraud'])
        ]);
        
        // تنفيذ - تشغيل معالج التنبيهات الإدارية
        $this->artisan('notifications:send-admin-alerts')
             ->assertExitCode(0);
        
        // تحقق - تم إرسال تنبيهات للدفعات عالية القيمة
        Notification::assertSentTo(
            $this->adminUser,
            AdminPaymentAlert::class,
            function ($notification) {
                return $notification->alertType === 'high_value_payment';
            }
        );
        
        // تحقق - تم إرسال تنبيهات للدفعات المشبوهة
        Notification::assertSentTo(
            $this->adminUser,
            AdminPaymentAlert::class,
            function ($notification) {
                return $notification->alertType === 'suspicious_payment';
            }
        );
    }

    /** @test */
    public function notification_preferences_and_channels()
    {
        // ترتيب - إنشاء عميل بتفضيلات إشعارات مخصصة
        $customer = User::factory()->create([
            'email' => 'customer@example.com',
            'notification_preferences' => [
                'payment_completed' => ['mail', 'sms'],
                'subscription_expiring' => ['mail'],
                'subscription_expired' => ['mail', 'push'],
                'marketing' => []
            ]
        ]);
        
        $subscription = $this->createActiveSubscription([
            'customer_email' => $customer->email,
            'user_id' => $customer->id
        ]);
        
        // تنفيذ - إرسال إشعار انتهاء الصلاحية
        $subscription->sendExpiringNotification();
        
        // تحقق - تم إرسال الإشعار عبر القنوات المحددة فقط
        Notification::assertSentTo(
            $subscription,
            SubscriptionExpiring::class,
            function ($notification, $channels) {
                return in_array('mail', $channels) && 
                       !in_array('sms', $channels) && 
                       !in_array('push', $channels);
            }
        );
    }

    /** @test */
    public function notification_throttling_and_rate_limiting()
    {
        // ترتيب - إنشاء اشتراك
        $subscription = $this->createActiveSubscription();
        
        // تنفيذ - محاولة إرسال نفس الإشعار عدة مرات
        for ($i = 0; $i < 5; $i++) {
            $subscription->sendExpiringNotification();
        }
        
        // تحقق - تم إرسال الإشعار مرة واحدة فقط خلال فترة زمنية محددة
        Notification::assertSentTimes(SubscriptionExpiring::class, 1);
        
        // تحقق - تم تسجيل محاولات الإرسال المكررة
        $this->assertDatabaseHas('notification_logs', [
            'type' => 'subscription_expiring',
            'recipient_email' => $subscription->customer_email,
            'status' => 'throttled'
        ]);
    }

    /** @test */
    public function notification_retry_mechanism()
    {
        // ترتيب - محاكاة فشل إرسال الإشعار
        Notification::fake([
            PaymentCompleted::class => function () {
                throw new \Exception('SMTP server unavailable');
            }
        ]);
        
        $payment = $this->createCompletedPayment();
        
        // تنفيذ - محاولة إرسال الإشعار
        try {
            $payment->sendCompletionNotification();
        } catch (\Exception $e) {
            // متوقع أن يفشل
        }
        
        // تحقق - تم تسجيل فشل الإرسال
        $this->assertDatabaseHas('notification_logs', [
            'type' => 'payment_completed',
            'recipient_email' => $payment->customer_email,
            'status' => 'failed'
        ]);
        
        // تنفيذ - إعادة محاولة الإشعارات الفاشلة
        Notification::fake(); // إعادة تعيين لمحاكاة النجاح
        $this->artisan('notifications:retry-failed')
             ->assertExitCode(0);
        
        // تحقق - تم إعادة إرسال الإشعار بنجاح
        Notification::assertSent(PaymentCompleted::class);
        
        $this->assertDatabaseHas('notification_logs', [
            'type' => 'payment_completed',
            'recipient_email' => $payment->customer_email,
            'status' => 'sent',
            'retry_count' => 1
        ]);
    }

    /** @test */
    public function bulk_notification_processing()
    {
        // ترتيب - إنشاء عدة اشتراكات تنتهي قريباً
        $subscriptions = collect();
        for ($i = 0; $i < 100; $i++) {
            $subscriptions->push($this->createActiveSubscription([
                'customer_email' => "customer{$i}@example.com",
                'expires_at' => now()->addDays(3)
            ]));
        }
        
        // تنفيذ - معالجة مجمعة للإشعارات
        $startTime = microtime(true);
        
        $this->artisan('notifications:send-expiring-subscriptions', [
            '--batch-size' => 25
        ])->assertExitCode(0);
        
        $processingTime = microtime(true) - $startTime;
        
        // تحقق - تم إرسال جميع الإشعارات
        Notification::assertSentTimes(SubscriptionExpiring::class, 100);
        
        // تحقق - الأداء ضمن المعايير المقبولة
        $this->assertLessThan(30.0, $processingTime, 'معالجة 100 إشعار يجب أن تكون أقل من 30 ثانية');
        
        // تحقق - تم تسجيل جميع الإشعارات
        $this->assertEquals(100, NotificationLog::where('type', 'subscription_expiring')->count());
        
        echo "\nأداء الإشعارات المجمعة:\n";
        echo "- وقت المعالجة: {$processingTime}s\n";
        echo "- المعدل: " . round(100 / $processingTime, 2) . " إشعار/ثانية\n";
    }

    // Helper Methods

    protected function createTestEnvironment()
    {
        $this->website = Website::factory()->create();
        $this->plan = Plan::factory()->create([
            'name' => 'خطة أساسية',
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
        
        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@company.com'
        ]);
    }

    protected function createCompletedPayment($attributes = [])
    {
        return Payment::factory()->create(array_merge([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'amount' => $this->plan->price,
            'status' => 'completed',
            'confirmed_at' => now(),
            'customer_email' => 'customer@example.com'
        ], $attributes));
    }

    protected function createFailedPayment($attributes = [])
    {
        return Payment::factory()->create(array_merge([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'amount' => $this->plan->price,
            'status' => 'failed',
            'customer_email' => 'failed@example.com',
            'failure_reason' => 'insufficient_funds'
        ], $attributes));
    }

    protected function createActiveSubscription($attributes = [])
    {
        $payment = $this->createCompletedPayment();
        
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
}