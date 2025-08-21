<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Jobs\ProcessPendingPayment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\PaymentLinkService;
use App\Services\PaymentGatewayService;
use App\Services\StripeSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentLinkService $paymentLinkService,
        private PaymentGatewayService $paymentGatewayService,
        private StripeSubscriptionService $stripeSubscriptionService
    ) {}

    public function handleGetRequest(Request $request): RedirectResponse
    {
        // Handle GET requests to process-payment (usually from browser refresh/back button)
        // Extract data from query parameters and redirect to checkout page
        
        $token = $request->query('token');
        if ($token) {
            return redirect()->route('checkout', ['token' => $token])
                           ->with('error', 'Please use the payment form to process your payment.');
        }
        
        // If no token, redirect to home
        return redirect('/')->with('error', 'Invalid payment request.');
    }

    public function process(CheckoutRequest $request): RedirectResponse
    {
        $token = $request->input('token');
        $paymentMethod = $request->input('payment_method');
        
        try {
            $data = $this->paymentLinkService->validateAndDecodeToken($token);
            
            // تحديد إذا كان العميل اختار الطريقة المُفضلة (الأقل استخداماً)
            $promotedPaymentMethod = $this->paymentGatewayService->getPromotedPaymentMethod();
            $data['is_promoted_method'] = ($paymentMethod === $promotedPaymentMethod);
            $data['selected_payment_method'] = $paymentMethod;
            
            if ($paymentMethod === 'stripe') {
                return $this->processStripePayment($request, $data);
            } elseif ($paymentMethod === 'paypal') {
                return $this->processPayPalPayment($request, $data);
            }
            
            return redirect($data['failure_url'] . '?error=invalid_payment_method');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function processStripePayment(CheckoutRequest $request, array $data): RedirectResponse
    {
        try {
            // البحث عن بوابة Stripe المناسبة
            $gateway = $this->paymentGatewayService->selectGatewayByName(
                'stripe',
                $data['currency'], 
                'US' // يمكن تحسين هذا بناءً على بيانات المستخدم
            );
            
            if (!$gateway) {
                return redirect($data['failure_url'] . '?error=stripe_not_available');
            }
            
            // Create preliminary payment record for logging
            $payment = Payment::create([
                'generated_link_id' => $data['link_id'],
                'payment_gateway' => 'stripe',
                'gateway_payment_id' => null, // Will be updated after checkout creation
                'amount' => $data['price'],
                'currency' => $data['currency'],
                'status' => 'pending',
                'customer_email' => $request->getValidatedEmail(),
                'customer_phone' => $request->getValidatedPhone(),
                'gateway_response' => []
            ]);

            // Schedule background verification job (with 2 minutes delay to allow payment to process)
            ProcessPendingPayment::dispatch($payment)->delay(now()->addMinutes(2));

            // اختيار أفضل حساب للبوابة مع تسجيل السلوك وتوفر الباقة
            $stripeAccount = $this->paymentGatewayService->selectBestAccount($gateway, false, $payment->id, $data['plan_id']);
            
            if (!$stripeAccount) {
                $payment->delete(); // Clean up
                return redirect($data['failure_url'] . '?error=no_stripe_account_available');
            }
            
            // Update payment with selected account
            $payment->update(['payment_account_id' => $stripeAccount->id]);
            
            $credentials = $stripeAccount->credentials;
            
            if (!$credentials || !isset($credentials['secret_key'])) {
                $payment->delete(); // Clean up
                return redirect($data['failure_url'] . '?error=stripe_credentials_missing');
            }
            
            // Use the subscription service to create checkout
            $checkoutUrl = $this->stripeSubscriptionService->createSubscriptionCheckout(
                $data, 
                $request, 
                $stripeAccount
            );
            
            // Get the session ID from session storage
            $sessionId = session('stripe_session_id');

            // Update payment record with checkout details
            $payment->update([
                'gateway_payment_id' => $sessionId,
                'gateway_session_id' => $sessionId,
                'gateway_response' => [
                    'checkout_url' => $checkoutUrl,
                    'metadata' => [
                        'is_promoted_method' => $data['is_promoted_method'] ?? false,
                        'selected_payment_method' => $data['selected_payment_method'] ?? 'stripe'
                    ]
                ]
            ]);
            
            // تسجيل محاولة الدفع في الحساب (سيتم تحديث النتيجة في webhook)
            // $stripeAccount->incrementTotalTransactions(); // Will be updated in webhook handler

            return redirect($checkoutUrl);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            // خطأ في Stripe API - جرب حساب آخر
            \Log::warning('Stripe API error', [
                'error' => $e->getMessage(),
                'type' => $e->getStripeCode(),
                'account_id' => $stripeAccount->id ?? null
            ]);
            
            // محاولة الفشل وتسجيلها في الحساب
            if (isset($stripeAccount)) {
                $stripeAccount->incrementFailedTransaction();
            }
            
            // محاولة البحث عن حساب آخر أو بوابة أخرى
            return $this->tryFallbackPayment($data, $e->getMessage());
            
        } catch (\Exception $e) {
            // خطأ عام
            \Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            
            if (isset($stripeAccount)) {
                $stripeAccount->incrementFailedTransaction();
            }
            
            return redirect($data['failure_url'] . '?error=processing_failed&message=' . urlencode($e->getMessage()));
        }
    }

    private function processPayPalPayment(CheckoutRequest $request, array $data): RedirectResponse
    {
        try {
            // البحث عن بوابة PayPal المناسبة
            $gateway = $this->paymentGatewayService->selectGatewayByName(
                'paypal',
                $data['currency'], 
                'US' // يمكن تحسين هذا بناءً على بيانات المستخدم
            );
            
            if (!$gateway) {
                return redirect($data['failure_url'] . '?error=paypal_not_available');
            }
            
            // Prepare metadata without Gold Panel device data (will be selected after payment)
            $metadata = [
                'is_promoted_method' => $data['is_promoted_method'] ?? false,
                'selected_payment_method' => $data['selected_payment_method'] ?? 'paypal'
            ];

            // Create preliminary payment record for logging
            $payment = Payment::create([
                'generated_link_id' => $data['link_id'],
                'payment_gateway' => 'paypal',
                'gateway_payment_id' => null, // Will be updated after order creation
                'amount' => $data['price'],
                'currency' => $data['currency'],
                'status' => 'pending',
                'customer_email' => $request->getValidatedEmail(),
                'customer_phone' => $request->getValidatedPhone(),
                'gateway_response' => [
                    'metadata' => $metadata
                ]
            ]);

            // Schedule background verification job (with 2 minutes delay to allow payment to process)
            ProcessPendingPayment::dispatch($payment)->delay(now()->addMinutes(2));

            // اختيار أفضل حساب للبوابة مع تسجيل السلوك
            $paypalAccount = $this->paymentGatewayService->selectBestAccount($gateway, false, $payment->id);
            
            if (!$paypalAccount) {
                $payment->delete(); // Clean up
                return redirect($data['failure_url'] . '?error=no_paypal_account_available');
            }
            
            // Update payment with selected account
            $payment->update(['payment_account_id' => $paypalAccount->id]);
            
            $credentials = $paypalAccount->credentials;
            
            if (!$credentials || !isset($credentials['client_id']) || !isset($credentials['client_secret'])) {
                $payment->delete(); // Clean up
                return redirect($data['failure_url'] . '?error=paypal_credentials_missing');
            }

            \Log::info('PayPal credentials check', [
                'client_id_length' => strlen($credentials['client_id']),
                'client_secret_length' => strlen($credentials['client_secret']),
                'is_sandbox' => $paypalAccount->is_sandbox
            ]);
            
            // Get PayPal access token
            $accessToken = $this->getPayPalAccessToken($credentials, $paypalAccount->is_sandbox);
            
            if (!$accessToken) {
                $payment->delete(); // Clean up
                return redirect($data['failure_url'] . '?error=paypal_auth_failed');
            }
            
            // PayPal API base URL
            $baseUrl = $paypalAccount->is_sandbox 
                ? 'https://api-m.sandbox.paypal.com'
                : 'https://api-m.paypal.com';
            
            // إنشاء طلب الدفع
            $orderData = $this->buildPayPalOrder($data, $request);
            
            // إنشاء الطلب في PayPal عبر API مباشرة
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'Prefer' => 'return=representation'
            ])->post($baseUrl . '/v2/checkout/orders', $orderData);
            
            if (!$response->successful()) {
                \Log::error('PayPal order creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                $payment->delete(); // Clean up
                return redirect($data['failure_url'] . '?error=paypal_order_creation_failed');
            }
            
            $responseData = $response->json();
            
            // Update payment record with PayPal order details
            $payment->update([
                'gateway_payment_id' => $responseData['id'],
                'gateway_session_id' => $responseData['id'],
                'gateway_response' => $responseData
            ]);
            
            // البحث عن رابط الموافقة
            $approvalLink = '';
            foreach ($responseData['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approvalLink = $link['href'];
                    break;
                }
            }
            
            if (empty($approvalLink)) {
                return redirect($data['failure_url'] . '?error=paypal_approval_link_missing');
            }
            
            \Log::info('PayPal order created', [
                'order_id' => $responseData['id'],
                'payment_id' => $payment->id,
                'approval_link' => $approvalLink
            ]);
            
            return redirect($approvalLink);
            
        } catch (\Exception $e) {
            \Log::error('PayPal payment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            
            // في حالة فشل PayPal، جرب fallback
            return $this->tryFallbackPayment($data, $e->getMessage());
        }
    }
    
    private function buildPayPalOrder(array $data, CheckoutRequest $request): array
    {
        return [
            'intent' => 'CAPTURE',
            'application_context' => [
                'return_url' => route('paypal.return'),
                'cancel_url' => route('paypal.cancel'),
                'brand_name' => $data['website_name'],
                'user_action' => 'PAY_NOW'
            ],
            'purchase_units' => [
                [
                    'reference_id' => 'payment_' . $data['link_id'],
                    'description' => $data['plan_name'] . ' - ' . $data['plan_description'],
                    'amount' => [
                        'currency_code' => strtoupper($data['currency']),
                        'value' => number_format($data['price'], 2, '.', '')
                    ],
                    'payee' => [
                        'email_address' => $request->getValidatedEmail()
                    ]
                ]
            ]
        ];
    }
    
    private function tryFallbackPayment(array $data, string $originalError): RedirectResponse
    {
        \Log::info('Trying fallback payment methods', ['original_error' => $originalError]);
        
        // البحث عن بوابات أخرى متاحة
        $availableGateways = $this->paymentGatewayService->getAvailableGateways(
            $data['currency'],
            'US'
        );
        
        foreach ($availableGateways as $gateway) {
            // تجاهل Stripe لأنها فشلت بالفعل
            if ($gateway->name === 'stripe') {
                continue;
            }
            
            \Log::info('Trying fallback gateway: ' . $gateway->name);
            
            if ($gateway->name === 'paypal') {
                // إعادة توجيه إلى معالج PayPal
                return redirect()->route('process-payment', [
                    'token' => request()->input('token'),
                    'payment_method' => 'paypal',
                    'email' => request()->input('email'),
                    'phone' => request()->input('phone')
                ]);
            }
        }
        
        // لا توجد بوابات بديلة متاحة
        \Log::error('No fallback payment gateways available', [
            'currency' => $data['currency'],
            'original_error' => $originalError
        ]);
        
        return redirect($data['failure_url'] . '?error=no_payment_methods_available&original_error=' . urlencode($originalError));
    }

    public function success(Request $request): RedirectResponse
    {
        // Log all parameters for debugging
        \Log::info('Payment success callback parameters', $request->all());
        
        $sessionId = $request->query('session_id'); // Stripe
        $token = $request->query('token'); // PayPal
        $PayerID = $request->query('PayerID'); // PayPal
        
        // Handle PayPal return
        if ($token && $PayerID) {
            \Log::info('PayPal success callback', [
                'token' => $token,
                'PayerID' => $PayerID
            ]);
            
            // Find payment by PayPal token (which is the order ID)
            $payment = Payment::where('gateway_session_id', $token)->first();
            
            if (!$payment) {
                \Log::error('PayPal payment not found', ['token' => $token]);
                return redirect('/')->with('error', 'Payment session not found');
            }
            
            // Redirect to verification page
            return redirect()->route('payment.verify', [
                'payment' => $payment->id, 
                'session_id' => $token
            ]);
        }
        
        // Handle Stripe return
        if ($sessionId) {
            // Find the payment by session ID
            $payment = Payment::where('gateway_session_id', $sessionId)->first();
            
            if (!$payment) {
                return redirect('/')->with('error', 'Payment session not found');
            }

            // Redirect to verification page instead of processing directly
            return redirect()->route('payment.verify', ['payment' => $payment->id, 'session_id' => $sessionId]);
        }
        
        \Log::warning('Payment success callback with no valid parameters', $request->all());
        return redirect('/')->with('error', 'Invalid payment callback');
    }

    public function verify(Request $request, Payment $payment)
    {
        try {
            // Load payment with related data
            $payment->load(['generatedLink.website', 'generatedLink.plan']);
            
            // Set locale based on website language
            $language = 'en'; // default
            if ($payment->generatedLink && $payment->generatedLink->website && $payment->generatedLink->website->language) {
                $language = $payment->generatedLink->website->language;
                app()->setLocale($language);
            }
            
            // Show the payment verification page
            return view('payment.verify', [
                'payment' => $payment,
                'language' => $language,
                'isRTL' => in_array($language, ['ar', 'he', 'fa', 'ur'])
            ]);
        } catch (\Exception $e) {
            \Log::error('Payment verification page error: ' . $e->getMessage(), [
                'payment_id' => $payment->id ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->view('errors.500', [
                'message' => 'حدث خطأ في تحميل صفحة التحقق: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyBySession(Request $request, $sessionId)
    {
        try {
            // Find payment by session ID with related data
            $payment = Payment::with(['generatedLink.website', 'generatedLink.plan'])
                              ->where('gateway_session_id', $sessionId)->first();
            
            if (!$payment) {
                return response()->view('errors.404', [
                    'message' => 'لم يتم العثور على الدفعة المرتبطة بهذه الجلسة'
                ], 404);
            }

            // Set locale based on website language
            $language = 'en'; // default
            if ($payment->generatedLink && $payment->generatedLink->website && $payment->generatedLink->website->language) {
                $language = $payment->generatedLink->website->language;
                app()->setLocale($language);
            }

            // Redirect to the verification page with the payment ID
            return redirect()->route('payment.verify', [
                'payment' => $payment->id,
                'session_id' => $sessionId
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Payment session verification error: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->view('errors.500', [
                'message' => 'حدث خطأ في العثور على الدفعة: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request): RedirectResponse
    {
        // This would be called from payment gateway on cancellation
        return redirect('/')->with('message', 'Payment cancelled');
    }
    
    public function paypalReturn(Request $request): RedirectResponse
    {
        // Log all PayPal return parameters for debugging
        \Log::info('PayPal return callback parameters', $request->all());
        
        $token = $request->query('token'); // PayPal Order ID
        $PayerID = $request->query('PayerID'); // PayPal Payer ID
        
        if (!$token || !$PayerID) {
            \Log::error('PayPal return missing required parameters', [
                'token' => $token,
                'PayerID' => $PayerID,
                'all_params' => $request->all()
            ]);
            return redirect('/')->with('error', 'Invalid PayPal return parameters');
        }
        
        // Find payment by PayPal token (which is the order ID)
        $payment = Payment::where('gateway_session_id', $token)->first();
        
        if (!$payment) {
            \Log::error('PayPal payment not found for return', [
                'token' => $token,
                'PayerID' => $PayerID
            ]);
            return redirect('/')->with('error', 'Payment session not found');
        }
        
        \Log::info('PayPal return successful', [
            'payment_id' => $payment->id,
            'token' => $token,
            'PayerID' => $PayerID
        ]);
        
        // Update payment with PayPal payer info
        $payment->update([
            'gateway_response' => array_merge(
                $payment->gateway_response ?? [],
                [
                    'payer_id' => $PayerID,
                    'return_timestamp' => now()->toISOString()
                ]
            )
        ]);
        
        // Redirect to verification page
        return redirect()->route('payment.verify', [
            'payment' => $payment->id, 
            'session_id' => $token
        ]);
    }
    
    public function paypalCancel(Request $request): Response
    {
        // Log PayPal cancellation parameters
        \Log::info('PayPal cancellation callback parameters', $request->all());
        
        $token = $request->query('token'); // PayPal Order ID
        
        if ($token) {
            // Find payment by PayPal token and mark as cancelled
            $payment = Payment::with(['generatedLink.website'])->where('gateway_session_id', $token)->first();
            
            if ($payment && $payment->status === 'pending') {
                $payment->update([
                    'status' => 'cancelled',
                    'gateway_response' => array_merge(
                        $payment->gateway_response ?? [],
                        [
                            'cancelled_at' => now()->toISOString(),
                            'cancellation_reason' => 'user_cancelled_paypal'
                        ]
                    )
                ]);
                
                \Log::info('PayPal payment marked as cancelled', [
                    'payment_id' => $payment->id,
                    'token' => $token
                ]);
                
                // Set locale based on website language
                $language = 'en'; // default
                if ($payment->generatedLink && $payment->generatedLink->website && $payment->generatedLink->website->language) {
                    $language = $payment->generatedLink->website->language;
                    app()->setLocale($language);
                }
                
                // Show cancellation page with payment details
                return response()->view('payment-cancelled', [
                    'token' => $token,
                    'payment' => $payment,
                    'retry_url' => $this->getRetryUrlForPayment($payment),
                    'language' => $language,
                    'isRTL' => in_array($language, ['ar', 'he', 'fa', 'ur'])
                ]);
            }
        }
        
        // Default cancellation page
        return response()->view('payment-cancelled', [
            'token' => $token,
            'language' => 'en',
            'isRTL' => false
        ]);
    }
    
    /**
     * Get retry URL for a cancelled payment
     */
    private function getRetryUrlForPayment(Payment $payment): ?string
    {
        if ($payment->generatedLink) {
            // Generate a new token for the same payment link
            $service = app(PaymentLinkService::class);
            try {
                $newLink = $service->generatePaymentLink(
                    $payment->generatedLink->website_id,
                    $payment->generatedLink->plan_id,
                    $payment->generatedLink->success_url,
                    $payment->generatedLink->failure_url,
                    60 // 1 hour expiry
                );
                return $newLink['payment_link'];
            } catch (\Exception $e) {
                \Log::error('Failed to generate retry URL: ' . $e->getMessage());
            }
        }
        
        return null;
    }

    // Webhook handlers
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook.secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);

            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;
                
                // Update payment status
                $payment = Payment::where('gateway_session_id', $session->id)->first();
                if ($payment && $payment->status === 'pending') {
                    
                    // Check if it's a subscription or one-time payment
                    $isSubscription = $session->mode === 'subscription';
                    $subscriptionId = $isSubscription ? $session->subscription : null;
                    
                    $payment->update([
                        'status' => 'completed',
                        'paid_at' => now(),
                        'gateway_response' => array_merge(
                            $payment->gateway_response ?? [],
                            $session->toArray()
                        )
                    ]);
                    
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
                    
                    // Check if this payment used promoted method - check multiple sources
                    $isPromoted = $payment->gateway_response['metadata']['is_promoted_method'] ?? 
                                 ($session->metadata['is_promoted_method'] ?? false);
                    
                    // Create subscription now that payment is confirmed
                    $subscriptionData = [
                        'subscription_id' => $subscriptionId ?: ('sub_' . \Str::random(24)),
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
                            'features' => $payment->generatedLink->plan->features,
                            'has_bonus_month' => $isPromoted // إضافة معلومة حول المكافأة
                        ]
                    ];
                    
                    if ($isSubscription && $subscriptionId) {
                        // For real Stripe subscriptions, set gateway subscription ID
                        $subscriptionData['gateway_subscription_id'] = $subscriptionId;
                        $subscriptionData['expires_at'] = null; // Recurring subscription
                        
                        // Get billing details from Stripe subscription
                        try {
                            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                            $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);
                            $subscriptionData['next_billing_date'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                        } catch (\Exception $e) {
                            \Log::warning('Could not fetch Stripe subscription details', ['error' => $e->getMessage()]);
                        }
                    } else {
                        // One-time payment, set expiry based on billing interval with promotion bonus
                        $subscriptionData['expires_at'] = $payment->generatedLink->plan->calculateExpiryDateWithBonus(now(), $isPromoted);
                    }
                    
                    $subscription = Subscription::create($subscriptionData);
                    
                    // Update payment account statistics
                    if ($payment->payment_account_id) {
                        $account = \App\Models\PaymentAccount::find($payment->payment_account_id);
                        if ($account) {
                            $account->incrementSuccessfulTransaction($payment->amount);
                        }
                    }
                    
                    // Mark link as used if single use
                    if ($payment->generatedLink->single_use) {
                        $this->paymentLinkService->markLinkAsUsed($payment->generated_link_id);
                    }
                    
                    \Log::info('Payment completed via webhook', [
                        'payment_id' => $payment->id,
                        'subscription_id' => $subscription->subscription_id,
                        'amount' => $payment->amount
                    ]);
                }
            }

            return response('OK', 200);

        } catch (\Exception $e) {
            return response('Webhook Error: ' . $e->getMessage(), 400);
        }
    }

    public function paypalWebhook(Request $request)
    {
        try {
            $payload = $request->all();
            $headers = $request->headers->all();
            
            \Log::info('PayPal webhook received', [
                'event_type' => $payload['event_type'] ?? 'unknown',
                'resource_id' => $payload['resource']['id'] ?? 'unknown'
            ]);
            
            // التحقق من event type
            if (!isset($payload['event_type'])) {
                return response('Invalid payload', 400);
            }
            
            switch ($payload['event_type']) {
                case 'CHECKOUT.ORDER.APPROVED':
                    $this->handlePayPalOrderApproved($payload);
                    break;
                    
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $this->handlePayPalPaymentCompleted($payload);
                    break;
                    
                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.DECLINED':
                    $this->handlePayPalPaymentFailed($payload);
                    break;
                    
                default:
                    \Log::info('Unhandled PayPal webhook event', ['event_type' => $payload['event_type']]);
            }
            
            return response('OK', 200);
            
        } catch (\Exception $e) {
            \Log::error('PayPal webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response('Webhook Error: ' . $e->getMessage(), 400);
        }
    }
    
    private function handlePayPalOrderApproved(array $payload): void
    {
        if (!isset($payload['resource']['id'])) {
            return;
        }
        
        $orderId = $payload['resource']['id'];
        $payment = Payment::where('gateway_session_id', $orderId)->first();
        
        if ($payment && $payment->status === 'pending') {
            $payment->update([
                'gateway_response' => array_merge(
                    $payment->gateway_response ?? [],
                    ['approval_event' => $payload]
                )
            ]);
            
            \Log::info('PayPal order approved', ['order_id' => $orderId, 'payment_id' => $payment->id]);
        }
    }
    
    private function handlePayPalPaymentCompleted(array $payload): void
    {
        if (!isset($payload['resource']['supplementary_data']['related_ids']['order_id'])) {
            return;
        }
        
        $orderId = $payload['resource']['supplementary_data']['related_ids']['order_id'];
        $payment = Payment::where('gateway_session_id', $orderId)->first();
        
        if ($payment && $payment->status === 'pending') {
            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'gateway_response' => array_merge(
                    $payment->gateway_response ?? [],
                    ['completion_event' => $payload]
                )
            ]);
            
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
            
            // إنشاء subscription مع المكافأة للطرق المُروجة
            $isPromoted = $payment->gateway_response['metadata']['is_promoted_method'] ?? false;
            $subscription = Subscription::create([
                'subscription_id' => 'sub_' . \Str::random(24),
                'payment_id' => $payment->id,
                'website_id' => $payment->generatedLink->website_id,
                'plan_id' => $payment->generatedLink->plan_id,
                'customer_email' => $payment->customer_email,
                'customer_phone' => $payment->customer_phone,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => $payment->generatedLink->plan->calculateExpiryDateWithBonus(now(), $isPromoted),
                'plan_data' => [
                    'name' => $payment->generatedLink->plan->name,
                    'price' => $payment->amount,
                    'currency' => $payment->currency,
                    'features' => $payment->generatedLink->plan->features,
                    'has_bonus_month' => $isPromoted // إضافة معلومة حول المكافأة
                ]
            ]);
            
            // تحديث إحصائيات الحساب
            if ($payment->payment_account_id) {
                $account = \App\Models\PaymentAccount::find($payment->payment_account_id);
                if ($account) {
                    $account->incrementSuccessfulTransaction($payment->amount);
                }
            }
            
            // وضع علامة على الرابط كمستخدم إذا كان للاستخدام الواحد
            if ($payment->generatedLink->single_use) {
                $this->paymentLinkService->markLinkAsUsed($payment->generated_link_id);
            }
            
            \Log::info('PayPal payment completed', [
                'order_id' => $orderId,
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->subscription_id
            ]);
        }
    }
    
    private function handlePayPalPaymentFailed(array $payload): void
    {
        if (!isset($payload['resource']['supplementary_data']['related_ids']['order_id'])) {
            return;
        }
        
        $orderId = $payload['resource']['supplementary_data']['related_ids']['order_id'];
        $payment = Payment::where('gateway_session_id', $orderId)->first();
        
        if ($payment && $payment->status === 'pending') {
            $payment->update([
                'status' => 'failed',
                'gateway_response' => array_merge(
                    $payment->gateway_response ?? [],
                    ['failure_event' => $payload]
                )
            ]);
            
            // تحديث إحصائيات فشل الحساب
            if ($payment->payment_account_id) {
                $account = \App\Models\PaymentAccount::find($payment->payment_account_id);
                if ($account) {
                    $account->incrementFailedTransaction();
                }
            }
            
            \Log::warning('PayPal payment failed', [
                'order_id' => $orderId,
                'payment_id' => $payment->id,
                'reason' => $payload['resource']['reason_code'] ?? 'unknown'
            ]);
        }
    }
    
    /**
     * Get PayPal access token for API authentication
     */
    private function getPayPalAccessToken(array $credentials, bool $isSandbox): ?string
    {
        $baseUrl = $isSandbox 
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
            
        try {
            $response = Http::asForm()
                ->withBasicAuth($credentials['client_id'], $credentials['client_secret'])
                ->post($baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials'
                ]);
                
            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }
            
            \Log::error('PayPal access token request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('PayPal access token request exception', [
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
}