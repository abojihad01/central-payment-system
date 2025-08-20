<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Jobs\ProcessPendingPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class RecoverLostPayments extends Command
{
    protected $signature = 'payments:recover-lost 
                           {--limit=50 : Maximum number of payments to check}
                           {--min-age=10 : Minimum age in minutes for payments to be checked}
                           {--max-age=2880 : Maximum age in minutes for payments to be checked (48 hours)}
                           {--dry-run : Show what would be recovered without making changes}';

    protected $description = 'Recover payments that were successful in gateway but marked as pending in system';

    public function handle()
    {
        $limit = $this->option('limit');
        $minAge = $this->option('min-age');
        $maxAge = $this->option('max-age');
        $isDryRun = $this->option('dry-run');

        $this->info("🔍 Searching for lost payments...");
        $this->info("Parameters: limit={$limit}, min-age={$minAge}min, max-age={$maxAge}min, dry-run=" . ($isDryRun ? 'yes' : 'no'));

        // Find pending payments that might be completed in gateway
        $suspiciousPayments = Payment::where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($minAge))
            ->where('created_at', '>=', now()->subMinutes($maxAge))
            ->whereNotNull('gateway_session_id')
            ->orWhereNotNull('gateway_payment_id')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($suspiciousPayments->isEmpty()) {
            $this->info('✅ No suspicious pending payments found.');
            return Command::SUCCESS;
        }

        $this->info("🕵️ Found {$suspiciousPayments->count()} suspicious pending payments to check.");

        $recovered = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($suspiciousPayments as $payment) {
            $this->line("Checking payment ID: {$payment->id}");

            try {
                $result = $this->checkPaymentInGateway($payment);

                if ($result['status'] === 'completed') {
                    if ($isDryRun) {
                        $this->warn("  [DRY RUN] Would recover payment {$payment->id} - {$result['message']}");
                        $recovered++;
                    } else {
                        $this->recoverPayment($payment, $result);
                        $this->info("  ✅ Recovered payment {$payment->id} - {$result['message']}");
                        $recovered++;
                    }
                } elseif ($result['status'] === 'failed') {
                    if ($isDryRun) {
                        $this->warn("  [DRY RUN] Would mark payment {$payment->id} as failed - {$result['message']}");
                        $failed++;
                    } else {
                        $this->markPaymentFailed($payment, $result);
                        $this->error("  ❌ Marked payment {$payment->id} as failed - {$result['message']}");
                        $failed++;
                    }
                } else {
                    $this->comment("  ⏳ Payment {$payment->id} still pending - {$result['message']}");
                    $stillPending++;
                }

                // Small delay to avoid rate limiting
                usleep(200000); // 0.2 seconds

            } catch (\Exception $e) {
                $this->error("  ❌ Error checking payment {$payment->id}: " . $e->getMessage());
                Log::error('Error checking lost payment', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("📊 Recovery Summary:");
        $this->info("- Recovered: {$recovered} payments");
        $this->info("- Failed: {$failed} payments");
        $this->info("- Still Pending: {$stillPending} payments");
        $this->info("- Total Checked: " . ($recovered + $failed + $stillPending));

        if ($isDryRun) {
            $this->warn("This was a dry run. No changes were made to the database.");
            $this->info("Run without --dry-run to apply the changes.");
        }

        // Log the summary
        Log::info('Lost payments recovery completed', [
            'recovered' => $recovered,
            'failed' => $failed,
            'still_pending' => $stillPending,
            'dry_run' => $isDryRun,
            'parameters' => [
                'limit' => $limit,
                'min_age_minutes' => $minAge,
                'max_age_minutes' => $maxAge
            ]
        ]);

        return Command::SUCCESS;
    }

    protected function checkPaymentInGateway(Payment $payment): array
    {
        switch (strtolower($payment->payment_gateway)) {
            case 'stripe':
                return $this->checkStripePayment($payment);
            case 'paypal':
                return $this->checkPayPalPayment($payment);
            default:
                return [
                    'status' => 'unknown',
                    'message' => 'Unsupported gateway: ' . $payment->payment_gateway
                ];
        }
    }

    protected function checkStripePayment(Payment $payment): array
    {
        try {
            // Get payment account and Stripe client
            $paymentAccount = $payment->paymentAccount;
            if (!$paymentAccount || !isset($paymentAccount->credentials['secret_key'])) {
                return [
                    'status' => 'error',
                    'message' => 'Stripe credentials not found'
                ];
            }

            $stripe = new StripeClient($paymentAccount->credentials['secret_key']);

            // Check Stripe Checkout Session if available
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
                            'currency' => $session->currency,
                            'payment_intent' => $session->payment_intent,
                        ]
                    ];
                } elseif ($session->status === 'expired') {
                    return [
                        'status' => 'failed',
                        'message' => 'Checkout session expired',
                        'gateway_data' => [
                            'session_id' => $session->id,
                            'status' => $session->status,
                        ]
                    ];
                } else {
                    return [
                        'status' => 'pending',
                        'message' => "Session status: {$session->status}, payment status: {$session->payment_status}"
                    ];
                }
            }

            // Check Payment Intent if available
            if ($payment->gateway_payment_id && str_starts_with($payment->gateway_payment_id, 'pi_')) {
                $intent = $stripe->paymentIntents->retrieve($payment->gateway_payment_id);

                if ($intent->status === 'succeeded') {
                    return [
                        'status' => 'completed',
                        'message' => 'Payment intent succeeded',
                        'gateway_data' => [
                            'payment_intent_id' => $intent->id,
                            'status' => $intent->status,
                            'amount_received' => $intent->amount_received,
                            'currency' => $intent->currency,
                            'payment_method' => $intent->payment_method,
                        ]
                    ];
                } elseif (in_array($intent->status, ['canceled', 'payment_failed'])) {
                    return [
                        'status' => 'failed',
                        'message' => "Payment intent {$intent->status}",
                        'gateway_data' => [
                            'payment_intent_id' => $intent->id,
                            'status' => $intent->status,
                            'last_payment_error' => $intent->last_payment_error->message ?? null,
                        ]
                    ];
                } else {
                    return [
                        'status' => 'pending',
                        'message' => "Payment intent status: {$intent->status}"
                    ];
                }
            }

            return [
                'status' => 'unknown',
                'message' => 'No valid Stripe session or payment intent ID found'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Stripe API error: ' . $e->getMessage()
            ];
        }
    }

    protected function checkPayPalPayment(Payment $payment): array
    {
        // Implement PayPal payment checking logic
        return [
            'status' => 'unknown',
            'message' => 'PayPal payment checking not implemented yet'
        ];
    }

    protected function recoverPayment(Payment $payment, array $result): void
    {
        $payment->update([
            'status' => 'completed',
            'confirmed_at' => now(),
            'paid_at' => now(),
            'gateway_response' => array_merge(
                $payment->gateway_response ?? [],
                $result['gateway_data'] ?? [],
                ['recovered_at' => now(), 'recovery_method' => 'lost_payment_recovery']
            ),
            'notes' => trim(($payment->notes ?? '') . "\nPayment recovered via lost payment recovery process.")
        ]);

        // Handle subscription creation and notifications directly since payment is already completed
        $this->processRecoveredPayment($payment);

        Log::info('Payment recovered successfully', [
            'payment_id' => $payment->id,
            'gateway' => $payment->payment_gateway,
            'amount' => $payment->amount,
            'recovery_method' => 'lost_payment_recovery'
        ]);
    }

    protected function markPaymentFailed(Payment $payment, array $result): void
    {
        $payment->update([
            'status' => 'failed',
            'gateway_response' => array_merge(
                $payment->gateway_response ?? [],
                $result['gateway_data'] ?? [],
                ['failed_at' => now(), 'failure_method' => 'lost_payment_recovery']
            ),
            'notes' => trim(($payment->notes ?? '') . "\nPayment marked as failed via lost payment recovery process: " . $result['message'])
        ]);

        Log::info('Payment marked as failed via recovery', [
            'payment_id' => $payment->id,
            'gateway' => $payment->payment_gateway,
            'reason' => $result['message']
        ]);
    }

    /**
     * Process recovered payment to create subscription and invoice
     */
    protected function processRecoveredPayment(Payment $payment)
    {
        try {
            // Check if payment already has a subscription to avoid duplicates
            if ($payment->subscription) {
                Log::info('Payment already has subscription, skipping', ['payment_id' => $payment->id]);
                return;
            }

            // Handle renewal payments
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
                Log::warning('Renewal payment recovered but no existing subscription found', [
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
            $durationDays = 30;
            
            if ($payment->generatedLink) {
                $planId = $payment->generatedLink->plan_id;
                $websiteId = $payment->generatedLink->website_id;
                if ($payment->generatedLink->plan) {
                    $planName = $payment->generatedLink->plan->name;
                    $durationDays = $payment->generatedLink->plan->duration_days;
                }
            } elseif ($payment->plan_id) {
                // Try direct plan relationship
                $planId = $payment->plan_id;
                $plan = \App\Models\Plan::find($planId);
                if ($plan) {
                    $planName = $plan->name;
                    $durationDays = $plan->duration_days;
                    $websiteId = $plan->website_id;
                }
            }
            
            // Calculate dates
            $expiryDate = now()->addDays($durationDays);
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
                'starts_at' => now(),
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
                'invoice_number' => 'INV-LOST-REC-' . date('Y') . '-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
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
                        'description' => $planName . ' (Lost Payment Recovery)',
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

            Log::info('Successfully processed recovered payment with subscription and invoice', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing recovered payment', [
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
        
        if (isset($subscription->plan_data['duration_days'])) {
            $durationDays = $subscription->plan_data['duration_days'];
        } elseif ($payment->generatedLink && $payment->generatedLink->plan) {
            $durationDays = $payment->generatedLink->plan->duration_days;
        } elseif ($subscription->plan) {
            $durationDays = $subscription->plan->duration_days;
        } else {
            $durationDays = 30;
            Log::warning('Could not determine renewal duration, using 30 days default', [
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
        $planName = 'Plan';
        if (isset($subscription->plan_data['name'])) {
            $planName = $subscription->plan_data['name'];
        } elseif ($payment->generatedLink && $payment->generatedLink->plan) {
            $planName = $payment->generatedLink->plan->name;
        } elseif ($subscription->plan) {
            $planName = $subscription->plan->name;
        }

        // Create renewal invoice
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'INV-RENEWAL-LOST-' . date('Y') . '-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT),
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
                    'description' => 'Subscription renewal - ' . $planName . ' (Lost Payment Recovery)',
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency ?? 'USD'
                ]
            ]
        ]);

        // Update customer for renewal
        $this->updateCustomerForRenewal($subscription, $payment);

        Log::info('Successfully processed renewal payment recovery', [
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'new_expiry_date' => $newExpiryDate
        ]);
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
            'event_type' => 'payment_recovered_lost',
            'description' => 'Lost payment recovered and processed - Amount: ' . $payment->amount . ' ' . $payment->currency,
            'metadata' => [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->payment_gateway,
                'recovery_method' => 'lost_payment_recovery',
                'recovered_at' => now()
            ],
            'source' => 'lost_payment_recovery_command',
            'ip_address' => null,
            'user_agent' => null
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
            'event_type' => 'subscription_created_from_lost_recovery',
            'description' => 'New subscription created from lost payment recovery for plan: ' . $subscription->plan_data['name'],
            'metadata' => [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $subscription->plan_data['name'],
                'price' => $subscription->plan_data['price'],
                'duration_days' => $subscription->plan_data['duration_days'],
                'expires_at' => $subscription->expires_at->toDateTimeString(),
                'recovery_method' => 'lost_payment_recovery'
            ],
            'source' => 'lost_payment_recovery_command'
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
                'event_type' => 'subscription_renewed_from_lost_recovery',
                'description' => 'Subscription renewed from lost payment recovery for plan: ' . ($subscription->plan_data['name'] ?? 'Plan'),
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'gateway' => $payment->payment_gateway,
                    'new_expires_at' => $subscription->expires_at->toDateTimeString(),
                    'recovery_method' => 'lost_payment_recovery'
                ],
                'source' => 'lost_payment_recovery_command'
            ]);
        }
    }
}