<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PaymentAccount;
use App\Services\StripeSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;

class StripeProductController extends Controller
{
    public function __construct(private StripeSubscriptionService $stripeService) {}

    /**
     * Get all Stripe products and their sync status
     */
    public function index(Request $request): JsonResponse
    {
        $accountId = $request->query('account_id');
        $account = PaymentAccount::with('gateway')->whereHas('gateway', function($q) {
            $q->where('name', 'stripe');
        })
            ->when($accountId, fn($q) => $q->where('id', $accountId))
            ->where('is_active', true)
            ->first();
            
        if (!$account) {
            return response()->json(['error' => 'No active Stripe account found'], 404);
        }

        try {
            $credentials = $account->credentials;
            if (!$credentials || !isset($credentials['secret_key'])) {
                return response()->json(['error' => 'Invalid Stripe credentials'], 400);
            }
            
            Stripe::setApiKey($credentials['secret_key']);
            
            // Get Stripe products
            $stripeProducts = Product::all(['limit' => 100]);
            $products = [];
            
            foreach ($stripeProducts->data as $product) {
                $prices = Price::all(['product' => $product->id]);
                
                foreach ($prices->data as $price) {
                    $localPlan = Plan::where('metadata->stripe_product_id', $product->id)
                        ->orWhere('metadata->stripe_price_id', $price->id)
                        ->first();
                    
                    $products[] = [
                        'stripe_product_id' => $product->id,
                        'stripe_price_id' => $price->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'price' => $price->unit_amount / 100,
                        'currency' => strtoupper($price->currency),
                        'recurring' => isset($price->recurring),
                        'interval' => $price->recurring->interval ?? null,
                        'interval_count' => $price->recurring->interval_count ?? null,
                        'local_plan_id' => $localPlan?->id,
                        'is_synced' => $localPlan !== null,
                        'metadata' => $product->metadata->toArray(),
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                ],
                'products' => $products
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch Stripe products: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create a new product in Stripe and sync to local plan
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'recurring' => 'boolean',
            'interval' => 'nullable|in:day,week,month,year',
            'interval_count' => 'nullable|integer|min:1|max:999',
            'account_id' => 'required|exists:payment_accounts,id',
        ]);

        $account = PaymentAccount::with('gateway')->findOrFail($request->account_id);
        
        if (!$account->gateway || $account->gateway->name !== 'stripe' || !$account->is_active) {
            return response()->json(['error' => 'Invalid Stripe account'], 400);
        }

        try {
            $credentials = $account->credentials;
            if (!$credentials || !isset($credentials['secret_key'])) {
                return response()->json(['error' => 'Invalid Stripe credentials'], 400);
            }
            
            Stripe::setApiKey($credentials['secret_key']);
            
            // Create Stripe product
            $product = Product::create([
                'name' => $request->name,
                'description' => $request->description,
                'metadata' => $request->metadata ?? [],
            ]);
            
            // Create price
            $priceData = [
                'unit_amount' => (int)($request->price * 100),
                'currency' => strtolower($request->currency),
                'product' => $product->id,
            ];
            
            if ($request->recurring) {
                $priceData['recurring'] = [
                    'interval' => $request->interval ?? 'month',
                    'interval_count' => $request->interval_count ?? 1,
                ];
            }
            
            $price = Price::create($priceData);
            
            // Create local plan
            $durationDays = null;
            if ($request->recurring) {
                $interval = $request->interval ?? 'month';
                $count = $request->interval_count ?? 1;
                
                $durationDays = match($interval) {
                    'day' => $count,
                    'week' => $count * 7,
                    'month' => $count * 30,
                    'year' => $count * 365,
                };
            }
            
            $plan = Plan::create([
                'name' => $request->name,
                'description' => $request->description ?? "Created via API: {$request->name}",
                'price' => $request->price,
                'currency' => strtoupper($request->currency),
                'duration_days' => $durationDays,
                'features' => $request->features ?? '',
                'metadata' => [
                    'stripe_product_id' => $product->id,
                    'stripe_price_id' => $price->id,
                    'stripe_account_id' => $account->id,
                    'recurring' => $request->recurring ?? false,
                    'created_via_api' => true,
                    'stripe_interval' => $price->recurring->interval ?? null,
                    'stripe_interval_count' => $price->recurring->interval_count ?? null,
                ]
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'stripe_product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                ],
                'stripe_price' => [
                    'id' => $price->id,
                    'amount' => $price->unit_amount / 100,
                ],
                'local_plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create product: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Import products from Stripe to local plans
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|exists:payment_accounts,id',
            'create_new_plans' => 'boolean',
            'update_existing' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'string',
        ]);

        $account = PaymentAccount::with('gateway')->findOrFail($request->account_id);
        
        if (!$account->gateway || $account->gateway->name !== 'stripe' || !$account->is_active) {
            return response()->json(['error' => 'Invalid Stripe account'], 400);
        }

        try {
            $credentials = $account->credentials;
            Stripe::setApiKey($credentials['secret_key']);
            
            $productIds = $request->product_ids;
            $products = $productIds 
                ? collect($productIds)->map(fn($id) => Product::retrieve($id))
                : Product::all(['limit' => 100])->data;
            
            $created = 0;
            $updated = 0;
            $synced = 0;
            $errors = [];
            
            foreach ($products as $product) {
                try {
                    $prices = Price::all(['product' => $product->id]);
                    
                    foreach ($prices->data as $price) {
                        $existingPlan = Plan::where('metadata->stripe_product_id', $product->id)
                            ->orWhere('metadata->stripe_price_id', $price->id)
                            ->first();
                        
                        if ($existingPlan) {
                            if ($request->update_existing) {
                                $this->updatePlanFromStripe($existingPlan, $product, $price, $account);
                                $updated++;
                            } else {
                                $metadata = $existingPlan->metadata ?? [];
                                $metadata['stripe_product_id'] = $product->id;
                                $metadata['stripe_price_id'] = $price->id;
                                $existingPlan->update(['metadata' => $metadata]);
                                $synced++;
                            }
                        } elseif ($request->create_new_plans) {
                            $this->createPlanFromStripe($product, $price, $account);
                            $created++;
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Product {$product->id}: " . $e->getMessage();
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Import completed',
                'results' => [
                    'created' => $created,
                    'updated' => $updated,
                    'synced' => $synced,
                    'errors' => $errors,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    private function createPlanFromStripe($product, $price, $account)
    {
        $currency = strtoupper($price->currency);
        $amount = $price->unit_amount / 100;
        $isRecurring = isset($price->recurring);
        
        $durationDays = null;
        if ($isRecurring) {
            $interval = $price->recurring->interval;
            $intervalCount = $price->recurring->interval_count ?? 1;
            
            $durationDays = match($interval) {
                'day' => $intervalCount,
                'week' => $intervalCount * 7,
                'month' => $intervalCount * 30,
                'year' => $intervalCount * 365,
            };
        }
        
        return Plan::create([
            'name' => $product->name,
            'description' => $product->description ?? "Imported from Stripe: {$product->name}",
            'price' => $amount,
            'currency' => $currency,
            'duration_days' => $durationDays,
            'features' => $product->metadata['features'] ?? '',
            'metadata' => [
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
                'stripe_account_id' => $account->id,
                'recurring' => $isRecurring,
                'imported_from_stripe' => true,
            ]
        ]);
    }

    private function updatePlanFromStripe($plan, $product, $price, $account)
    {
        $currency = strtoupper($price->currency);
        $amount = $price->unit_amount / 100;
        $isRecurring = isset($price->recurring);
        
        $durationDays = null;
        if ($isRecurring) {
            $interval = $price->recurring->interval;
            $intervalCount = $price->recurring->interval_count ?? 1;
            
            $durationDays = match($interval) {
                'day' => $intervalCount,
                'week' => $intervalCount * 7,
                'month' => $intervalCount * 30,
                'year' => $intervalCount * 365,
            };
        }
        
        $metadata = $plan->metadata ?? [];
        $metadata['stripe_product_id'] = $product->id;
        $metadata['stripe_price_id'] = $price->id;
        $metadata['updated_from_stripe'] = now()->toISOString();
        
        $plan->update([
            'name' => $product->name,
            'description' => $product->description ?? $plan->description,
            'price' => $amount,
            'currency' => $currency,
            'duration_days' => $durationDays,
            'metadata' => $metadata
        ]);
    }
}