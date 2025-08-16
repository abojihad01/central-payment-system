<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\BotProtectionSettings;

class RecaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    
    /**
     * Verify reCAPTCHA v3 token
     */
    public function verify(string $token, string $action = '', ?string $remoteIp = null): array
    {
        $secretKey = config('services.recaptcha.secret_key');
        
        if (empty($secretKey)) {
            Log::warning('reCAPTCHA secret key not configured');
            return [
                'success' => false,
                'error' => 'reCAPTCHA not configured',
                'score' => 0.0
            ];
        }
        
        try {
            $response = Http::asForm()->post(self::VERIFY_URL, [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $remoteIp ?: request()->ip()
            ]);
            
            if (!$response->successful()) {
                Log::error('reCAPTCHA API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'error' => 'API request failed',
                    'score' => 0.0
                ];
            }
            
            $data = $response->json();
            
            // Log the verification attempt
            Log::info('reCAPTCHA verification', [
                'success' => $data['success'] ?? false,
                'score' => $data['score'] ?? 0.0,
                'action' => $data['action'] ?? $action,
                'challenge_ts' => $data['challenge_ts'] ?? null,
                'hostname' => $data['hostname'] ?? null,
                'error_codes' => $data['error-codes'] ?? []
            ]);
            
            return [
                'success' => $data['success'] ?? false,
                'score' => $data['score'] ?? 0.0,
                'action' => $data['action'] ?? $action,
                'challenge_ts' => $data['challenge_ts'] ?? null,
                'hostname' => $data['hostname'] ?? null,
                'error_codes' => $data['error-codes'] ?? []
            ];
            
        } catch (\Exception $e) {
            Log::error('reCAPTCHA verification exception', [
                'message' => $e->getMessage(),
                'token' => substr($token, 0, 20) . '...'
            ]);
            
            return [
                'success' => false,
                'error' => 'Verification failed: ' . $e->getMessage(),
                'score' => 0.0
            ];
        }
    }
    
    /**
     * Validate reCAPTCHA token with configurable threshold
     */
    public function validateRequest(Request $request, string $action = 'submit'): array
    {
        // Check if reCAPTCHA is enabled
        if (!BotProtectionSettings::get('recaptcha_enabled', false)) {
            return [
                'valid' => true,
                'message' => 'reCAPTCHA disabled',
                'score' => 1.0
            ];
        }
        
        // Skip reCAPTCHA in local development if configured to do so
        if ($this->shouldSkipInDevelopment($request)) {
            return [
                'valid' => true,
                'message' => 'reCAPTCHA skipped in development',
                'score' => 1.0
            ];
        }
        
        $token = $request->input('g-recaptcha-response');
        
        if (empty($token)) {
            return [
                'valid' => false,
                'message' => 'reCAPTCHA token missing',
                'score' => 0.0
            ];
        }
        
        $result = $this->verify($token, $action, $request->ip());
        
        if (!$result['success']) {
            // Handle browser-error specifically for localhost
            if (isset($result['error_codes']) && in_array('browser-error', $result['error_codes'])) {
                Log::warning('reCAPTCHA browser-error detected (likely localhost/domain mismatch)', [
                    'host' => $request->getHost(),
                    'url' => $request->fullUrl(),
                    'error_codes' => $result['error_codes']
                ]);
                
                // In development, allow this to pass with a warning
                if (app()->environment('local')) {
                    return [
                        'valid' => true,
                        'message' => 'reCAPTCHA bypassed in local development (browser-error)',
                        'score' => 0.8
                    ];
                }
            }
            
            return [
                'valid' => false,
                'message' => 'reCAPTCHA verification failed: ' . ($result['error'] ?? 'Unknown error'),
                'score' => $result['score']
            ];
        }
        
        // Get threshold from settings
        $threshold = (float) BotProtectionSettings::get('recaptcha_threshold', 0.5);
        $score = $result['score'];
        
        if ($score < $threshold) {
            return [
                'valid' => false,
                'message' => "reCAPTCHA score too low: {$score} < {$threshold}",
                'score' => $score
            ];
        }
        
        // Verify action matches if provided
        if (!empty($action) && isset($result['action']) && $result['action'] !== $action) {
            return [
                'valid' => false,
                'message' => "reCAPTCHA action mismatch: expected '{$action}', got '{$result['action']}'",
                'score' => $score
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'reCAPTCHA verification successful',
            'score' => $score
        ];
    }
    
    /**
     * Check if reCAPTCHA should be skipped in development
     */
    private function shouldSkipInDevelopment(Request $request): bool
    {
        if (!app()->environment('local')) {
            return false;
        }
        
        $host = $request->getHost();
        $developmentHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
            'local.test',
            '.test'
        ];
        
        foreach ($developmentHosts as $devHost) {
            if ($host === $devHost || str_contains($host, $devHost)) {
                return BotProtectionSettings::get('recaptcha_skip_localhost', true);
            }
        }
        
        return false;
    }
    
    /**
     * Get reCAPTCHA site key for frontend
     */
    public function getSiteKey(): ?string
    {
        return config('services.recaptcha.site_key');
    }
    
    /**
     * Check if reCAPTCHA is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty(config('services.recaptcha.site_key')) && 
               !empty(config('services.recaptcha.secret_key'));
    }
    
    /**
     * Get reCAPTCHA configuration for frontend
     */
    public function getConfig(): array
    {
        return [
            'enabled' => BotProtectionSettings::get('recaptcha_enabled', false),
            'site_key' => $this->getSiteKey(),
            'threshold' => (float) BotProtectionSettings::get('recaptcha_threshold', 0.5),
            'configured' => $this->isConfigured()
        ];
    }
}