<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FraudAlert;
use App\Models\FraudRule;
use App\Models\Blacklist;
use App\Models\Whitelist;
use App\Models\RiskProfile;
use App\Services\FraudDetectionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FraudDetectionController extends Controller
{
    public function __construct(
        private FraudDetectionService $fraudService
    ) {}

    /**
     * Analyze payment for fraud risk
     */
    public function analyzePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'ip_address' => 'required|ip',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'payment_gateway' => 'required|string',
            'country' => 'nullable|string|size:2',
            'device_fingerprint' => 'nullable|array'
        ]);

        try {
            $analysis = $this->fraudService->analyzePayment(
                $validated['email'],
                $validated['ip_address'],
                $validated['amount'],
                $validated['currency'],
                $validated['payment_gateway'],
                $validated['country'] ?? null,
                $validated['device_fingerprint'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $analysis
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze payment: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get fraud alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $query = FraudAlert::with(['riskProfile']);

        // Filter by severity
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $alerts = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $alerts
        ]);
    }

    /**
     * Get fraud rules
     */
    public function rules(Request $request): JsonResponse
    {
        $query = FraudRule::query();

        // Filter by active status
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $rules = $query->orderBy('priority', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }

    /**
     * Create fraud rule
     */
    public function createRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|integer|min:1|max:10',
            'conditions' => 'required|array',
            'action' => 'required|in:allow,review,block',
            'risk_score_impact' => 'required|integer|min:0|max:100',
            'is_active' => 'boolean'
        ]);

        try {
            $rule = FraudRule::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Fraud rule created successfully',
                'data' => $rule
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create fraud rule: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update fraud rule
     */
    public function updateRule(Request $request, string $id): JsonResponse
    {
        $rule = FraudRule::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'priority' => 'sometimes|integer|min:1|max:10',
            'conditions' => 'sometimes|array',
            'action' => 'sometimes|in:allow,review,block',
            'risk_score_impact' => 'sometimes|integer|min:0|max:100',
            'is_active' => 'sometimes|boolean'
        ]);

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fraud rule updated successfully',
            'data' => $rule->fresh()
        ]);
    }

    /**
     * Get blacklist entries
     */
    public function blacklist(Request $request): JsonResponse
    {
        $query = Blacklist::active();

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $blacklist = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $blacklist
        ]);
    }

    /**
     * Add to blacklist
     */
    public function addToBlacklist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:email,ip,domain,country,card_bin',
            'value' => 'required|string|max:255',
            'reason' => 'required|string|max:255',
            'expires_at' => 'nullable|date'
        ]);

        try {
            $blacklist = Blacklist::create([
                ...$validated,
                'is_active' => true,
                'added_by' => auth()->user()?->id ?? 'api'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Added to blacklist successfully',
                'data' => $blacklist
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add to blacklist: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get whitelist entries
     */
    public function whitelist(Request $request): JsonResponse
    {
        $query = Whitelist::active();

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $whitelist = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $whitelist
        ]);
    }

    /**
     * Add to whitelist
     */
    public function addToWhitelist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:email,ip,domain,country,card_bin',
            'value' => 'required|string|max:255',
            'reason' => 'required|string|max:255',
            'expires_at' => 'nullable|date'
        ]);

        try {
            $whitelist = Whitelist::create([
                ...$validated,
                'is_active' => true,
                'added_by' => auth()->user()?->id ?? 'api'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Added to whitelist successfully',
                'data' => $whitelist
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add to whitelist: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get risk profiles
     */
    public function riskProfiles(Request $request): JsonResponse
    {
        $query = RiskProfile::query();

        // Filter by risk level
        if ($request->filled('high_risk')) {
            if ($request->boolean('high_risk')) {
                $query->where('risk_score', '>=', 60);
            }
        }

        // Search by email or IP
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $profiles = $query->orderBy('risk_score', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $profiles
        ]);
    }

    /**
     * Get fraud statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_alerts' => FraudAlert::count(),
            'alerts_last_24h' => FraudAlert::where('created_at', '>=', now()->subDay())->count(),
            'high_risk_profiles' => RiskProfile::where('risk_score', '>=', 60)->count(),
            'blocked_profiles' => RiskProfile::where('is_blocked', true)->count(),
            'active_rules' => FraudRule::where('is_active', true)->count(),
            'blacklist_entries' => Blacklist::active()->count(),
            'whitelist_entries' => Whitelist::active()->count(),
            
            // Rule effectiveness
            'rule_accuracy' => FraudRule::active()
                ->selectRaw('AVG(accuracy_rate) as avg_accuracy')
                ->value('avg_accuracy') ?? 0,
            
            // Recent activity
            'recent_high_risk_transactions' => FraudAlert::where('severity', 'high')
                ->where('created_at', '>=', now()->subHours(24))
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}