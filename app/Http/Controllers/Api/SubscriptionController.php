<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Display a listing of subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with(['plan']);

        // Filter by customer email
        if ($request->filled('customer_email')) {
            $query->where('customer_email', $request->customer_email);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by plan
        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        $subscriptions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
            'meta' => [
                'total' => $subscriptions->total(),
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage()
            ]
        ]);
    }

    /**
     * Store a newly created subscription
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'customer_email' => 'required|email',
            'payment_method' => 'required|string',
            'customer_data' => 'array',
            'payment_method_data' => 'array'
        ]);

        try {
            $subscription = $this->subscriptionService->createSubscription($validated);

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => $subscription->load('plan')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified subscription
     */
    public function show(string $id): JsonResponse
    {
        $subscription = Subscription::with(['plan'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $subscription
        ]);
    }

    /**
     * Update the specified subscription
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'sometimes|in:active,cancelled,paused,suspended',
            'customer_data' => 'sometimes|array',
            'payment_method_data' => 'sometimes|array'
        ]);

        $subscription->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Subscription updated successfully',
            'data' => $subscription->fresh(['plan'])
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        
        $validated = $request->validate([
            'reason' => 'sometimes|string|max:255',
            'refund_amount' => 'sometimes|numeric|min:0',
            'effective_date' => 'sometimes|date'
        ]);

        // Update subscription status
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['reason'] ?? null
        ]);

        // Create refund payment if requested
        \Log::info('Processing refund request', [
            'refund_amount' => $validated['refund_amount'] ?? null,
            'subscription_id' => $subscription->id
        ]);
        if (isset($validated['refund_amount']) && $validated['refund_amount'] > 0) {
            // Get the original payment for this subscription - try multiple methods
            $originalPayment = $subscription->payment;
            
            if (!$originalPayment) {
                // Try finding by subscription_id
                $originalPayment = \App\Models\Payment::where('subscription_id', $subscription->id)
                    ->where('status', 'completed')
                    ->first();
            }
            
            if (!$originalPayment && $subscription->payment_id) {
                // Try finding by payment_id from subscription
                $originalPayment = \App\Models\Payment::find($subscription->payment_id);
            }
            
            if ($originalPayment) {
                \Log::info('Creating refund payment');
                $refundData = [
                    'subscription_id' => $subscription->id,
                    'generated_link_id' => $originalPayment->generated_link_id ?? null,
                    'payment_account_id' => $originalPayment->payment_account_id ?? null,
                    'payment_gateway' => $originalPayment->payment_gateway ?? 'system',
                    'gateway_payment_id' => 'refund_' . uniqid(),
                    'amount' => -$validated['refund_amount'], // Negative amount for refund
                    'currency' => $originalPayment->currency ?? 'USD',
                    'status' => 'completed',
                    'customer_email' => $subscription->customer_email,
                    'customer_name' => $originalPayment->customer_name ?? $subscription->customer_email,
                    'type' => 'refund',
                    'confirmed_at' => now(),
                    'paid_at' => now()
                ];
                \Log::info('Refund data', $refundData);
                
                try {
                    $refundPayment = \App\Models\Payment::create($refundData);
                    \Log::info('Refund payment created', ['refund_payment_id' => $refundPayment->id]);
                } catch (\Exception $e) {
                    \Log::error('Failed to create refund payment', ['error' => $e->getMessage()]);
                }
            } else {
                \Log::warning('No original payment found for refund');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully',
            'data' => $subscription->fresh()
        ]);
    }

    /**
     * Pause subscription
     */
    public function pause(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        
        $validated = $request->validate([
            'reason' => 'sometimes|string|max:255'
        ]);

        $result = $subscription->pause($validated['reason'] ?? null);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Subscription paused successfully',
                'data' => $subscription->fresh()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to pause subscription'
        ], 400);
    }

    /**
     * Resume subscription
     */
    public function resume(string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);

        $result = $subscription->resume();

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Subscription resumed successfully',
                'data' => $subscription->fresh()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to resume subscription'
        ], 400);
    }

    /**
     * Change subscription plan (upgrade/downgrade)
     */
    public function changePlan(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        
        $validated = $request->validate([
            'new_plan_id' => 'required|exists:plans,id',
            'prorate' => 'sometimes|boolean'
        ]);

        $oldPlan = $subscription->plan;
        $newPlan = \App\Models\Plan::find($validated['new_plan_id']);
        
        // Update subscription
        $subscription->update([
            'plan_id' => $validated['new_plan_id']
        ]);

        // Create prorated payment for upgrade if price difference exists
        if ($newPlan->price > $oldPlan->price) {
            // Get the original payment for this subscription - try multiple methods
            $originalPayment = $subscription->payment;
            
            if (!$originalPayment) {
                // Try finding by subscription_id
                $originalPayment = \App\Models\Payment::where('subscription_id', $subscription->id)
                    ->where('status', 'completed')
                    ->first();
            }
            
            if (!$originalPayment && $subscription->payment_id) {
                // Try finding by payment_id from subscription
                $originalPayment = \App\Models\Payment::find($subscription->payment_id);
            }
            
            if ($originalPayment) {
                \Log::info('Creating upgrade payment');
                try {
                    $upgradePayment = \App\Models\Payment::create([
                        'subscription_id' => $subscription->id,
                        'generated_link_id' => $originalPayment->generated_link_id ?? null,
                        'payment_account_id' => $originalPayment->payment_account_id ?? null,
                        'payment_gateway' => $originalPayment->payment_gateway ?? 'system',
                        'gateway_payment_id' => 'upgrade_' . uniqid(),
                        'amount' => $newPlan->price - $oldPlan->price,
                        'currency' => $newPlan->currency,
                        'status' => 'completed',
                        'customer_email' => $subscription->customer_email,
                        'customer_name' => $originalPayment->customer_name ?? $subscription->customer_email,
                        'type' => 'upgrade',
                        'confirmed_at' => now(),
                        'paid_at' => now()
                    ]);
                    \Log::info('Upgrade payment created', ['upgrade_payment_id' => $upgradePayment->id]);
                } catch (\Exception $e) {
                    \Log::error('Failed to create upgrade payment', ['error' => $e->getMessage()]);
                }
            } else {
                \Log::warning('No original payment found for upgrade');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan changed successfully',
            'data' => $subscription->fresh(['plan'])
        ]);
    }

    /**
     * Upgrade subscription plan
     */
    public function upgradePlan(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        
        $validated = $request->validate([
            'new_plan_id' => 'required|exists:plans,id'
        ]);

        $result = $subscription->upgradePlan($validated['new_plan_id']);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Subscription plan upgraded successfully',
                'data' => $subscription->fresh(['plan'])
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to upgrade subscription plan'
        ], 400);
    }

    /**
     * Get subscription analytics
     */
    public function analytics(): JsonResponse
    {
        $analytics = $this->subscriptionService->getAnalytics();

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get MRR (Monthly Recurring Revenue)
     */
    public function mrr(): JsonResponse
    {
        $mrr = $this->subscriptionService->calculateMRR();

        return response()->json([
            'success' => true,
            'data' => [
                'mrr' => $mrr,
                'currency' => 'USD',
                'period' => 'monthly'
            ]
        ]);
    }
}