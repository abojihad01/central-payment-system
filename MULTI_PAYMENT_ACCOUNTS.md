# 🏦 Multiple Payment Accounts System

## 📋 Overview
The Central Payment System supports **multiple payment accounts** for each gateway, allowing you to:
- Use different Stripe accounts for different websites/clients
- Route payments based on amount, region, or business rules
- Implement load balancing across multiple accounts
- Separate live and sandbox accounts per client

## 🏗️ Architecture

### Database Structure
```sql
payment_gateways table:
├── id (Stripe, PayPal, etc.)
├── name
├── is_active

payment_accounts table:
├── id
├── payment_gateway_id (FK)
├── account_id (unique identifier)
├── name (display name)
├── credentials (encrypted JSON)
├── is_active
├── is_sandbox
├── settings (JSON configuration)
```

### Credentials Storage
```json
// Stripe Account Credentials
{
  "secret_key": "sk_live_xxxxx",
  "publishable_key": "pk_live_xxxxx",
  "webhook_secret": "whsec_xxxxx"
}

// PayPal Account Credentials  
{
  "client_id": "xxxxx",
  "client_secret": "xxxxx",
  "webhook_id": "xxxxx"
}
```

## 🔧 Implementation Examples

### 1. Creating Payment Accounts

```php
// Create Stripe Account for Client A
PaymentAccount::create([
    'payment_gateway_id' => 1, // Stripe
    'account_id' => 'client-a-stripe',
    'name' => 'Client A - Main Stripe Account',
    'credentials' => [
        'secret_key' => 'sk_live_client_a_xxxxx',
        'publishable_key' => 'pk_live_client_a_xxxxx',
        'webhook_secret' => 'whsec_client_a_xxxxx'
    ],
    'is_active' => true,
    'is_sandbox' => false,
    'settings' => [
        'max_daily_amount' => 50000,
        'allowed_currencies' => ['USD', 'EUR'],
        'webhook_url' => 'https://your-domain.com/webhooks/stripe/client-a'
    ]
]);

// Create PayPal Account for Client B
PaymentAccount::create([
    'payment_gateway_id' => 2, // PayPal
    'account_id' => 'client-b-paypal',
    'name' => 'Client B - PayPal Business Account',
    'credentials' => [
        'client_id' => 'paypal_client_b_xxxxx',
        'client_secret' => 'paypal_secret_b_xxxxx'
    ],
    'is_active' => true,
    'is_sandbox' => false
]);
```

### 2. Payment Processing with Multiple Accounts

```php
// In ProcessPendingPayment Job
class ProcessPendingPayment implements ShouldQueue
{
    public function handle()
    {
        // Get the specific payment account for this payment
        $paymentAccount = $this->payment->paymentAccount;
        
        if ($this->payment->payment_gateway === 'stripe') {
            $this->processStripePayment($paymentAccount);
        } elseif ($this->payment->payment_gateway === 'paypal') {
            $this->processPayPalPayment($paymentAccount);
        }
    }
    
    private function processStripePayment(PaymentAccount $account)
    {
        // Use account-specific credentials
        $stripe = new \Stripe\StripeClient($account->credentials['secret_key']);
        
        $paymentIntent = $stripe->paymentIntents->retrieve(
            $this->payment->gateway_payment_id
        );
        
        // Process based on account-specific settings
        if ($paymentIntent->status === 'succeeded') {
            $this->markPaymentCompleted();
        }
    }
}
```

### 3. Account Selection Logic

```php
// Service to select best payment account
class PaymentAccountSelector
{
    public function selectAccount($gateway, $amount, $currency, $region = null)
    {
        $accounts = PaymentAccount::where('payment_gateway_id', $gateway)
            ->active()
            ->where('is_sandbox', app()->environment('production') ? false : true)
            ->get();
            
        // Apply business rules
        $filteredAccounts = $accounts->filter(function($account) use ($amount, $currency) {
            $settings = $account->settings ?? [];
            
            // Check daily limit
            if (isset($settings['max_daily_amount'])) {
                $todayAmount = $account->payments()
                    ->whereDate('created_at', today())
                    ->sum('amount');
                    
                if ($todayAmount + $amount > $settings['max_daily_amount']) {
                    return false;
                }
            }
            
            // Check currency support
            if (isset($settings['allowed_currencies'])) {
                if (!in_array($currency, $settings['allowed_currencies'])) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Load balancing: select account with least recent usage
        return $filteredAccounts->sortBy('last_used_at')->first();
    }
}
```

## 🎛️ Admin Dashboard Management

### Account Management Interface
```php
// PaymentAccountController
class PaymentAccountController extends Controller
{
    public function index()
    {
        $accounts = PaymentAccount::with('gateway')
            ->paginate(20);
            
        return view('admin.payment-accounts.index', compact('accounts'));
    }
    
    public function create()
    {
        $gateways = PaymentGateway::active()->get();
        return view('admin.payment-accounts.create', compact('gateways'));
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'payment_gateway_id' => 'required|exists:payment_gateways,id',
            'account_id' => 'required|unique:payment_accounts',
            'name' => 'required|string|max:255',
            'credentials' => 'required|array',
            'is_sandbox' => 'boolean'
        ]);
        
        // Encrypt sensitive credentials
        $credentials = encrypt($request->credentials);
        
        PaymentAccount::create([
            'payment_gateway_id' => $request->payment_gateway_id,
            'account_id' => $request->account_id,
            'name' => $request->name,
            'credentials' => $credentials,
            'is_sandbox' => $request->is_sandbox ?? false,
            'is_active' => true
        ]);
        
        return redirect()->route('admin.payment-accounts.index')
            ->with('success', 'Payment account created successfully');
    }
}
```

## 📊 Dashboard Views

### Account Statistics
```html
<!-- resources/views/admin/payment-accounts/index.blade.php -->
<div class="payment-accounts-dashboard">
    @foreach($accounts as $account)
    <div class="account-card">
        <h3>{{ $account->name }}</h3>
        <div class="account-stats">
            <div class="stat">
                <label>Gateway:</label>
                <span>{{ $account->gateway->name }}</span>
            </div>
            <div class="stat">
                <label>Status:</label>
                <span class="{{ $account->is_active ? 'active' : 'inactive' }}">
                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <div class="stat">
                <label>Environment:</label>
                <span>{{ $account->is_sandbox ? 'Sandbox' : 'Live' }}</span>
            </div>
            <div class="stat">
                <label>Total Processed:</label>
                <span>${{ number_format($account->total_amount, 2) }}</span>
            </div>
            <div class="stat">
                <label>Success Rate:</label>
                <span>{{ $account->getSuccessRate() }}%</span>
            </div>
        </div>
        
        <div class="account-actions">
            <a href="{{ route('admin.payment-accounts.edit', $account) }}" class="btn btn-primary">
                Edit
            </a>
            <a href="{{ route('admin.payment-accounts.show', $account) }}" class="btn btn-info">
                View Details
            </a>
        </div>
    </div>
    @endforeach
</div>
```

## 🔐 Security Features

### 1. Credential Encryption
```php
// Model accessor/mutator for credentials
public function getCredentialsAttribute($value)
{
    return $value ? decrypt($value) : null;
}

public function setCredentialsAttribute($value)
{
    $this->attributes['credentials'] = $value ? encrypt($value) : null;
}
```

### 2. Account Validation
```php
public function validateCredentials()
{
    switch ($this->gateway->name) {
        case 'stripe':
            return $this->validateStripeCredentials();
        case 'paypal':
            return $this->validatePayPalCredentials();
    }
}

private function validateStripeCredentials()
{
    try {
        $stripe = new \Stripe\StripeClient($this->credentials['secret_key']);
        $stripe->accounts->retrieve();
        return true;
    } catch (\Exception $e) {
        return false;
    }
}
```

## 📈 Load Balancing & Failover

### Smart Account Selection
```php
class SmartPaymentRouter
{
    public function routePayment($amount, $currency, $region = null)
    {
        // Get all available accounts
        $accounts = $this->getAvailableAccounts($currency, $region);
        
        // Apply load balancing rules
        $account = $this->selectOptimalAccount($accounts, $amount);
        
        // Fallback if primary fails
        if (!$account || !$this->testAccount($account)) {
            $account = $this->selectFallbackAccount($accounts);
        }
        
        return $account;
    }
    
    private function selectOptimalAccount($accounts, $amount)
    {
        return $accounts
            ->sortBy(function($account) {
                // Prefer accounts with lower recent load
                return $account->payments()
                    ->where('created_at', '>', now()->subHour())
                    ->count();
            })
            ->first();
    }
}
```

## 🛠️ Configuration Examples

### Environment Variables (Only for System-Level)
```bash
# .env - Only webhook secrets and system defaults
STRIPE_WEBHOOK_SECRET=whsec_system_webhook_secret
PAYPAL_MODE=live
DEFAULT_CURRENCY=USD
PAYMENT_TIMEOUT_MINUTES=30

# No individual account keys in .env!
# All account credentials are in database
```

### Account Creation Commands
```bash
# Artisan command to create payment accounts
php artisan payment:create-account stripe "Client A Main" \
  --secret-key="sk_live_xxxxx" \
  --publishable-key="pk_live_xxxxx" \
  --webhook-secret="whsec_xxxxx" \
  --live

php artisan payment:create-account paypal "Client B Business" \
  --client-id="paypal_xxxxx" \
  --client-secret="paypal_xxxxx" \
  --live
```

## 🎯 Benefits of This Approach

1. **Scalability**: Add unlimited payment accounts without code changes
2. **Security**: Each account has isolated credentials
3. **Flexibility**: Different settings per account
4. **Reliability**: Automatic failover between accounts  
5. **Multi-tenant**: Support multiple clients/websites
6. **Load Distribution**: Spread traffic across accounts
7. **Risk Management**: Isolate issues to specific accounts

## 📝 Summary

**لا نحتاج مفاتيح ثابتة في `.env`** لأن:
- كل حساب دفع له مفاتيحه المخزنة في قاعدة البيانات
- يمكن إضافة/تعديل الحسابات من لوحة التحكم
- النظام يختار أفضل حساب تلقائياً
- أمان أعلى مع تشفير المفاتيح
- مرونة كاملة في إدارة الحسابات

هذا التصميم يجعل النظام **enterprise-ready** و **scalable** للمؤسسات الكبيرة! 🚀