<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

    /**
     * Display a listing of customers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        // Search by email, name, or customer ID
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by risk level
        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        // Filter by high value customers
        if ($request->boolean('high_value')) {
            $query->where('lifetime_value', '>=', 1000);
        }

        // Filter by country
        if ($request->filled('country')) {
            $query->where('country_code', $request->country);
        }

        $customers = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $customers,
            'meta' => [
                'total' => $customers->total(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage()
            ]
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:customers',
            'phone' => 'nullable|string',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'acquisition_source' => 'nullable|string|max:255'
        ]);

        try {
            $customer = $this->customerService->createOrUpdateCustomer(
                $validated['email'],
                $validated['phone'] ?? null,
                array_filter($validated),
                $validated['acquisition_source'] ?? 'api'
            );

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified customer
     */
    public function show(string $id): JsonResponse
    {
        $customer = Customer::with(['events', 'communications'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        
        $validated = $request->validate([
            'phone' => 'sometimes|nullable|string',
            'first_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'country_code' => 'sometimes|nullable|string|size:2',
            'city' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'marketing_consent' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string',
            'tags' => 'sometimes|array'
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer->fresh()
        ]);
    }

    /**
     * Block customer
     */
    public function block(Request $request, string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        
        $validated = $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $this->customerService->blockCustomer($customer, $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Customer blocked successfully',
            'data' => $customer->fresh()
        ]);
    }

    /**
     * Unblock customer
     */
    public function unblock(Request $request, string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        
        $validated = $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $this->customerService->unblockCustomer($customer, $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Customer unblocked successfully',
            'data' => $customer->fresh()
        ]);
    }

    /**
     * Get customer analytics
     */
    public function analytics(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $analytics = $this->customerService->getCustomerAnalytics($customer);

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get customer LTV prediction
     */
    public function ltvPrediction(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $prediction = $this->customerService->predictCustomerLTV($customer);

        return response()->json([
            'success' => true,
            'data' => $prediction
        ]);
    }

    /**
     * Get customer segmentation
     */
    public function segments(): JsonResponse
    {
        $segments = $this->customerService->segmentCustomers();

        return response()->json([
            'success' => true,
            'data' => $segments
        ]);
    }

    /**
     * Get customer events
     */
    public function events(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $events = $customer->events()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Get customer communications
     */
    public function communications(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $communications = $customer->communications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $communications
        ]);
    }
}