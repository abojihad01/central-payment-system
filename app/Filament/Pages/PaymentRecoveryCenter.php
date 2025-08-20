<?php

namespace App\Filament\Pages;

use App\Models\Payment;
use App\Jobs\ProcessPendingPayment;
use Filament\Pages\Page;
use Filament\Actions;
use Illuminate\Support\Facades\Artisan;
use Stripe\StripeClient;

class PaymentRecoveryCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationGroup = 'النظام';
    protected static ?string $navigationLabel = 'مركز استعادة المدفوعات (إرث)';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.payment-recovery-center';

    public $lostPayments = [];
    public $healthStats = [];
    public $recentRecoveries = [];
    public $isScanning = false;

    public function mount()
    {
        $this->loadLostPayments();
        $this->loadHealthStats();
        $this->loadRecentRecoveries();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scan_lost_payments')
                ->label('بحث عن الدفعات المفقودة')
                ->icon('heroicon-o-magnifying-glass')
                ->color('info')
                ->action(function () {
                    $this->scanForLostPayments();
                }),

            Actions\Action::make('recover_all_found')
                ->label('استعادة جميع المكتشفة')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->action(function () {
                    $this->recoverAllFoundPayments();
                })
                ->requiresConfirmation()
                ->visible(fn () => count($this->lostPayments) > 0),

            Actions\Action::make('run_health_check')
                ->label('فحص صحة النظام')
                ->icon('heroicon-o-heart')
                ->color('warning')
                ->action(function () {
                    $this->runHealthCheck();
                }),

            Actions\Action::make('view_webhook_logs')
                ->label('سجلات Webhooks')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->modalHeading('Webhook Logs')
                ->modalContent(function () {
                    return view('filament.modals.webhook-logs');
                }),
        ];
    }

    public function loadLostPayments()
    {
        // Find potentially lost payments
        $suspiciousPayments = Payment::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(10))
            ->where('created_at', '>', now()->subHours(48))
            ->whereNotNull('gateway_session_id')
            ->limit(20)
            ->get();

        $this->lostPayments = [];
        
        foreach ($suspiciousPayments as $payment) {
            try {
                $gatewayStatus = $this->checkPaymentInGateway($payment);
                if ($gatewayStatus['status'] === 'completed') {
                    $this->lostPayments[] = [
                        'payment' => $payment,
                        'gateway_status' => $gatewayStatus,
                        'lost_duration' => $payment->created_at->diffForHumans(),
                        'amount' => $payment->amount,
                        'customer_email' => $payment->customer_email,
                    ];
                }
            } catch (\Exception $e) {
                // Skip payments that can't be checked
            }
        }
    }

    public function loadHealthStats()
    {
        $this->healthStats = [
            'total_pending' => Payment::where('status', 'pending')->count(),
            'stuck_payments' => Payment::where('status', 'pending')
                ->where('created_at', '<', now()->subHour())
                ->count(),
            'success_rate_24h' => $this->calculateSuccessRate(24),
            'webhook_failures' => $this->getWebhookFailureCount(),
            'last_recovery_run' => cache('last_recovery_scan', 'Never'),
            'queue_health' => [
                'pending_jobs' => \DB::table('jobs')->whereNull('reserved_at')->count(),
                'failed_jobs' => \DB::table('failed_jobs')->count(),
            ]
        ];
    }

    public function loadRecentRecoveries()
    {
        $this->recentRecoveries = Payment::where('notes', 'like', '%recovered%')
            ->orWhere('notes', 'like', '%webhook%')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'customer_email' => $payment->customer_email,
                    'recovered_at' => $payment->updated_at->diffForHumans(),
                    'method' => str_contains($payment->notes, 'webhook') ? 'Webhook' : 'Manual Recovery',
                ];
            })->toArray();
    }

    public function scanForLostPayments()
    {
        $this->isScanning = true;
        
        try {
            // Run the recovery command
            Artisan::call('payments:recover-lost', [
                '--limit' => 50,
                '--min-age' => 10,
                '--max-age' => 2880,
                '--dry-run' => true
            ]);

            cache(['last_recovery_scan' => now()->format('Y-m-d H:i:s')], 3600);
            
            // Reload the data
            $this->loadLostPayments();
            $this->loadHealthStats();

            \Filament\Notifications\Notification::make()
                ->title('تم البحث عن الدفعات المفقودة')
                ->body('تم العثور على ' . count($this->lostPayments) . ' دفعة مفقودة')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('خطأ في البحث')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->isScanning = false;
    }

    public function recoverAllFoundPayments()
    {
        $recoveredCount = 0;

        foreach ($this->lostPayments as $lostPayment) {
            try {
                $payment = $lostPayment['payment'];
                $gatewayStatus = $lostPayment['gateway_status'];

                $payment->update([
                    'status' => 'completed',
                    'confirmed_at' => now(),
                    'paid_at' => now(),
                    'gateway_response' => array_merge(
                        $payment->gateway_response ?? [],
                        $gatewayStatus['gateway_data'] ?? [],
                        ['recovered_at' => now(), 'recovery_method' => 'admin_panel']
                    ),
                    'notes' => trim(($payment->notes ?? '') . "\nPayment recovered via Admin Panel at " . now())
                ]);

                // Handle subscription and invoice creation directly for recovered payments
                $this->processRecoveredPayment($payment);
                
                $recoveredCount++;

            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->title('خطأ في استعادة الدفعة')
                    ->body("Payment {$payment->id}: " . $e->getMessage())
                    ->danger()
                    ->send();
            }
        }

        if ($recoveredCount > 0) {
            \Filament\Notifications\Notification::make()
                ->title('تم استعادة الدفعات')
                ->body("تم استعادة {$recoveredCount} دفعة بنجاح")
                ->success()
                ->send();

            // Reload data
            $this->loadLostPayments();
            $this->loadHealthStats();
            $this->loadRecentRecoveries();
        }
    }

    public function recoverSinglePayment($paymentId)
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            $gatewayStatus = $this->checkPaymentInGateway($payment);

            if ($gatewayStatus['status'] === 'completed') {
                $payment->update([
                    'status' => 'completed',
                    'confirmed_at' => now(),
                    'paid_at' => now(),
                    'gateway_response' => array_merge(
                        $payment->gateway_response ?? [],
                        $gatewayStatus['gateway_data'] ?? [],
                        ['recovered_at' => now(), 'recovery_method' => 'admin_panel_single']
                    ),
                    'notes' => trim(($payment->notes ?? '') . "\nPayment recovered individually via Admin Panel at " . now())
                ]);

                // Handle subscription and invoice creation directly for recovered payments
                $this->processRecoveredPayment($payment);

                \Filament\Notifications\Notification::make()
                    ->title('تم استعادة الدفعة')
                    ->body("Payment ID: {$payment->id} تم استعادتها بنجاح")
                    ->success()
                    ->send();

                $this->loadLostPayments();
                $this->loadRecentRecoveries();
            } else {
                \Filament\Notifications\Notification::make()
                    ->title('لا يمكن استعادة الدفعة')
                    ->body('الدفعة غير مكتملة في البوابة')
                    ->warning()
                    ->send();
            }

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('خطأ في الاستعادة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function runHealthCheck()
    {
        try {
            Artisan::call('payments:monitor-health', ['--alert-threshold' => 5]);
            
            $this->loadHealthStats();

            \Filament\Notifications\Notification::make()
                ->title('تم فحص صحة النظام')
                ->body('تم تحديث الإحصائيات')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('خطأ في فحص الصحة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function checkPaymentInGateway(Payment $payment): array
    {
        if (strtolower($payment->payment_gateway) !== 'stripe') {
            return ['status' => 'unknown', 'message' => 'Gateway not supported'];
        }

        $paymentAccount = $payment->paymentAccount;
        if (!$paymentAccount || !isset($paymentAccount->credentials['secret_key'])) {
            return ['status' => 'error', 'message' => 'Credentials not found'];
        }

        $stripe = new StripeClient($paymentAccount->credentials['secret_key']);

        if ($payment->gateway_session_id && str_starts_with($payment->gateway_session_id, 'cs_')) {
            $session = $stripe->checkout->sessions->retrieve($payment->gateway_session_id);
            
            if ($session->status === 'complete' && $session->payment_status === 'paid') {
                return [
                    'status' => 'completed',
                    'message' => 'Checkout session completed and paid',
                    'gateway_data' => [
                        'session_id' => $session->id,
                        'status' => $session->status,
                        'payment_status' => $session->payment_status,
                        'amount_total' => $session->amount_total,
                    ]
                ];
            }
        }

        return ['status' => 'pending', 'message' => 'Not completed in gateway'];
    }

    protected function calculateSuccessRate(int $hours): float
    {
        $total = Payment::where('created_at', '>=', now()->subHours($hours))->count();
        if ($total === 0) return 100.0;
        
        $successful = Payment::where('created_at', '>=', now()->subHours($hours))
                            ->where('status', 'completed')
                            ->count();
        
        return round(($successful / $total) * 100, 2);
    }

    protected function getWebhookFailureCount(): int
    {
        // This would check webhook failure logs
        // For now, return a placeholder
        return 0;
    }

    public function refresh()
    {
        $this->loadLostPayments();
        $this->loadHealthStats();
        $this->loadRecentRecoveries();
        
        \Filament\Notifications\Notification::make()
            ->title('تم تحديث البيانات')
            ->success()
            ->send();
    }

    /**
     * Process recovered payment to create subscription and invoice
     */
    protected function processRecoveredPayment(Payment $payment)
    {
        try {
            // Check if payment already has a subscription to avoid duplicates
            if ($payment->subscription) {
                return;
            }

            // Handle renewal payments - check both conditions more carefully
            if ($payment->is_renewal) {
                // If has subscription_id, try to find the subscription
                if ($payment->subscription_id) {
                    $existingSubscription = \App\Models\Subscription::find($payment->subscription_id);
                    if ($existingSubscription) {
                        $this->handleRenewalPayment($payment);
                        return;
                    }
                }
                
                // If is_renewal is true but no valid subscription_id, try to find subscription by customer email
                $existingSubscription = \App\Models\Subscription::where('customer_email', $payment->customer_email)
                    ->where('status', 'active')
                    ->orderBy('created_at', 'desc')
                    ->first();
                    
                if ($existingSubscription) {
                    // Update payment with correct subscription_id
                    $payment->update(['subscription_id' => $existingSubscription->id]);
                    $this->handleRenewalPayment($payment);
                    return;
                }
                
                // If no existing subscription found for renewal, treat as new subscription but log warning
                \Log::warning('Renewal payment recovered but no existing subscription found', [
                    'payment_id' => $payment->id,
                    'customer_email' => $payment->customer_email,
                    'subscription_id' => $payment->subscription_id
                ]);
            }

            // Create or update customer record
            $customer = $this->createOrUpdateCustomer($payment);
            
            // Get plan and website information
            $planId = null;
            $websiteId = null;
            $planName = 'Recovered Payment Plan';
            $plan = null;
            
            if ($payment->generatedLink) {
                $planId = $payment->generatedLink->plan_id;
                $websiteId = $payment->generatedLink->website_id;
                if ($payment->generatedLink->plan) {
                    $plan = $payment->generatedLink->plan;
                    $planName = $plan->name;
                }
            } elseif ($payment->plan_id) {
                // Try direct plan relationship
                $planId = $payment->plan_id;
                $plan = \App\Models\Plan::find($planId);
                if ($plan) {
                    $planName = $plan->name;
                    $websiteId = $plan->website_id;
                }
            }
            
            // Calculate dates based on billing interval
            $startsAt = now();
            if ($plan) {
                $expiryDate = $plan->calculateExpiryDate($startsAt);
                $durationDays = $plan->getSubscriptionDurationDays();
            } else {
                $expiryDate = $startsAt->copy()->addDays(30);
                $durationDays = 30;
            }
            $nextBillingDate = $expiryDate->copy(); // For simple plans, next billing = expiry
            
            // Create new subscription
            $subscription = \App\Models\Subscription::create([
                'subscription_id' => \Illuminate\Support\Str::uuid(),
                'payment_id' => $payment->id,
                'plan_id' => $planId,
                'website_id' => $websiteId,
                'customer_email' => $payment->customer_email,
                'customer_phone' => $payment->customer_phone,
                'status' => 'active',
                'starts_at' => $startsAt,
                'expires_at' => $expiryDate,
                'next_billing_date' => $nextBillingDate,
                'billing_cycle_count' => 1,
                'last_billing_date' => now(),
                'plan_data' => [
                    'name' => $planName,
                    'price' => $payment->amount,
                    'duration_days' => $durationDays
                ]
            ]);
            
            // Create invoice for the payment
            $invoice = \App\Models\Invoice::create([
                'invoice_number' => 'INV-REC-' . date('Y') . '-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'customer_email' => $payment->customer_email,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency ?? 'USD',
                'status' => 'paid',
                'issued_at' => now(),
                'paid_at' => now(),
                'line_items' => [
                    [
                        'description' => $planName . ' (Recovered Payment)',
                        'amount' => (float) $payment->amount,
                        'currency' => $payment->currency ?? 'USD'
                    ]
                ]
            ]);

            // Update customer statistics
            $this->updateCustomerStatistics($customer, $subscription, $payment);

            // Dispatch events
            event(new \App\Events\PaymentCompleted($payment));
            event(new \App\Events\SubscriptionCreated($subscription));

        } catch (\Exception $e) {
            \Log::error('Error processing recovered payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle renewal payment recovery
     */
    protected function handleRenewalPayment(Payment $payment)
    {
        $subscription = \App\Models\Subscription::find($payment->subscription_id);
        if (!$subscription) {
            throw new \Exception('Subscription not found for renewal payment');
        }

        $currentExpiryDate = $subscription->expires_at;
        
        // Try multiple ways to get duration days
        $durationDays = null;
        
        // 1. From subscription plan_data
        if (isset($subscription->plan_data['duration_days'])) {
            $durationDays = $subscription->plan_data['duration_days'];
        }
        // 2. From payment's generated link plan
        elseif ($payment->generatedLink && $payment->generatedLink->plan) {
            $durationDays = $payment->generatedLink->plan->duration_days;
        }
        // 3. From subscription's plan relationship
        elseif ($subscription->plan) {
            $durationDays = $subscription->plan->duration_days;
        }
        // 4. Default fallback
        else {
            $durationDays = 30;
            \Log::warning('Could not determine renewal duration, using 30 days default', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id
            ]);
        }
        
        $newExpiryDate = $currentExpiryDate > now() 
            ? $currentExpiryDate->addDays($durationDays)
            : now()->addDays($durationDays);
            
        // Calculate next billing date (usually same as expiry for simple plans, or +duration for recurring)
        $newNextBillingDate = $newExpiryDate->copy();
        if ($subscription->plan && method_exists($subscription->plan, 'isRecurring') && $subscription->plan->isRecurring()) {
            // For recurring plans, next billing is after the new expiry
            $newNextBillingDate = $newExpiryDate->copy()->addDays($durationDays);
        }
            
        $subscription->update([
            'expires_at' => $newExpiryDate,
            'next_billing_date' => $newNextBillingDate,
            'billing_cycle_count' => ($subscription->billing_cycle_count ?? 0) + 1,
            'last_billing_date' => now()
        ]);
        
        // Get plan name for invoice
        $planName = 'Plan'; // Default fallback
        if (isset($subscription->plan_data['name'])) {
            $planName = $subscription->plan_data['name'];
        } elseif ($payment->generatedLink && $payment->generatedLink->plan) {
            $planName = $payment->generatedLink->plan->name;
        } elseif ($subscription->plan) {
            $planName = $subscription->plan->name;
        }

        // Create renewal invoice
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'INV-RENEWAL-REC-' . date('Y') . '-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
            'customer_email' => $payment->customer_email,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency ?? 'USD',
            'status' => 'paid',
            'type' => 'renewal',
            'issued_at' => now(),
            'paid_at' => now(),
            'line_items' => [
                [
                    'description' => 'Subscription renewal - ' . $planName . ' (Recovered Payment)',
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency ?? 'USD'
                ]
            ]
        ]);

        // Update customer for renewal
        $this->updateCustomerForRenewal($subscription, $payment);
        
        // Dispatch events for renewal recovery
        event(new \App\Events\PaymentCompleted($payment));
        try {
            if (class_exists('\App\Events\SubscriptionRenewed')) {
                event(new \App\Events\SubscriptionRenewed($subscription, $payment));
            }
        } catch (\Exception $e) {
            \Log::warning('Could not dispatch SubscriptionRenewed event', ['error' => $e->getMessage()]);
        }
        
        // Send notification for renewal
        try {
            \Illuminate\Support\Facades\Notification::send($subscription, new \App\Notifications\SubscriptionRenewed($subscription, $payment));
        } catch (\Exception $e) {
            \Log::warning('Could not send renewal notification', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create or update customer record
     */
    protected function createOrUpdateCustomer(Payment $payment): \App\Models\Customer
    {
        $customer = \App\Models\Customer::firstOrCreate(
            ['email' => $payment->customer_email],
            [
                'customer_id' => \Illuminate\Support\Str::uuid(),
                'phone' => $payment->customer_phone,
                'status' => 'active',
                'risk_level' => 'low',
                'total_subscriptions' => 0,
                'active_subscriptions' => 0,
                'lifetime_value' => 0,
                'total_spent' => 0,
                'successful_payments' => 0,
                'failed_payments' => 0,
                'chargebacks' => 0,
                'refunds' => 0,
                'preferences' => [],
                'payment_methods' => [$payment->payment_gateway],
                'subscription_history' => [],
                'tags' => [],
                'first_purchase_at' => now(),
                'last_purchase_at' => now()
            ]
        );

        if (!$customer->wasRecentlyCreated) {
            $customer->update([
                'last_purchase_at' => now(),
                'phone' => $payment->customer_phone ?? $customer->phone,
            ]);
        }

        // Log customer event for recovered payment
        \App\Models\CustomerEvent::create([
            'customer_id' => $customer->id,
            'event_type' => 'payment_recovered',
            'description' => 'Payment recovered and processed - Amount: ' . $payment->amount . ' ' . $payment->currency,
            'metadata' => [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->payment_gateway,
                'recovery_method' => 'admin_panel',
                'recovered_at' => now()
            ],
            'source' => 'payment_recovery_center',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        return $customer;
    }

    /**
     * Update customer statistics after subscription creation
     */
    protected function updateCustomerStatistics(\App\Models\Customer $customer, \App\Models\Subscription $subscription, Payment $payment): void
    {
        $totalSubscriptions = $customer->total_subscriptions + 1;
        $activeSubscriptions = \App\Models\Subscription::where('customer_email', $customer->email)
            ->where('status', 'active')->count();
        $totalSpent = $customer->total_spent + (float) $payment->amount;
        $successfulPayments = $customer->successful_payments + 1;
        $lifetimeValue = \App\Models\Payment::where('customer_email', $customer->email)
            ->where('status', 'completed')->sum('amount');

        $customer->update([
            'total_subscriptions' => $totalSubscriptions,
            'active_subscriptions' => $activeSubscriptions,
            'total_spent' => $totalSpent,
            'successful_payments' => $successfulPayments,
            'lifetime_value' => $lifetimeValue
        ]);

        // Log subscription creation event
        \App\Models\CustomerEvent::create([
            'customer_id' => $customer->id,
            'event_type' => 'subscription_created_from_recovery',
            'description' => 'New subscription created from recovered payment for plan: ' . $subscription->plan_data['name'],
            'metadata' => [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $subscription->plan_data['name'],
                'price' => $subscription->plan_data['price'],
                'duration_days' => $subscription->plan_data['duration_days'],
                'expires_at' => $subscription->expires_at->toDateTimeString(),
                'recovery_method' => 'admin_panel'
            ],
            'source' => 'payment_recovery_center'
        ]);
    }

    /**
     * Update customer for renewal payment
     */
    protected function updateCustomerForRenewal(\App\Models\Subscription $subscription, Payment $payment): void
    {
        $customer = \App\Models\Customer::where('email', $subscription->customer_email)->first();
        
        if ($customer) {
            $customer->increment('successful_payments');
            $customer->increment('total_spent', (float) $payment->amount);
            $customer->update([
                'last_purchase_at' => now(),
                'lifetime_value' => \App\Models\Payment::where('customer_email', $customer->email)
                    ->where('status', 'completed')->sum('amount')
            ]);

            // Log renewal event from recovery
            \App\Models\CustomerEvent::create([
                'customer_id' => $customer->id,
                'event_type' => 'subscription_renewed_from_recovery',
                'description' => 'Subscription renewed from recovered payment for plan: ' . ($subscription->plan_data['name'] ?? 'Plan'),
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'gateway' => $payment->payment_gateway,
                    'new_expires_at' => $subscription->expires_at->toDateTimeString(),
                    'recovery_method' => 'admin_panel'
                ],
                'source' => 'payment_recovery_center'
            ]);
        }
    }
}