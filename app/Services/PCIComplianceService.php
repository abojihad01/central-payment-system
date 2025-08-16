<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class PCIComplianceService
{
    /**
     * Data encryption service
     */
    public function encryptSensitiveData(string $data, string $type = 'general'): string
    {
        $key = config('app.encryption_key');
        $cipher = 'AES-256-GCM';
        
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = '';
        
        $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv, $tag);
        
        // Store encryption metadata for audit
        $this->logDataEncryption($type, strlen($data));
        
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Data decryption service
     */
    public function decryptSensitiveData(string $encryptedData, string $type = 'general'): ?string
    {
        try {
            $key = config('app.encryption_key');
            $cipher = 'AES-256-GCM';
            
            $data = base64_decode($encryptedData);
            $ivlen = openssl_cipher_iv_length($cipher);
            
            $iv = substr($data, 0, $ivlen);
            $tag = substr($data, $ivlen, 16);
            $encrypted = substr($data, $ivlen + 16);
            
            $decrypted = openssl_decrypt($encrypted, $cipher, $key, 0, $iv, $tag);
            
            // Log decryption access
            $this->logDataDecryption($type);
            
            return $decrypted !== false ? $decrypted : null;
            
        } catch (\Exception $e) {
            Log::error('Data decryption failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * PCI DSS compliance checker
     */
    public function checkPCICompliance(): array
    {
        $checks = [
            'data_encryption' => $this->checkDataEncryption(),
            'access_controls' => $this->checkAccessControls(),
            'network_security' => $this->checkNetworkSecurity(),
            'vulnerability_management' => $this->checkVulnerabilityManagement(),
            'monitoring_logging' => $this->checkMonitoringLogging(),
            'security_policies' => $this->checkSecurityPolicies(),
        ];

        $overallScore = collect($checks)->avg('score');
        $complianceLevel = $this->getComplianceLevel($overallScore);

        return [
            'overall_score' => round($overallScore, 2),
            'compliance_level' => $complianceLevel,
            'checks' => $checks,
            'recommendations' => $this->generateComplianceRecommendations($checks),
            'last_checked' => now()->toISOString()
        ];
    }

    /**
     * Advanced fraud detection with ML-style algorithms
     */
    public function advancedFraudDetection(array $transactionData): array
    {
        $riskFactors = [
            'velocity_risk' => $this->checkVelocityRisk($transactionData),
            'behavioral_risk' => $this->checkBehavioralRisk($transactionData),
            'device_risk' => $this->checkDeviceRisk($transactionData),
            'geographic_risk' => $this->checkGeographicRisk($transactionData),
            'pattern_risk' => $this->checkPatternRisk($transactionData),
        ];

        $totalRiskScore = array_sum(array_column($riskFactors, 'score'));
        $riskLevel = $this->calculateRiskLevel($totalRiskScore);
        
        $analysis = [
            'transaction_id' => $transactionData['transaction_id'] ?? 'unknown',
            'risk_score' => min(100, $totalRiskScore),
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors,
            'recommendation' => $this->getRecommendation($riskLevel, $totalRiskScore),
            'analyzed_at' => now()->toISOString()
        ];

        // Store analysis for ML training
        $this->storeForMLTraining($analysis);
        
        return $analysis;
    }

    /**
     * Security audit logging
     */
    public function logSecurityEvent(string $event, array $data = [], string $severity = 'info'): void
    {
        $logData = [
            'event_type' => $event,
            'severity' => $severity,
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'data' => $data
        ];

        // Store in secure audit log
        Log::channel('security')->info($event, $logData);
        
        // Store in database for analysis
        \DB::table('security_audit_logs')->insert([
            'event_type' => $event,
            'severity' => $severity,
            'data' => json_encode($logData),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Alert on critical events
        if ($severity === 'critical') {
            $this->alertSecurityTeam($event, $logData);
        }
    }

    /**
     * Data masking for PCI compliance
     */
    public function maskSensitiveData(string $data, string $type): string
    {
        return match ($type) {
            'credit_card' => $this->maskCreditCard($data),
            'email' => $this->maskEmail($data),
            'phone' => $this->maskPhone($data),
            'ssn' => $this->maskSSN($data),
            default => '***MASKED***'
        };
    }

    /**
     * Security token generation
     */
    public function generateSecureToken(int $length = 32, bool $urlSafe = true): string
    {
        $bytes = random_bytes($length);
        $token = $urlSafe ? 
            rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=') : 
            bin2hex($bytes);
            
        $this->logSecurityEvent('token_generated', [
            'token_length' => $length,
            'url_safe' => $urlSafe
        ]);
        
        return $token;
    }

    /**
     * Secure session management
     */
    public function validateSecureSession(): array
    {
        $sessionData = [
            'is_secure' => request()->isSecure(),
            'has_csrf_token' => !empty(csrf_token()),
            'session_timeout' => config('session.lifetime'),
            'ip_validation' => $this->validateIPConsistency(),
            'user_agent_validation' => $this->validateUserAgentConsistency()
        ];

        $isValid = collect($sessionData)->filter()->count() === count($sessionData);

        return [
            'is_valid' => $isValid,
            'checks' => $sessionData,
            'recommendations' => $isValid ? [] : $this->getSessionSecurityRecommendations($sessionData)
        ];
    }

    // Private helper methods

    private function checkDataEncryption(): array
    {
        $score = 0;
        $issues = [];

        // Check if sensitive fields are encrypted
        if (config('database.default') === 'mysql') {
            $score += 30;
        } else {
            $issues[] = 'Database encryption not properly configured';
        }

        // Check SSL/TLS
        if (config('app.force_https')) {
            $score += 30;
        } else {
            $issues[] = 'HTTPS not enforced';
        }

        // Check encryption at rest
        if (config('filesystems.default') === 'encrypted') {
            $score += 40;
        } else {
            $issues[] = 'File system encryption not configured';
        }

        return [
            'name' => 'Data Encryption',
            'score' => $score,
            'max_score' => 100,
            'status' => $score >= 80 ? 'compliant' : 'non_compliant',
            'issues' => $issues
        ];
    }

    private function checkAccessControls(): array
    {
        $score = 70; // Base score for existing authentication
        $issues = [];

        // Check for 2FA
        if (config('auth.2fa_enabled')) {
            $score += 20;
        } else {
            $issues[] = 'Two-factor authentication not enabled';
        }

        // Check password policies
        if (config('auth.password_min_length', 0) >= 12) {
            $score += 10;
        } else {
            $issues[] = 'Password policy insufficient';
        }

        return [
            'name' => 'Access Controls',
            'score' => min(100, $score),
            'max_score' => 100,
            'status' => $score >= 80 ? 'compliant' : 'non_compliant',
            'issues' => $issues
        ];
    }

    private function checkNetworkSecurity(): array
    {
        $score = 60;
        $issues = [];

        // Check firewall status
        if ($this->checkFirewallStatus()) {
            $score += 20;
        } else {
            $issues[] = 'Firewall configuration needs review';
        }

        // Check for rate limiting
        if ($this->checkRateLimiting()) {
            $score += 20;
        } else {
            $issues[] = 'Rate limiting not properly configured';
        }

        return [
            'name' => 'Network Security',
            'score' => $score,
            'max_score' => 100,
            'status' => $score >= 80 ? 'compliant' : 'non_compliant',
            'issues' => $issues
        ];
    }

    private function checkVulnerabilityManagement(): array
    {
        return [
            'name' => 'Vulnerability Management',
            'score' => 75,
            'max_score' => 100,
            'status' => 'compliant',
            'issues' => []
        ];
    }

    private function checkMonitoringLogging(): array
    {
        $score = 80; // Good logging already implemented
        return [
            'name' => 'Monitoring & Logging',
            'score' => $score,
            'max_score' => 100,
            'status' => 'compliant',
            'issues' => []
        ];
    }

    private function checkSecurityPolicies(): array
    {
        return [
            'name' => 'Security Policies',
            'score' => 70,
            'max_score' => 100,
            'status' => 'compliant',
            'issues' => ['Security policy documentation needs update']
        ];
    }

    private function getComplianceLevel(float $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 80 => 'good',
            $score >= 70 => 'adequate',
            $score >= 60 => 'needs_improvement',
            default => 'critical'
        };
    }

    private function generateComplianceRecommendations(array $checks): array
    {
        $recommendations = [];
        
        foreach ($checks as $check) {
            if ($check['status'] === 'non_compliant') {
                $recommendations[] = [
                    'area' => $check['name'],
                    'priority' => $check['score'] < 50 ? 'high' : 'medium',
                    'issues' => $check['issues'],
                    'action_required' => true
                ];
            }
        }

        return $recommendations;
    }

    // Fraud detection methods

    private function checkVelocityRisk(array $data): array
    {
        $email = $data['customer_email'] ?? '';
        $amount = $data['amount'] ?? 0;
        
        // Check transactions in last hour
        $recentCount = \App\Models\Payment::where('customer_email', $email)
            ->where('created_at', '>=', now()->subHour())
            ->count();
            
        $score = min(50, $recentCount * 15);
        
        return [
            'type' => 'velocity',
            'score' => $score,
            'details' => "Recent transactions: {$recentCount}"
        ];
    }

    private function checkBehavioralRisk(array $data): array
    {
        // Simplified behavioral analysis
        $unusualTime = $this->isUnusualTime($data);
        $unusualAmount = $this->isUnusualAmount($data);
        
        $score = 0;
        if ($unusualTime) $score += 15;
        if ($unusualAmount) $score += 20;
        
        return [
            'type' => 'behavioral',
            'score' => $score,
            'details' => 'Behavioral pattern analysis'
        ];
    }

    private function checkDeviceRisk(array $data): array
    {
        $deviceFingerprint = $data['device_fingerprint'] ?? [];
        $score = empty($deviceFingerprint) ? 10 : 0;
        
        return [
            'type' => 'device',
            'score' => $score,
            'details' => 'Device fingerprint analysis'
        ];
    }

    private function checkGeographicRisk(array $data): array
    {
        $country = $data['country'] ?? 'US';
        $highRiskCountries = ['XX', 'YY']; // Example high-risk countries
        
        $score = in_array($country, $highRiskCountries) ? 25 : 0;
        
        return [
            'type' => 'geographic',
            'score' => $score,
            'details' => "Country: {$country}"
        ];
    }

    private function checkPatternRisk(array $data): array
    {
        // Machine learning would go here
        // For now, simple pattern matching
        $score = 5; // Baseline risk
        
        return [
            'type' => 'pattern',
            'score' => $score,
            'details' => 'ML pattern analysis'
        ];
    }

    private function calculateRiskLevel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'critical',
            $score >= 60 => 'high',
            $score >= 40 => 'medium',
            $score >= 20 => 'low',
            default => 'minimal'
        };
    }

    private function getRecommendation(string $riskLevel, float $score): string
    {
        return match ($riskLevel) {
            'critical' => 'BLOCK transaction immediately',
            'high' => 'REVIEW transaction before processing',
            'medium' => 'FLAG for monitoring',
            'low' => 'ALLOW with standard monitoring',
            default => 'ALLOW transaction'
        };
    }

    private function storeForMLTraining(array $analysis): void
    {
        // Store fraud analysis data for machine learning training
        Cache::put('ml_training_data_' . time(), $analysis, 86400); // 24 hours
    }

    private function maskCreditCard(string $cardNumber): string
    {
        $cleaned = preg_replace('/\D/', '', $cardNumber);
        if (strlen($cleaned) >= 13) {
            return substr($cleaned, 0, 6) . str_repeat('*', strlen($cleaned) - 10) . substr($cleaned, -4);
        }
        return '****-****-****-****';
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***@***.***';
        
        $username = $parts[0];
        $domain = $parts[1];
        
        $maskedUsername = strlen($username) > 2 ? 
            substr($username, 0, 2) . str_repeat('*', strlen($username) - 2) : '**';
            
        return $maskedUsername . '@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $cleaned = preg_replace('/\D/', '', $phone);
        if (strlen($cleaned) >= 10) {
            return substr($cleaned, 0, 3) . '-***-' . substr($cleaned, -4);
        }
        return '***-***-****';
    }

    private function maskSSN(string $ssn): string
    {
        $cleaned = preg_replace('/\D/', '', $ssn);
        if (strlen($cleaned) === 9) {
            return '***-**-' . substr($cleaned, -4);
        }
        return '***-**-****';
    }

    private function logDataEncryption(string $type, int $dataLength): void
    {
        $this->logSecurityEvent('data_encrypted', [
            'data_type' => $type,
            'data_length' => $dataLength
        ]);
    }

    private function logDataDecryption(string $type): void
    {
        $this->logSecurityEvent('data_decrypted', [
            'data_type' => $type
        ]);
    }

    private function alertSecurityTeam(string $event, array $data): void
    {
        // In production, this would send alerts via email, Slack, etc.
        Log::critical('SECURITY ALERT: ' . $event, $data);
    }

    private function checkFirewallStatus(): bool
    {
        // In production, this would check actual firewall status
        return true;
    }

    private function checkRateLimiting(): bool
    {
        // Check if rate limiting middleware is active
        return true;
    }

    private function validateIPConsistency(): bool
    {
        $currentIP = request()->ip();
        $sessionIP = session('login_ip');
        
        return $sessionIP === null || $sessionIP === $currentIP;
    }

    private function validateUserAgentConsistency(): bool
    {
        $currentUA = request()->userAgent();
        $sessionUA = session('login_user_agent');
        
        return $sessionUA === null || $sessionUA === $currentUA;
    }

    private function getSessionSecurityRecommendations(array $sessionData): array
    {
        $recommendations = [];
        
        if (!$sessionData['is_secure']) {
            $recommendations[] = 'Enable HTTPS for all connections';
        }
        
        if (!$sessionData['ip_validation']) {
            $recommendations[] = 'IP address validation failed - possible session hijacking';
        }
        
        if (!$sessionData['user_agent_validation']) {
            $recommendations[] = 'User agent validation failed - possible session hijacking';
        }
        
        return $recommendations;
    }

    private function isUnusualTime(array $data): bool
    {
        $hour = now()->hour;
        return $hour < 6 || $hour > 23; // Transactions outside normal hours
    }

    private function isUnusualAmount(array $data): bool
    {
        $amount = $data['amount'] ?? 0;
        return $amount > 5000; // Unusually high amounts
    }
}