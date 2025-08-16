<?php

namespace App\Services;

use App\Models\GeneratedLink;
use App\Models\Plan;
use App\Models\Website;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

class PaymentLinkService
{
    private string $jwtKey;
    private string $algorithm = 'HS256';

    public function __construct()
    {
        $this->jwtKey = config('app.jwt_secret', config('app.key'));
    }

    public function generatePaymentLink(
        int $websiteId,
        int $planId,
        string $successUrl,
        string $failureUrl,
        ?int $expiryMinutes = null,
        bool $singleUse = false
    ): array {
        $website = Website::findOrFail($websiteId);
        $plan = Plan::findOrFail($planId);

        if ($plan->website_id !== $websiteId) {
            throw new \InvalidArgumentException('Plan does not belong to the specified website');
        }

        $token = $this->generateToken();
        $expiresAt = $expiryMinutes ? Carbon::now()->addMinutes($expiryMinutes) : null;

        $generatedLink = GeneratedLink::create([
            'website_id' => $websiteId,
            'plan_id' => $planId,
            'token' => $token,
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'expires_at' => $expiresAt,
            'single_use' => $singleUse,
            'is_active' => true,
        ]);

        $payload = [
            'iss' => config('app.url'),
            'aud' => 'payment-system',
            'iat' => Carbon::now()->timestamp,
            'exp' => $expiresAt ? $expiresAt->timestamp : Carbon::now()->addDays(30)->timestamp,
            'data' => [
                'link_id' => $generatedLink->id,
                'website_id' => $websiteId,
                'plan_id' => $planId,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'success_url' => $successUrl,
                'failure_url' => $failureUrl,
                'website_name' => $website->name,
                'website_language' => $website->language,
                'website_logo' => $website->logo,
                'plan_name' => $plan->name,
                'plan_description' => $plan->description,
                'plan_features' => $plan->features,
            ]
        ];

        $jwt = JWT::encode($payload, $this->jwtKey, $this->algorithm);

        return [
            'payment_link' => config('app.url') . '/checkout?token=' . $jwt,
            'token' => $jwt,
            'generated_link_id' => $generatedLink->id,
            'expires_at' => $expiresAt,
        ];
    }

    public function validateAndDecodeToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtKey, $this->algorithm));
            $payload = (array) $decoded;
            $data = (array) $payload['data'];

            $generatedLink = GeneratedLink::find($data['link_id']);

            if (!$generatedLink) {
                throw new \Exception('Payment link not found');
            }

            if (!$generatedLink->isValid()) {
                throw new \Exception('Payment link is no longer valid');
            }

            return $data;
        } catch (\Exception $e) {
            throw new \Exception('Invalid or expired payment token: ' . $e->getMessage());
        }
    }

    public function markLinkAsUsed(int $linkId): void
    {
        GeneratedLink::where('id', $linkId)->update(['is_used' => true]);
    }

    public function deactivateLink(int $linkId): void
    {
        GeneratedLink::where('id', $linkId)->update(['is_active' => false]);
    }

    private function generateToken(): string
    {
        return Str::random(64);
    }
}