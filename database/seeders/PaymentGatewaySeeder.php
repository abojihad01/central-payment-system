<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Models\PaymentAccount;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        // إنشاء بوابة Stripe
        $stripe = PaymentGateway::firstOrCreate(['name' => 'stripe'], [
            'name' => 'stripe',
            'display_name' => 'Stripe',
            'description' => 'بوابة دفع Stripe - دعم للبطاقات الائتمانية والمحافظ الرقمية',
            'logo_url' => 'https://stripe.com/img/v3/home/social.png',
            'is_active' => true,
            'priority' => 100,
            'supported_currencies' => ['USD', 'EUR', 'SAR', 'AED', 'EGP'],
            'supported_countries' => ['US', 'SA', 'AE', 'EG', 'UK', 'CA'],
            'configuration' => [
                'supports_webhooks' => true,
                'supports_refunds' => true,
                'supports_recurring' => true
            ]
        ]);

        // إضافة حسابات Stripe تجريبية
        PaymentAccount::firstOrCreate(['account_id' => 'stripe_account_1'], [
            'payment_gateway_id' => $stripe->id,
            'account_id' => 'stripe_account_1',
            'name' => 'Stripe Main Account',
            'description' => 'الحساب الرئيسي لـ Stripe',
            'credentials' => [
                'publishable_key' => 'pk_test_example_key_1',
                'secret_key' => 'sk_test_example_secret_1',
                'webhook_secret' => 'whsec_example_webhook_1'
            ],
            'is_active' => true,
            'is_sandbox' => true,
            'settings' => [
                'statement_descriptor' => 'PAYMENT SYSTEM',
                'capture_method' => 'automatic'
            ]
        ]);

        PaymentAccount::firstOrCreate(['account_id' => 'stripe_account_2'], [
            'payment_gateway_id' => $stripe->id,
            'account_id' => 'stripe_account_2',
            'name' => 'Stripe Backup Account',
            'description' => 'الحساب الاحتياطي لـ Stripe',
            'credentials' => [
                'publishable_key' => 'pk_test_example_key_2',
                'secret_key' => 'sk_test_example_secret_2',
                'webhook_secret' => 'whsec_example_webhook_2'
            ],
            'is_active' => true,
            'is_sandbox' => true,
            'settings' => [
                'statement_descriptor' => 'PAYMENT SYSTEM',
                'capture_method' => 'automatic'
            ]
        ]);

        // إنشاء بوابة PayPal
        $paypal = PaymentGateway::firstOrCreate(['name' => 'paypal'], [
            'name' => 'paypal',
            'display_name' => 'PayPal',
            'description' => 'بوابة دفع PayPal - دعم للمحافظ الرقمية والبطاقات',
            'logo_url' => 'https://www.paypalobjects.com/webstatic/mktg/logo/AM_mc_vs_dc_ae.jpg',
            'is_active' => true,
            'priority' => 90,
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD'],
            'supported_countries' => ['US', 'UK', 'CA', 'AU', 'DE', 'FR'],
            'configuration' => [
                'supports_webhooks' => true,
                'supports_refunds' => true,
                'supports_recurring' => false
            ]
        ]);

        // إضافة حساب PayPal تجريبي
        PaymentAccount::firstOrCreate(['account_id' => 'paypal_account_1'], [
            'payment_gateway_id' => $paypal->id,
            'account_id' => 'paypal_account_1',
            'name' => 'PayPal Main Account',
            'description' => 'الحساب الرئيسي لـ PayPal',
            'credentials' => [
                'client_id' => 'paypal_client_id_example',
                'client_secret' => 'paypal_client_secret_example'
            ],
            'is_active' => true,
            'is_sandbox' => true,
            'settings' => [
                'brand_name' => 'Payment System',
                'locale' => 'en_US'
            ]
        ]);

        // إنشاء بوابة محلية (للمستقبل)
        $local = PaymentGateway::firstOrCreate(['name' => 'local_bank'], [
            'name' => 'local_bank',
            'display_name' => 'البنك المحلي',
            'description' => 'بوابة دفع البنوك المحلية',
            'logo_url' => null,
            'is_active' => false, // معطلة افتراضياً
            'priority' => 50,
            'supported_currencies' => ['SAR', 'AED', 'EGP'],
            'supported_countries' => ['SA', 'AE', 'EG'],
            'configuration' => [
                'supports_webhooks' => false,
                'supports_refunds' => false,
                'supports_recurring' => false
            ]
        ]);

        $this->command->info('تم إنشاء بوابات الدفع والحسابات التجريبية بنجاح!');
    }
}