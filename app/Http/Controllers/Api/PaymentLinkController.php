<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentLinkService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentLinkController extends Controller
{
    public function __construct(
        private PaymentLinkService $paymentLinkService
    ) {}

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'website_id' => 'required|integer|exists:websites,id',
            'plan_id' => 'required|integer|exists:plans,id',
            'success_url' => 'required|url',
            'failure_url' => 'required|url',
            'expiry_minutes' => 'nullable|integer|min:1|max:10080', // max 1 week
            'single_use' => 'boolean',
        ]);

        try {
            $linkData = $this->paymentLinkService->generatePaymentLink(
                websiteId: $request->website_id,
                planId: $request->plan_id,
                successUrl: $request->success_url,
                failureUrl: $request->failure_url,
                expiryMinutes: $request->expiry_minutes,
                singleUse: $request->boolean('single_use', false)
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment link generated successfully',
                'data' => $linkData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        try {
            $data = $this->paymentLinkService->validateAndDecodeToken($request->token);

            return response()->json([
                'success' => true,
                'message' => 'Token is valid',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}