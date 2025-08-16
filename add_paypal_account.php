<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\PaymentGateway;
use App\Models\PaymentAccount;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // التحقق من وجود بوابة PayPal أو إنشاؤها
    $gateway = PaymentGateway::where('name', 'paypal')->first();
    if (!$gateway) {
        $gateway = PaymentGateway::create([
            'name' => 'paypal',
            'display_name' => 'PayPal',
            'is_active' => true,
            'supported_currencies' => ['USD', 'EUR', 'SAR', 'AED', 'GBP'],
            'supported_countries' => ['US', 'SA', 'AE', 'GB', 'DE', 'FR', 'ES', 'IT', 'CA', 'AU'],
            'priority' => 2
        ]);
        echo "✅ تم إنشاء بوابة PayPal جديدة\n";
    } else {
        echo "✅ تم العثور على بوابة PayPal الموجودة\n";
    }

    // إنشاء حساب PayPal Sandbox الجديد
    $account = PaymentAccount::create([
        'payment_gateway_id' => $gateway->id,
        'account_id' => 'paypal_sandbox_' . time(),
        'name' => 'PayPal Sandbox Account',
        'description' => 'PayPal Sandbox account for testing payments',
        'credentials' => [
            'client_id' => 'Aeb5yx09I0auwM3uJkSMUXH8gXGM7D3BhjR9yCwDJxxxXUQt9abXdAh0-HcclBRB-Ls6EAcDn78rHIxu',
            'client_secret' => 'EMUydVglqM_uTjUKEhA-U74fOloco7Zm-31oYyCULFt4eGapXF2_j9nXNWLydCk6kEb1vilij0_JJemR',
            'environment' => 'sandbox'
        ],
        'is_active' => true,
        'is_sandbox' => true, // PayPal Sandbox environment
        'successful_transactions' => 0,
        'failed_transactions' => 0,
        'total_amount' => 0.00,
        'settings' => [
            'webhook_url' => '/api/webhooks/paypal',
            'return_url' => '/payment/success',
            'cancel_url' => '/payment/cancel'
        ]
    ]);

    echo "\n🎉 تم إضافة حساب PayPal Sandbox بنجاح!\n";
    echo "📋 تفاصيل الحساب:\n";
    echo "   - معرف الحساب: {$account->account_id}\n";
    echo "   - اسم الحساب: {$account->name}\n";
    echo "   - حالة الحساب: " . ($account->is_active ? 'نشط ✅' : 'غير نشط ❌') . "\n";
    echo "   - نوع البيئة: " . ($account->is_sandbox ? 'Sandbox (Test) 🧪' : 'Production 🚀') . "\n";
    echo "   - البيئة: " . $account->credentials['environment'] . "\n";

    // عرض جميع حسابات PayPal الموجودة
    $allPayPalAccounts = PaymentAccount::where('payment_gateway_id', $gateway->id)->get();
    echo "\n📊 جميع حسابات PayPal:\n";
    foreach ($allPayPalAccounts as $acc) {
        $status = $acc->is_active ? '✅ نشط' : '❌ غير نشط';
        $env = $acc->is_sandbox ? '🧪 Sandbox' : '🚀 Production';
        echo "   - {$acc->name} ({$acc->account_id}) - {$status} - {$env}\n";
    }

    // عرض ملخص لجميع بوابات الدفع
    echo "\n🌐 ملخص بوابات الدفع:\n";
    $allGateways = PaymentGateway::with('accounts')->get();
    foreach ($allGateways as $gw) {
        $accountCount = $gw->accounts->count();
        $activeCount = $gw->accounts->where('is_active', true)->count();
        echo "   - {$gw->display_name}: {$activeCount}/{$accountCount} حسابات نشطة\n";
    }

    echo "\n✨ النظام جاهز الآن لمعالجة المدفوعات عبر Stripe و PayPal!\n";

} catch (Exception $e) {
    echo "❌ خطأ في إضافة حساب PayPal: " . $e->getMessage() . "\n";
    exit(1);
}