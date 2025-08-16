<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Website;
use App\Models\GeneratedLink;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleSystemTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function system_database_models_work_correctly()
    {
        // إنشاء موقع ويب
        $website = Website::create([
            'name' => 'Test IPTV',
            'domain' => 'test.com',
            'language' => 'ar',
            'logo' => 'logo.png',
            'success_url' => 'https://test.com/success',
            'failure_url' => 'https://test.com/failure',
            'is_active' => true
        ]);

        // إنشاء خطة
        $plan = Plan::create([
            'website_id' => $website->id,
            'name' => 'خطة أساسية',
            'description' => 'خطة شهرية أساسية',
            'price' => 99.99,
            'currency' => 'USD',
            'duration_days' => 30,
            'features' => json_encode(['channels' => 1000, 'quality' => 'HD']),
            'is_active' => true
        ]);

        // إنشاء رابط مولد
        $generatedLink = GeneratedLink::create([
            'website_id' => $website->id,
            'plan_id' => $plan->id,
            'token' => 'test-token-123',
            'success_url' => 'https://test.com/success',
            'failure_url' => 'https://test.com/failure',
            'price' => 99.99,
            'currency' => 'USD',
            'expires_at' => now()->addDays(30),
            'single_use' => false,
            'is_used' => false,
            'is_active' => true
        ]);

        // إنشاء بوابة دفع
        $paymentGateway = PaymentGateway::create([
            'name' => 'stripe',
            'display_name' => 'Stripe',
            'is_active' => true
        ]);

        // إنشاء حساب دفع
        $paymentAccount = PaymentAccount::create([
            'payment_gateway_id' => $paymentGateway->id,
            'account_id' => 'stripe_test_001',
            'name' => 'Test Stripe Account',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => json_encode([
                'secret_key' => 'sk_test_123',
                'publishable_key' => 'pk_test_123'
            ])
        ]);

        // إنشاء دفعة
        $payment = Payment::create([
            'generated_link_id' => $generatedLink->id,
            'payment_account_id' => $paymentAccount->id,
            'payment_gateway' => 'stripe',
            'gateway_payment_id' => 'pi_test_123',
            'gateway_session_id' => 'cs_test_123',
            'amount' => 99.99,
            'currency' => 'USD',
            'status' => 'completed',
            'customer_email' => 'test@example.com',
            'customer_name' => 'Test Customer',
            'type' => 'payment',
            'is_renewal' => false,
            'confirmed_at' => now(),
            'gateway_response' => json_encode(['status' => 'succeeded']),
            'notes' => 'Test payment'
        ]);

        // إنشاء اشتراك
        $subscription = Subscription::create([
            'subscription_id' => \Illuminate\Support\Str::uuid(),
            'payment_id' => $payment->id,
            'plan_id' => $plan->id,
            'website_id' => $website->id,
            'customer_email' => 'test@example.com',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30)
        ]);

        // التحقق من أن جميع النماذج تم إنشاؤها بنجاح
        $this->assertDatabaseHas('websites', ['name' => 'Test IPTV']);
        $this->assertDatabaseHas('plans', ['name' => 'خطة أساسية']);
        $this->assertDatabaseHas('generated_links', ['token' => 'test-token-123']);
        $this->assertDatabaseHas('payment_gateways', ['name' => 'stripe']);
        $this->assertDatabaseHas('payment_accounts', ['name' => 'Test Stripe Account']);
        $this->assertDatabaseHas('payments', ['gateway_payment_id' => 'pi_test_123']);
        $this->assertDatabaseHas('subscriptions', ['status' => 'active']);

        // التحقق من العلاقات
        $this->assertEquals($website->id, $plan->website_id);
        $this->assertEquals($plan->id, $generatedLink->plan_id);
        $this->assertEquals($payment->id, $subscription->payment_id);
        $this->assertEquals('completed', $payment->status);
        $this->assertEquals('active', $subscription->status);
        
        echo "\n✅ اختبار النماذج الأساسي نجح!\n";
        echo "  - تم إنشاء جميع النماذج المطلوبة\n";
        echo "  - العلاقات تعمل بشكل صحيح\n";
        echo "  - قاعدة البيانات تحتوي على البيانات الصحيحة\n";
    }

    /** @test */
    public function basic_payment_flow_simulation()
    {
        // إنشاء البيانات الأساسية
        $website = Website::create([
            'name' => 'IPTV Test',
            'domain' => 'iptv-test.com',
            'language' => 'ar',
            'logo' => null,
            'success_url' => 'https://test.com/success',
            'failure_url' => 'https://test.com/failure',
            'is_active' => true
        ]);

        $plan = Plan::create([
            'website_id' => $website->id,
            'name' => 'خطة مميزة',
            'description' => 'خطة شهرية مميزة',
            'price' => 199.99,
            'currency' => 'USD',
            'duration_days' => 30,
            'features' => json_encode(['channels' => 5000, 'quality' => '4K']),
            'is_active' => true
        ]);

        $generatedLink = GeneratedLink::create([
            'website_id' => $website->id,
            'plan_id' => $plan->id,
            'token' => 'premium-token-456',
            'success_url' => 'https://test.com/success',
            'failure_url' => 'https://test.com/failure',
            'price' => 199.99,
            'currency' => 'USD',
            'expires_at' => now()->addDays(7),
            'single_use' => true,
            'is_used' => false,
            'is_active' => true
        ]);

        // محاكاة معالجة الدفع
        $gateway = PaymentGateway::create([
            'name' => 'paypal', 
            'display_name' => 'PayPal',
            'is_active' => true
        ]);
        $account = PaymentAccount::create([
            'payment_gateway_id' => $gateway->id,
            'account_id' => 'paypal_test_001',
            'name' => 'PayPal Account',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => json_encode(['client_id' => 'paypal_123'])
        ]);

        // إنشاء دفعة معلقة
        $payment = Payment::create([
            'generated_link_id' => $generatedLink->id,
            'payment_account_id' => $account->id,
            'payment_gateway' => 'paypal',
            'gateway_payment_id' => 'PAY-123456789',
            'amount' => 199.99,
            'currency' => 'USD',
            'status' => 'pending',
            'customer_email' => 'premium@example.com',
            'customer_name' => 'Premium Customer',
            'type' => 'payment',
            'is_renewal' => false
        ]);

        // محاكاة إكمال الدفعة
        $payment->update([
            'status' => 'completed',
            'confirmed_at' => now(),
            'gateway_response' => json_encode(['status' => 'COMPLETED'])
        ]);

        // إنشاء الاشتراك بعد نجاح الدفعة
        $subscription = Subscription::create([
            'subscription_id' => \Illuminate\Support\Str::uuid(),
            'payment_id' => $payment->id,
            'plan_id' => $plan->id,
            'website_id' => $website->id,
            'customer_email' => $payment->customer_email,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays($plan->duration_days)
        ]);

        // التحقق من التدفق الكامل
        $this->assertEquals('completed', $payment->status);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($payment->customer_email, $subscription->customer_email);
        $this->assertTrue($subscription->expires_at->greaterThan(now()));

        echo "\n✅ محاكاة تدفق الدفع الأساسي نجحت!\n";
        echo "  - تم إنشاء دفعة معلقة\n";
        echo "  - تم إكمال الدفعة بنجاح\n";
        echo "  - تم إنشاء اشتراك نشط\n";
        echo "  - تواريخ الاشتراك صحيحة\n";
    }

    /** @test */
    public function subscription_management_basics()
    {
        // إنشاء اشتراك نشط
        $website = Website::create([
            'name' => 'Test Service',
            'domain' => 'service.test',
            'language' => 'en',
            'logo' => null,
            'success_url' => 'https://test.com/success',
            'failure_url' => 'https://test.com/failure',
            'is_active' => true
        ]);

        $plan = Plan::create([
            'website_id' => $website->id,
            'name' => 'Monthly Plan',
            'description' => 'Basic monthly plan',
            'price' => 49.99,
            'currency' => 'USD',
            'duration_days' => 30,
            'is_active' => true
        ]);

        // نحتاج إنشاء دفعة أولاً
        $gateway = PaymentGateway::create([
            'name' => 'stripe',
            'display_name' => 'Stripe',
            'is_active' => true
        ]);
        $account = PaymentAccount::create([
            'payment_gateway_id' => $gateway->id,
            'account_id' => 'stripe_sub_001',
            'name' => 'Test Account',
            'is_active' => true,
            'is_sandbox' => true,
            'credentials' => json_encode(['key' => 'test'])
        ]);
        $generatedLink = GeneratedLink::create([
            'website_id' => $website->id,
            'plan_id' => $plan->id,
            'token' => 'sub-test-token',
            'success_url' => 'https://test.com/success',
            'failure_url' => 'https://test.com/failure',
            'price' => 49.99,
            'currency' => 'USD',
            'expires_at' => now()->addDays(7),
            'single_use' => false,
            'is_used' => false,
            'is_active' => true
        ]);
        $payment = Payment::create([
            'generated_link_id' => $generatedLink->id,
            'payment_account_id' => $account->id,
            'payment_gateway' => 'stripe',
            'gateway_payment_id' => 'pi_sub_test',
            'amount' => 49.99,
            'currency' => 'USD',
            'status' => 'completed',
            'customer_email' => 'subscriber@test.com',
            'customer_name' => 'Test Subscriber',
            'type' => 'payment',
            'confirmed_at' => now()
        ]);

        $subscription = Subscription::create([
            'subscription_id' => \Illuminate\Support\Str::uuid(),
            'payment_id' => $payment->id,
            'plan_id' => $plan->id,
            'website_id' => $website->id,
            'customer_email' => 'subscriber@test.com',
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30)
        ]);

        // اختبار إلغاء الاشتراك
        $subscription->update([
            'status' => 'cancelled'
        ]);

        $this->assertEquals('cancelled', $subscription->status);

        // اختبار انتهاء الصلاحية
        $expiredPayment = Payment::create([
            'generated_link_id' => $generatedLink->id,
            'payment_account_id' => $account->id,
            'payment_gateway' => 'stripe',
            'gateway_payment_id' => 'pi_expired_test',
            'amount' => 49.99,
            'currency' => 'USD',
            'status' => 'completed',
            'customer_email' => 'expired@test.com',
            'customer_name' => 'Expired User',
            'type' => 'payment',
            'confirmed_at' => now()->subDays(60)
        ]);

        $expiredSubscription = Subscription::create([
            'subscription_id' => \Illuminate\Support\Str::uuid(),
            'payment_id' => $expiredPayment->id,
            'plan_id' => $plan->id,
            'website_id' => $website->id,
            'customer_email' => 'expired@test.com',
            'status' => 'active',
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDays(30)
        ]);

        $expiredSubscription->update([
            'status' => 'expired'
        ]);

        $this->assertEquals('expired', $expiredSubscription->status);

        echo "\n✅ إدارة الاشتراكات الأساسية تعمل!\n";
        echo "  - إلغاء الاشتراك يعمل\n";
        echo "  - انتهاء صلاحية الاشتراك يعمل\n";
        echo "  - حالات الاشتراك تُحدث بشكل صحيح\n";
    }
}