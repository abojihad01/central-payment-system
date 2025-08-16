# دليل التثبيت السريع - نظام الدفع المركزي

## متطلبات النظام
- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js & NPM

## خطوات التثبيت السريع

### 1. تثبيت التبعيات
```bash
composer install
npm install && npm run build
```

### 2. إعداد ملف البيئة
```bash
cp .env.example .env
php artisan key:generate
```

### 3. إعداد MySQL في ملف .env
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=central_payment_system
DB_USERNAME=root
DB_PASSWORD=
```

### 4. إنشاء قاعدة البيانات
```bash
# إنشاء قاعدة البيانات
mysql -u root -e "CREATE DATABASE central_payment_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# تشغيل migrations مع البيانات الأساسية
php artisan migrate:fresh --seed --seeder=AdminUserSeeder
```

### 5. تشغيل النظام
```bash
php artisan serve
```

### 6. الوصول للوحة التحكم
- **الرابط:** http://localhost:8000/admin
- **البريد الإلكتروني:** admin@example.com
- **كلمة المرور:** password123

## إضافة بيانات تجريبية

```bash
php artisan tinker
```

```php
// إنشاء موقع تجريبي
$website = \App\Models\Website::create([
    'name' => 'موقع تجريبي',
    'domain' => 'example.com',
    'success_url' => 'https://example.com/payment-success',
    'failure_url' => 'https://example.com/payment-failed',
    'is_active' => true
]);

// إنشاء باقة تجريبية
$plan = \App\Models\Plan::create([
    'website_id' => $website->id,
    'name' => 'الباقة الأساسية',
    'description' => 'باقة تجريبية للاختبار',
    'price' => 29.99,
    'currency' => 'USD',
    'duration_days' => 30,
    'features' => ['ميزة 1', 'ميزة 2', 'ميزة 3'],
    'is_active' => true
]);

echo "تم إنشاء الموقع والباقة بنجاح!";
echo "Website ID: " . $website->id . ", Plan ID: " . $plan->id;
```

## توليد رابط دفع تجريبي

```php
// في tinker
$paymentService = new \App\Services\PaymentLinkService();

$linkData = $paymentService->generatePaymentLink(
    websiteId: 1,
    planId: 1,
    successUrl: 'https://example.com/payment-success',
    failureUrl: 'https://example.com/payment-failed',
    expiryMinutes: 60,
    singleUse: true
);

echo "رابط الدفع: " . $linkData['payment_link'];
```

## إعداد بوابات الدفع (اختياري)

أضف المفاتيح التالية في ملف `.env`:

```env
# Stripe (للاختبار)
STRIPE_KEY=pk_test_your_publishable_key
STRIPE_SECRET=sk_test_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# PayPal (للاختبار)
PAYPAL_MODE=sandbox
PAYPAL_SANDBOX_CLIENT_ID=your_sandbox_client_id
PAYPAL_SANDBOX_CLIENT_SECRET=your_sandbox_client_secret
```

## التحقق من التثبيت

```bash
# فحص الجداول
php artisan migrate:status

# فحص البيانات
php artisan tinker
>>> \App\Models\User::count()
>>> \App\Models\Website::count()

# فحص الـ routes
php artisan route:list --name=admin
```

## مشاكل شائعة وحلولها

### MySQL Connection Error
```bash
# تأكد من تشغيل MySQL
brew services start mysql  # macOS
sudo systemctl start mysql # Linux

# فحص الاتصال
mysql -u root -p -e "SHOW DATABASES;"
```

### Migration Error
```bash
# إعادة تشغيل migrations
php artisan migrate:fresh --seed --seeder=AdminUserSeeder

# في حال الأخطاء المستمرة
php artisan migrate:reset
php artisan migrate
```

### Permissions Error
```bash
# إصلاح صلاحيات المجلدات
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

🎉 **تهانينا!** النظام جاهز للاستخدام.

📚 للمزيد من التفاصيل، راجع ملف `README.md` الكامل.