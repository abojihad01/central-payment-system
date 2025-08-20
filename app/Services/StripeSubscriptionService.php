<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PaymentAccount;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Subscription as StripeSubscription;

class StripeSubscriptionService
{
    public function createProductAndPrice(Plan $plan, PaymentAccount $account): array
    {
        $credentials = $account->credentials;
        
        if (!$credentials || !isset($credentials['secret_key'])) {
            throw new \Exception('Stripe credentials not found');
        }
        
        Stripe::setApiKey($credentials['secret_key']);
        
        // Create product
        $product = Product::create([
            'name' => $plan->name,
            'description' => $plan->description,
            'metadata' => [
                'plan_id' => $plan->id,
                'duration_days' => $plan->duration_days,
            ]
        ]);
        
        // Create price based on plan type
        $priceData = [
            'unit_amount' => (int)($plan->price * 100), // Stripe uses cents
            'currency' => strtolower($plan->currency),
            'product' => $product->id,
        ];
        
        // Check if it's a recurring subscription
        if ($plan->subscription_type === 'recurring' && $plan->billing_interval) {
            // Map our interval format to Stripe's format
            $stripeInterval = match($plan->billing_interval) {
                'daily' => 'day',
                'weekly' => 'week',
                'monthly' => 'month',
                'quarterly' => 'month', // Stripe doesn't have quarterly, use month with count 3
                'yearly' => 'year',
                default => 'month'
            };
            
            $intervalCount = $plan->billing_interval_count ?? 1;
            
            // Handle quarterly billing (convert to 3-month intervals)
            if ($plan->billing_interval === 'quarterly') {
                $intervalCount = ($intervalCount * 3); // 1 quarter = 3 months
            }
            
            $priceData['recurring'] = [
                'interval' => $stripeInterval,
                'interval_count' => $intervalCount
            ];
            
            // Add trial period if specified
            if ($plan->trial_period_days && $plan->trial_period_days > 0) {
                $priceData['recurring']['trial_period_days'] = $plan->trial_period_days;
            }
        }
        
        \Log::info('Creating Stripe price', [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'subscription_type' => $plan->subscription_type,
            'price_data' => $priceData
        ]);
        
        $price = Price::create($priceData);
        
        \Log::info('Stripe product and price created', [
            'plan_id' => $plan->id,
            'product_id' => $product->id,
            'price_id' => $price->id,
            'is_recurring' => isset($priceData['recurring']),
            'recurring_details' => $priceData['recurring'] ?? null
        ]);
        
        return [
            'product' => $product,
            'price' => $price
        ];
    }
    
    public function createSubscriptionCheckout(array $data, $request, PaymentAccount $account): string
    {
        $credentials = $account->credentials;
        Stripe::setApiKey($credentials['secret_key']);
        
        $plan = Plan::find($data['plan_id']);
        
        // Get Stripe product/price for this specific account
        $stripePriceId = $this->getStripePriceIdForAccount($plan, $account);
        
        if (!$stripePriceId) {
            // Create product and price for this account
            $result = $this->createProductAndPrice($plan, $account);
            $stripePriceId = $result['price']->id;
            
            // Save to plan metadata under the account-specific section
            $this->savePriceIdToAccount($plan, $account, $result['product']->id, $stripePriceId);
        }
        
        $sessionData = [
            'payment_method_types' => ['card'],
            'customer_email' => $request->input('email'),
            'success_url' => url('/payment/verify-session/{CHECKOUT_SESSION_ID}'),
            'cancel_url' => $data['failure_url'] . '?reason=cancelled',
            'metadata' => [
                'link_id' => $data['link_id'],
                'website_id' => $data['website_id'],
                'plan_id' => $data['plan_id'],
                'customer_email' => $request->input('email'),
                'customer_phone' => $request->input('phone', ''),
                'payment_account_id' => $account->id,
                'is_promoted_method' => $data['is_promoted_method'] ?? false,
                'selected_payment_method' => $data['selected_payment_method'] ?? 'stripe',
            ]
        ];
        
        // Check if it's a recurring subscription or one-time payment
        if ($this->isRecurringPlan($plan)) {
            \Log::info('Creating Stripe subscription checkout', [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'subscription_type' => $plan->subscription_type,
                'billing_interval' => $plan->billing_interval,
                'stripe_price_id' => $stripePriceId,
                'is_promoted_method' => $data['is_promoted_method'] ?? false
            ]);
            
            $sessionData['mode'] = 'subscription';
            $lineItemData = [
                'price' => $stripePriceId,
                'quantity' => 1,
            ];

            // إضافة metadata للعرض المُروج (سيتم تطبيق الشهر المجاني في webhook)
            if ($data['is_promoted_method'] ?? false) {
                $sessionData['subscription_data'] = [
                    'metadata' => [
                        'free_month_applied' => 'true',
                        'promotion_reason' => 'less_used_payment_method'
                    ]
                ];
                
                \Log::info('Applied free month promotion metadata for less used payment method', [
                    'plan_id' => $plan->id,
                    'payment_method' => $data['selected_payment_method'],
                    'note' => 'Free month will be applied via expires_at calculation in webhook'
                ]);
            }

            $sessionData['line_items'] = [$lineItemData];
        } else {
            \Log::info('Creating Stripe one-time payment checkout', [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'subscription_type' => $plan->subscription_type
            ]);
            $sessionData['mode'] = 'payment';
            $sessionData['line_items'] = [[
                'price_data' => [
                    'currency' => strtolower($data['currency']),
                    'product_data' => [
                        'name' => $data['plan_name'],
                        'description' => $data['plan_description'],
                    ],
                    'unit_amount' => (int)($data['price'] * 100),
                ],
                'quantity' => 1,
            ]];
        }
        
        $session = StripeSession::create($sessionData);
        
        // Store session info for payment record creation
        session(['stripe_session_id' => $session->id]);
        
        return $session->url;
    }
    
    private function isRecurringPlan(Plan $plan): bool
    {
        // Check if plan is meant to be recurring using the new subscription_type field
        return $plan->subscription_type === 'recurring';
    }
    
    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            $subscription = StripeSubscription::retrieve($subscriptionId);
            $subscription->cancel();
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to cancel Stripe subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function updateSubscription(string $subscriptionId, array $updates): bool
    {
        try {
            StripeSubscription::update($subscriptionId, $updates);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to update Stripe subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Create subscription from completed payment
     */
    public function createSubscriptionFromPayment(\App\Models\Payment $payment): \App\Models\Subscription
    {
        // Ensure payment has required relationships loaded
        if (!$payment->generatedLink) {
            $payment->load('generatedLink.plan');
        }
        
        if (!$payment->generatedLink) {
            throw new \Exception('Payment does not have an associated generated link');
        }
        
        if (!$payment->generatedLink->plan) {
            throw new \Exception('Payment generated link does not have an associated plan');
        }
        // Create or find customer first
        $customer = \App\Models\Customer::firstOrCreate([
            'email' => $payment->customer_email,
        ], [
            'customer_id' => 'cust_' . \Str::random(16),
            'email' => $payment->customer_email,
            'phone' => $payment->customer_phone,
            'first_name' => explode('@', $payment->customer_email)[0],
            'status' => 'active',
            'risk_score' => 10,
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
            'tags' => ['payment_link'],
            'first_purchase_at' => now(),
            'acquisition_source' => 'payment_link',
            'marketing_consent' => false,
            'email_verified' => false
        ]);
        
        // Create subscription
        $subscriptionData = [
            'subscription_id' => 'sub_' . \Str::random(24),
            'payment_id' => $payment->id,
            'website_id' => $payment->generatedLink->website_id,
            'plan_id' => $payment->generatedLink->plan_id,
            'customer_email' => $payment->customer_email,
            'customer_phone' => $payment->customer_phone,
            'status' => 'active',
            'starts_at' => now(),
            'plan_data' => [
                'name' => $payment->generatedLink->plan->name,
                'price' => $payment->amount,
                'currency' => $payment->currency,
                'features' => $payment->generatedLink->plan->features
            ]
        ];
        
        // Check if this payment used the promoted method (less used gateway)
        $gatewayResponse = $payment->gateway_response ?? [];
        $isPromotedMethod = false;
        
        // Check Stripe metadata for promoted method flag
        if ($payment->payment_gateway === 'stripe' && isset($gatewayResponse['metadata']['is_promoted_method'])) {
            $isPromotedMethod = ($gatewayResponse['metadata']['is_promoted_method'] === 'true');
        }
        
        // For PayPal, check through payment creation data (you'd store this during creation)
        if ($payment->payment_gateway === 'paypal') {
            // You could store this information during PayPal payment creation
            $paymentMetadata = $gatewayResponse['metadata'] ?? [];
            $isPromotedMethod = ($paymentMetadata['is_promoted_method'] ?? false);
        }

        // Check if it's a recurring subscription
        $plan = $payment->generatedLink->plan;
        if ($plan && $plan->subscription_type === 'recurring' && $plan->billing_interval) {
            $subscriptionData['expires_at'] = null; // Recurring subscription
            $nextBillingDate = $this->calculateNextBillingDate($plan->billing_interval);
            
            // Add free month if customer used promoted method
            if ($isPromotedMethod) {
                $nextBillingDate = $nextBillingDate->addMonth(); // Add extra month
                $subscriptionData['promotion_applied'] = 'free_month_less_used_gateway';
                $subscriptionData['promotion_details'] = [
                    'type' => 'free_month',
                    'reason' => 'Used less popular payment method',
                    'payment_method' => $payment->payment_gateway,
                    'applied_at' => now(),
                    'extra_days' => 30
                ];
                
                \Log::info('Free month promotion applied to subscription', [
                    'payment_id' => $payment->id,
                    'payment_method' => $payment->payment_gateway,
                    'original_billing_date' => $this->calculateNextBillingDate($plan->billing_interval),
                    'new_billing_date' => $nextBillingDate
                ]);
            }
            
            $subscriptionData['next_billing_date'] = $nextBillingDate;
        } else {
            // One-time payment, set expiry based on plan duration
            $baseDuration = $plan && $plan->duration_days ? $plan->duration_days : 30;
            $expiryDate = now()->addDays($baseDuration);
            
            // Add free month for one-time payments too
            if ($isPromotedMethod) {
                $expiryDate = $expiryDate->addDays(30); // Add 30 extra days
                $subscriptionData['promotion_applied'] = 'free_month_less_used_gateway';
                $subscriptionData['promotion_details'] = [
                    'type' => 'free_month',
                    'reason' => 'Used less popular payment method',
                    'payment_method' => $payment->payment_gateway,
                    'applied_at' => now(),
                    'extra_days' => 30
                ];
                
                \Log::info('Free month promotion applied to one-time subscription', [
                    'payment_id' => $payment->id,
                    'payment_method' => $payment->payment_gateway,
                    'base_duration' => $baseDuration,
                    'total_duration' => $baseDuration + 30
                ]);
            }
            
            $subscriptionData['expires_at'] = $expiryDate;
        }
        
        $subscription = \App\Models\Subscription::create($subscriptionData);
        
        // Update customer statistics
        $customer->increment('total_subscriptions');
        $customer->increment('active_subscriptions');
        $customer->increment('total_spent', $payment->amount);
        $customer->increment('successful_payments');
        $customer->update(['lifetime_value' => $customer->total_spent]);
        
        // Create customer events
        \App\Models\CustomerEvent::create([
            'customer_id' => $customer->id,
            'event_type' => 'payment_completed',
            'description' => 'Payment completed for ' . $payment->payment_gateway . ' - Amount: ' . $payment->amount . ' ' . $payment->currency,
            'metadata' => [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->payment_gateway,
                'gateway_payment_id' => $payment->gateway_payment_id
            ],
            'source' => 'payment_verification_api',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
        
        \App\Models\CustomerEvent::create([
            'customer_id' => $customer->id,
            'event_type' => 'subscription_created',
            'description' => 'New subscription created for plan: ' . $subscription->plan_data['name'],
            'metadata' => [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $subscription->plan_data['name'],
                'price' => $subscription->plan_data['price'],
                'duration_days' => $plan->duration_days ?? null,
                'expires_at' => $subscription->expires_at?->toDateTimeString()
            ],
            'source' => 'payment_verification_api'
        ]);
        
        // Update payment account statistics
        if ($payment->payment_account_id) {
            $account = \App\Models\PaymentAccount::find($payment->payment_account_id);
            if ($account) {
                $account->incrementSuccessfulTransaction($payment->amount);
            }
        }
        
        // Mark link as used if single use
        if ($payment->generatedLink && $payment->generatedLink->single_use) {
            $payment->generatedLink->update(['is_used' => true, 'used_at' => now()]);
        }
        
        \Log::info('Subscription created from payment verification', [
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->subscription_id,
            'customer_email' => $payment->customer_email
        ]);
        
        return $subscription;
    }
    
    /**
     * Calculate next billing date based on interval
     */
    private function calculateNextBillingDate(string $interval): \Carbon\Carbon
    {
        return match($interval) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly' => now()->addYear(),
            default => now()->addMonth()
        };
    }

    /**
     * Get Stripe price ID for a plan in a specific account
     */
    private function getStripePriceIdForAccount(Plan $plan, PaymentAccount $account): ?string
    {
        $metadata = $plan->metadata ?? [];
        
        // Check new format first (stripe_products array)
        $stripeProducts = $metadata['stripe_products'] ?? [];
        if (isset($stripeProducts[$account->id]['price_id'])) {
            return $stripeProducts[$account->id]['price_id'];
        }

        // Fallback to old format if this is the primary account
        $primaryAccountId = $metadata['stripe_account_id'] ?? null;
        if ($primaryAccountId == $account->id && isset($metadata['stripe_price_id'])) {
            return $metadata['stripe_price_id'];
        }

        return null;
    }

    /**
     * Save price ID to account-specific metadata
     */
    private function savePriceIdToAccount(Plan $plan, PaymentAccount $account, string $productId, string $priceId): void
    {
        $metadata = $plan->metadata ?? [];
        
        // Initialize stripe_products array if not exists
        if (!isset($metadata['stripe_products'])) {
            $metadata['stripe_products'] = [];
        }
        
        // Save product and price info for this account
        $metadata['stripe_products'][$account->id] = [
            'product_id' => $productId,
            'price_id' => $priceId
        ];
        
        // Also update the account_id for backwards compatibility if not set
        if (!isset($metadata['stripe_account_id'])) {
            $metadata['stripe_account_id'] = $account->id;
            $metadata['stripe_product_id'] = $productId;
            $metadata['stripe_price_id'] = $priceId;
        }
        
        $plan->update(['metadata' => $metadata]);
        
        \Log::info('Saved Stripe product/price for account', [
            'plan_id' => $plan->id,
            'account_id' => $account->id,
            'product_id' => $productId,
            'price_id' => $priceId
        ]);
    }
}