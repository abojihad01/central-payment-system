<?php

namespace App\Services;

use App\Models\RiskProfile;
use App\Models\FraudRule;
use App\Models\FraudAlert;
use App\Models\Blacklist;
use App\Models\Whitelist;
use App\Models\DeviceFingerprint;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FraudDetectionService
{
    /**
     * Analyze a payment for fraud risk
     */
    public function analyzePayment(
        string $email,
        string $ipAddress,
        float $amount,
        string $currency,
        ?string $countryCode = null,
        ?array $deviceFingerprint = null,
        ?Payment $payment = null
    ): array {
        // Initialize analysis result
        $analysis = [
            'risk_score' => 0,
            'risk_level' => 'low',
            'action' => 'allow',
            'triggered_rules' => [],
            'recommendations' => [],
            'should_block' => false,
            'requires_review' => false
        ];

        // 1. Check blacklists first (immediate block)
        if ($this->isBlacklisted($email, $ipAddress, $countryCode)) {
            $analysis['risk_score'] = 100;
            $analysis['risk_level'] = 'blocked';
            $analysis['action'] = 'block';
            $analysis['should_block'] = true;
            $analysis['triggered_rules'][] = 'blacklisted_email';
            
            $this->createAlert($email, $ipAddress, 'blacklist', 'critical', 100, 
                'Customer or IP found in blacklist', ['blacklist_match' => true], $payment);
                
            return $analysis;
        }

        // 2. Check whitelists (immediate allow with low risk)
        if ($this->isWhitelisted($email, $ipAddress, $countryCode)) {
            $analysis['risk_score'] = max(0, $analysis['risk_score'] - 20);
            $analysis['triggered_rules'][] = 'whitelisted_email';
            $analysis['recommendations'][] = 'Customer is whitelisted';
        }

        // 3. Get or create risk profile
        $riskProfile = $this->getOrCreateRiskProfile($email, $ipAddress, $countryCode);
        
        // 4. Update device fingerprint if provided
        if ($deviceFingerprint) {
            $this->updateDeviceFingerprint($deviceFingerprint, $email, $ipAddress);
        }

        // 5. Run fraud rules
        $rules = FraudRule::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($rules as $rule) {
            $ruleResult = $this->evaluateRule($rule, [
                'email' => $email,
                'ip_address' => $ipAddress,
                'amount' => $amount,
                'currency' => $currency,
                'country_code' => $countryCode,
                'risk_profile' => $riskProfile,
                'device_fingerprint' => $deviceFingerprint
            ]);

            if ($ruleResult['triggered']) {
                $analysis['risk_score'] += $rule->risk_score_impact;
                $analysis['triggered_rules'][] = $rule->name;
                $analysis['recommendations'][] = $ruleResult['message'];

                // Update rule statistics
                $rule->increment('times_triggered');

                // Apply rule action
                if ($rule->action === 'block') {
                    $analysis['should_block'] = true;
                    $analysis['action'] = 'block';
                } elseif ($rule->action === 'review' && $analysis['action'] !== 'block') {
                    $analysis['requires_review'] = true;
                    $analysis['action'] = 'review';
                }
            }
        }

        // 6. Velocity checks
        $velocityAnalysis = $this->performVelocityChecks($email, $ipAddress, $amount);
        $analysis['risk_score'] += $velocityAnalysis['risk_score_impact'];
        $analysis['triggered_rules'] = array_merge($analysis['triggered_rules'], $velocityAnalysis['triggered_rules']);

        // 7. Pattern analysis
        $patternAnalysis = $this->analyzePaymentPatterns($riskProfile, $amount, $currency);
        $analysis['risk_score'] += $patternAnalysis['risk_score_impact'];
        $analysis['triggered_rules'] = array_merge($analysis['triggered_rules'], $patternAnalysis['triggered_rules']);

        // 8. Determine final risk level
        $analysis['risk_level'] = $this->calculateRiskLevel($analysis['risk_score']);

        // 9. Final decision logic
        if ($analysis['risk_score'] >= 80 || $analysis['should_block']) {
            $analysis['action'] = 'block';
            $analysis['should_block'] = true;
        } elseif ($analysis['risk_score'] >= 50 || $analysis['requires_review']) {
            $analysis['action'] = 'review';
            $analysis['requires_review'] = true;
        }

        // 10. Update risk profile
        $this->updateRiskProfile($riskProfile, $analysis['risk_score']);

        // 11. Create alert if high risk
        if ($analysis['risk_score'] >= 50) {
            $severity = $analysis['risk_score'] >= 80 ? 'high' : 'medium';
            $this->createAlert($email, $ipAddress, 'high_risk', $severity, 
                $analysis['risk_score'], 'High risk transaction detected', 
                $analysis, $payment);
        }

        return $analysis;
    }

    /**
     * Check if email, IP, or country is blacklisted
     */
    private function isBlacklisted(string $email, string $ipAddress, ?string $countryCode): bool
    {
        $blacklists = Blacklist::where('is_active', true)
            ->where(function ($query) use ($email, $ipAddress, $countryCode) {
                $query->where(function ($q) use ($email) {
                    $q->where('type', 'email')->where('value', $email);
                })->orWhere(function ($q) use ($ipAddress) {
                    $q->where('type', 'ip')->where('value', $ipAddress);
                });
                
                if ($countryCode) {
                    $query->orWhere(function ($q) use ($countryCode) {
                        $q->where('type', 'country')->where('value', $countryCode);
                    });
                }
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        return $blacklists;
    }

    /**
     * Check if email, IP, or country is whitelisted
     */
    private function isWhitelisted(string $email, string $ipAddress, ?string $countryCode): bool
    {
        return Whitelist::where('is_active', true)
            ->where(function ($query) use ($email, $ipAddress, $countryCode) {
                $query->where(function ($q) use ($email) {
                    $q->where('type', 'email')->where('value', $email);
                })->orWhere(function ($q) use ($ipAddress) {
                    $q->where('type', 'ip')->where('value', $ipAddress);
                });
                
                if ($countryCode) {
                    $query->orWhere(function ($q) use ($countryCode) {
                        $q->where('type', 'country')->where('value', $countryCode);
                    });
                }
            })
            ->exists();
    }

    /**
     * Get or create risk profile
     */
    private function getOrCreateRiskProfile(string $email, string $ipAddress, ?string $countryCode): RiskProfile
    {
        return RiskProfile::firstOrCreate(
            ['email' => $email],
            [
                'ip_address' => $ipAddress,
                'country_code' => $countryCode,
                'risk_score' => 0,
                'risk_level' => 'low',
                'last_activity_at' => now()
            ]
        );
    }

    /**
     * Evaluate a fraud rule
     */
    private function evaluateRule(FraudRule $rule, array $data): array
    {
        $conditions = $rule->conditions;
        $triggered = true;
        $message = "Rule '{$rule->name}' triggered";

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? '';

            $actualValue = data_get($data, $field);

            $conditionMet = match ($operator) {
                '=' => $actualValue == $value,
                '!=' => $actualValue != $value,
                '>' => $actualValue > $value,
                '>=' => $actualValue >= $value,
                '<' => $actualValue < $value,
                '<=' => $actualValue <= $value,
                'in' => in_array($actualValue, (array) $value),
                'not_in' => !in_array($actualValue, (array) $value),
                'contains' => str_contains(strtolower($actualValue), strtolower($value)),
                'regex' => preg_match($value, $actualValue),
                default => false
            };

            if (!$conditionMet) {
                $triggered = false;
                break;
            }
        }

        return [
            'triggered' => $triggered,
            'message' => $message,
            'rule_id' => $rule->id
        ];
    }

    /**
     * Perform velocity checks
     */
    private function performVelocityChecks(string $email, string $ipAddress, float $amount): array
    {
        $result = [
            'risk_score_impact' => 0,
            'triggered_rules' => []
        ];

        $now = Carbon::now();

        // Check recent payment attempts from same email
        $recentEmailPayments = Payment::where('customer_email', $email)
            ->where('created_at', '>=', $now->subMinutes(10))
            ->count();

        if ($recentEmailPayments > 3) {
            $result['risk_score_impact'] += 25;
            $result['triggered_rules'][] = 'velocity';
        }

        // Check recent payment attempts from same IP
        $recentIpPayments = Payment::where('created_at', '>=', $now->subMinutes(10))
            // Note: We'd need to track IP in payments table for this
            ->count();

        // Check large amounts in short time
        $recentLargePayments = Payment::where('customer_email', $email)
            ->where('created_at', '>=', $now->subHour())
            ->where('amount', '>', 500)
            ->count();

        if ($recentLargePayments > 2) {
            $result['risk_score_impact'] += 20;
            $result['triggered_rules'][] = 'large_amount_velocity';
        }

        return $result;
    }

    /**
     * Analyze payment patterns
     */
    private function analyzePaymentPatterns(RiskProfile $profile, float $amount, string $currency): array
    {
        $result = [
            'risk_score_impact' => 0,
            'triggered_rules' => []
        ];

        // Check if amount is significantly different from user's typical amounts
        $patterns = $profile->payment_patterns ?? [];
        
        if (isset($patterns['avg_amount'])) {
            $avgAmount = $patterns['avg_amount'];
            $deviation = abs($amount - $avgAmount) / max($avgAmount, 1);
            
            if ($deviation > 3) { // More than 3x typical amount
                $result['risk_score_impact'] += 15;
                $result['triggered_rules'][] = 'amount_pattern_anomaly';
            }
        }

        // Check unusual time patterns
        $currentHour = now()->hour;
        $typicalHours = $patterns['typical_hours'] ?? [];
        
        if (!empty($typicalHours) && !in_array($currentHour, $typicalHours)) {
            $result['risk_score_impact'] += 10;
            $result['triggered_rules'][] = 'time_pattern_anomaly';
        }

        return $result;
    }

    /**
     * Calculate risk level based on score
     */
    private function calculateRiskLevel(int $riskScore): string
    {
        return match (true) {
            $riskScore >= 80 => 'blocked',
            $riskScore >= 60 => 'high',
            $riskScore >= 30 => 'medium',
            default => 'low'
        };
    }

    /**
     * Update risk profile
     */
    private function updateRiskProfile(RiskProfile $profile, int $newRiskScore): void
    {
        $profile->update([
            'risk_score' => $newRiskScore,
            'risk_level' => $this->calculateRiskLevel($newRiskScore),
            'last_activity_at' => now()
        ]);
    }

    /**
     * Create fraud alert
     */
    private function createAlert(
        string $email,
        string $ipAddress,
        string $alertType,
        string $severity,
        int $riskScore,
        string $description,
        array $metadata,
        ?Payment $payment = null
    ): FraudAlert {
        return FraudAlert::create([
            'alert_id' => 'alert_' . Str::uuid(),
            'payment_id' => $payment?->id,
            'email' => $email,
            'ip_address' => $ipAddress,
            'alert_type' => $alertType,
            'severity' => $severity,
            'risk_score' => $riskScore,
            'description' => $description,
            'triggered_rules' => $metadata['triggered_rules'] ?? [],
            'metadata' => $metadata,
            'status' => 'pending'
        ]);
    }

    /**
     * Update device fingerprint
     */
    private function updateDeviceFingerprint(array $fingerprint, string $email, string $ipAddress): void
    {
        $hash = md5(json_encode($fingerprint));
        
        DeviceFingerprint::updateOrCreate(
            ['fingerprint_hash' => $hash],
            [
                'fingerprint_data' => $fingerprint,
                'email' => $email,
                'ip_address' => $ipAddress,
                'last_seen_at' => now(),
                'first_seen_at' => now()
            ]
        );
    }

    /**
     * Block a customer
     */
    public function blockCustomer(string $email, string $reason, ?string $until = null): void
    {
        $profile = $this->getOrCreateRiskProfile($email, '', '');
        
        $profile->update([
            'is_blocked' => true,
            'blocked_until' => $until ? Carbon::parse($until) : null,
            'blocked_reason' => $reason,
            'risk_level' => 'blocked',
            'risk_score' => 100
        ]);

        // Add to blacklist
        Blacklist::create([
            'type' => 'email',
            'value' => $email,
            'reason' => $reason,
            'expires_at' => $until ? Carbon::parse($until) : null,
            'added_by' => auth()->user()->id ?? 'system'
        ]);
    }

    /**
     * Get fraud statistics
     */
    public function getFraudStatistics(?int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        return [
            'total_alerts' => FraudAlert::where('created_at', '>=', $startDate)->count(),
            'high_risk_alerts' => FraudAlert::where('created_at', '>=', $startDate)
                ->where('severity', 'high')->count(),
            'blocked_transactions' => FraudAlert::where('created_at', '>=', $startDate)
                ->whereHas('payment', fn($q) => $q->where('status', 'failed'))->count(),
            'false_positives' => FraudAlert::where('created_at', '>=', $startDate)
                ->where('status', 'false_positive')->count(),
            'average_risk_score' => FraudAlert::where('created_at', '>=', $startDate)
                ->avg('risk_score') ?? 0,
            'top_triggered_rules' => FraudRule::orderBy('times_triggered', 'desc')
                ->take(5)->pluck('name', 'times_triggered'),
            'blacklisted_count' => Blacklist::where('is_active', true)->count(),
            'whitelisted_count' => Whitelist::where('is_active', true)->count(),
        ];
    }
}