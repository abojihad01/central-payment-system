<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Jobs\ProcessPendingPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    /**
     * Handle Stripe webhook events
     */
    public function stripe(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            // Get webhook secret from environment or payment account
            $webhookSecret = config('services.stripe.webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET');
            
            if (!$webhookSecret) {
                Log::error('Stripe webhook secret not configured');
                return response('Webhook secret not configured', 400);
            }

            // Verify webhook signature
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);

            Log::info('Stripe webhook received', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'object_id' => $event->data->object->id ?? null,
            ]);

            // Handle different event types
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;

                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionEvent($event->type, $event->data->object);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', ['type' => $event->type]);
                    break;
            }

            return response('Webhook handled successfully', 200);

        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
                'signature' => $sigHeader,
            ]);
            return response('Invalid signature', 400);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle successful checkout session completion
     */
    protected function handleCheckoutSessionCompleted($session)
    {
        Log::info('Processing checkout session completed', [
            'session_id' => $session->id,
            'payment_status' => $session->payment_status,
            'customer_email' => $session->customer_details->email ?? null,
        ]);

        // Find payment by session ID
        $payment = Payment::where('gateway_session_id', $session->id)
                         ->where('status', 'pending')
                         ->first();

        if (!$payment) {
            // Try to find by payment intent ID if available
            if (isset($session->payment_intent)) {
                $payment = Payment::where('gateway_payment_id', $session->payment_intent)
                                ->where('status', 'pending')
                                ->first();
            }
        }

        if (!$payment) {
            Log::warning('No pending payment found for checkout session', [
                'session_id' => $session->id,
                'payment_intent' => $session->payment_intent ?? null,
            ]);
            return;
        }

        if ($session->payment_status === 'paid') {
            // Mark payment as completed
            $payment->update([
                'status' => 'completed',
                'confirmed_at' => now(),
                'paid_at' => now(),
                'gateway_response' => [
                    'session_id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'amount_total' => $session->amount_total,
                    'currency' => $session->currency,
                    'customer_email' => $session->customer_details->email ?? null,
                    'payment_intent' => $session->payment_intent ?? null,
                    'webhook_processed_at' => now(),
                ],
                'notes' => 'Payment completed via Stripe webhook'
            ]);

            Log::info('Payment marked as completed via webhook', [
                'payment_id' => $payment->id,
                'session_id' => $session->id,
                'amount' => $session->amount_total / 100,
            ]);

            // Dispatch job to handle subscription creation and notifications
            ProcessPendingPayment::dispatch($payment);

        } else {
            Log::warning('Checkout session completed but payment not paid', [
                'session_id' => $session->id,
                'payment_status' => $session->payment_status,
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Handle successful payment intent
     */
    protected function handlePaymentIntentSucceeded($paymentIntent)
    {
        Log::info('Processing payment intent succeeded', [
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount_received,
        ]);

        // Find payment by payment intent ID
        $payment = Payment::where('gateway_payment_id', $paymentIntent->id)
                         ->where('status', 'pending')
                         ->first();

        if (!$payment) {
            Log::warning('No pending payment found for payment intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        // Mark payment as completed
        $payment->update([
            'status' => 'completed',
            'confirmed_at' => now(),
            'paid_at' => now(),
            'gateway_response' => [
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount_received' => $paymentIntent->amount_received,
                'currency' => $paymentIntent->currency,
                'payment_method' => $paymentIntent->payment_method,
                'webhook_processed_at' => now(),
            ],
            'notes' => 'Payment completed via Stripe webhook'
        ]);

        Log::info('Payment marked as completed via webhook', [
            'payment_id' => $payment->id,
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount_received / 100,
        ]);

        // Dispatch job to handle subscription creation and notifications
        ProcessPendingPayment::dispatch($payment);
    }

    /**
     * Handle failed payment intent
     */
    protected function handlePaymentIntentFailed($paymentIntent)
    {
        Log::info('Processing payment intent failed', [
            'payment_intent_id' => $paymentIntent->id,
            'last_payment_error' => $paymentIntent->last_payment_error->message ?? null,
        ]);

        // Find payment by payment intent ID
        $payment = Payment::where('gateway_payment_id', $paymentIntent->id)
                         ->where('status', 'pending')
                         ->first();

        if (!$payment) {
            Log::warning('No pending payment found for failed payment intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        // Mark payment as failed
        $payment->update([
            'status' => 'failed',
            'gateway_response' => [
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'error' => $paymentIntent->last_payment_error->message ?? 'Payment failed',
                'error_code' => $paymentIntent->last_payment_error->code ?? null,
                'webhook_processed_at' => now(),
            ],
            'notes' => 'Payment failed via Stripe webhook: ' . ($paymentIntent->last_payment_error->message ?? 'Unknown error')
        ]);

        Log::info('Payment marked as failed via webhook', [
            'payment_id' => $payment->id,
            'payment_intent_id' => $paymentIntent->id,
            'error' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
        ]);
    }

    /**
     * Handle invoice payment events
     */
    protected function handleInvoicePaymentSucceeded($invoice)
    {
        Log::info('Processing invoice payment succeeded', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription ?? null,
        ]);

        // Handle subscription renewals or one-time payments
        // This is useful for recurring billing scenarios
    }

    protected function handleInvoicePaymentFailed($invoice)
    {
        Log::info('Processing invoice payment failed', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription ?? null,
        ]);

        // Handle failed recurring payments
    }

    /**
     * Handle subscription events
     */
    protected function handleSubscriptionEvent($eventType, $subscription)
    {
        Log::info('Processing subscription event', [
            'event_type' => $eventType,
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
        ]);

        // Handle subscription lifecycle events
        // This can be used for managing recurring subscriptions
    }

    /**
     * Handle PayPal webhook events
     */
    public function paypal(Request $request)
    {
        $payload = $request->getContent();
        $headers = $request->headers->all();

        try {
            // Verify PayPal webhook signature
            $this->verifyPayPalWebhook($payload, $headers);

            $event = json_decode($payload, true);

            Log::info('PayPal webhook received', [
                'event_type' => $event['event_type'] ?? 'unknown',
                'resource_type' => $event['resource_type'] ?? 'unknown',
            ]);

            // Handle PayPal events
            switch ($event['event_type']) {
                case 'CHECKOUT.ORDER.APPROVED':
                    $this->handlePayPalOrderApproved($event['resource']);
                    break;

                case 'PAYMENT.CAPTURE.COMPLETED':
                    $this->handlePayPalCaptureCompleted($event['resource']);
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                    $this->handlePayPalCaptureDenied($event['resource']);
                    break;

                default:
                    Log::info('Unhandled PayPal webhook event', ['type' => $event['event_type']]);
                    break;
            }

            return response('Webhook handled successfully', 200);

        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return response('Webhook processing failed', 500);
        }
    }

    protected function verifyPayPalWebhook($payload, $headers)
    {
        // Implement PayPal webhook signature verification
        // This requires PayPal webhook verification logic
    }

    protected function handlePayPalOrderApproved($resource)
    {
        // Handle PayPal order approval
    }

    protected function handlePayPalCaptureCompleted($resource)
    {
        // Handle successful PayPal capture
    }

    protected function handlePayPalCaptureDenied($resource)
    {
        // Handle failed PayPal capture
    }
}