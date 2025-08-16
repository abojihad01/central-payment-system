<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\BotProtectionSettings;
use App\Models\BotDetection;
use App\Services\RecaptchaService;

class BotProtection
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        // Check if protection is globally enabled
        if (!BotProtectionSettings::get('protection_enabled', true)) {
            return $next($request);
        }

        // Skip protection for certain routes (APIs, webhooks, etc.)
        if ($this->shouldSkipProtection($request)) {
            return $next($request);
        }

        $ip = $request->ip();

        // Check IP whitelist
        $whitelist = BotProtectionSettings::get('whitelist_ips', []);
        if (in_array($ip, $whitelist)) {
            return $next($request);
        }

        // Check IP blacklist
        $blacklist = BotProtectionSettings::get('blacklist_ips', []);
        if (in_array($ip, $blacklist)) {
            $this->logDetection($request, 'blacklist', 'IP address is blacklisted', 100);
            return $this->handleBotDetection($request, 'blacklist');
        }

        // Check for bot-like behavior
        if ($this->isBotBehavior($request)) {
            return $this->handleBotDetection($request, 'bot_user_agent');
        }

        // Rate limiting per IP
        if ($this->isRateLimited($request)) {
            $this->logDetection($request, 'rate_limit', 'Rate limit exceeded', 75);
            return response()->json([
                'error' => 'Too many requests. Please try again later.',
                'retry_after' => BotProtectionSettings::get('rate_limit_window', 60)
            ], 429);
        }

        // For POST requests on sensitive pages, require reCAPTCHA
        if ($this->requiresRecaptcha($request)) {
            return $this->validateRecaptcha($request, $next);
        }

        return $next($request);
    }

    /**
     * Check if protection should be skipped for this request
     */
    private function shouldSkipProtection(Request $request): bool
    {
        $skipRoutes = [
            'api/*',
            'webhook/*',
            'admin/*',
            'payments/*/webhook',
            'health-check'
        ];

        foreach ($skipRoutes as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect bot-like behavior
     */
    private function isBotBehavior(Request $request): bool
    {
        // Check if bot detection is enabled
        if (!BotProtectionSettings::get('bot_detection_enabled', true)) {
            return false;
        }

        $userAgent = $request->userAgent();
        $ip = $request->ip();

        // Get bot patterns from settings
        $botPatterns = BotProtectionSettings::get('bot_patterns', [
            '/bot/i', '/crawl/i', '/spider/i', '/scrape/i', '/curl/i', '/wget/i'
        ]);

        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $this->logDetection($request, 'bot_user_agent', "Bot pattern matched: {$pattern}", 80);
                return true;
            }
        }

        // Check for missing common headers
        if (!$request->hasHeader('Accept') || 
            !$request->hasHeader('Accept-Language') ||
            !$request->hasHeader('Accept-Encoding')) {
            $this->logDetection($request, 'suspicious_pattern', 'Missing common HTTP headers', 60);
            return true;
        }

        // Check for suspicious request patterns
        if ($this->hasSuspiciousPatterns($request)) {
            return true;
        }

        return false;
    }

    /**
     * Check for suspicious request patterns
     */
    private function hasSuspiciousPatterns(Request $request): bool
    {
        $ip = $request->ip();
        
        // Check request frequency
        $requestCount = Cache::get("requests_count_{$ip}", 0);
        if ($requestCount > 100) { // More than 100 requests in the last minute
            return true;
        }

        // Check for SQL injection patterns
        $queryString = $request->getQueryString();
        if ($queryString && preg_match('/union|select|insert|delete|update|drop|exec|script/i', $queryString)) {
            return true;
        }

        return false;
    }

    /**
     * Handle bot detection
     */
    private function handleBotDetection(Request $request, string $detectionType = 'bot_user_agent')
    {
        $ip = $request->ip();
        
        // Block IP temporarily
        Cache::put("blocked_ip_{$ip}", true, now()->addHours(1));
        
        // Log to Laravel logs
        Log::warning('Bot blocked', [
            'ip' => $ip,
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'detection_type' => $detectionType
        ]);

        return response()->view('errors.bot-detected', [], 403);
    }

    /**
     * Check if IP is rate limited
     */
    private function isRateLimited(Request $request): bool
    {
        // Check if rate limiting is enabled
        if (!BotProtectionSettings::get('rate_limit_enabled', true)) {
            return false;
        }

        $ip = $request->ip();
        $key = "rate_limit_{$ip}";
        
        $requests = Cache::get($key, 0);
        $maxRequests = BotProtectionSettings::get('rate_limit_requests', 10);
        $window = BotProtectionSettings::get('rate_limit_window', 60);
        
        if ($requests >= $maxRequests) {
            return true;
        }
        
        Cache::put($key, $requests + 1, now()->addSeconds($window));
        return false;
    }

    /**
     * Check if reCAPTCHA is required for this request
     */
    private function requiresRecaptcha(Request $request): bool
    {
        // Check if reCAPTCHA is enabled
        if (!BotProtectionSettings::get('recaptcha_enabled', false)) {
            return false;
        }

        // Require reCAPTCHA for POST requests on payment and contact forms
        if ($request->isMethod('POST')) {
            $sensitiveRoutes = [
                'payments/*/process',
                'contact',
                'register',
                'login',
                'payments/create'
            ];

            foreach ($sensitiveRoutes as $route) {
                if ($request->is($route)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate reCAPTCHA
     */
    private function validateRecaptcha(Request $request, Closure $next)
    {
        $recaptchaService = app(RecaptchaService::class);
        
        // Determine action based on route
        $action = $this->getRecaptchaAction($request);
        
        $validation = $recaptchaService->validateRequest($request, $action);
        
        if (!$validation['valid']) {
            // Log the failed verification
            $this->logDetection($request, 'recaptcha_failed', $validation['message'], 85);
            
            Log::warning('reCAPTCHA verification failed', [
                'ip' => $request->ip(),
                'score' => $validation['score'],
                'message' => $validation['message'],
                'action' => $action
            ]);
            
            return back()->withErrors([
                'recaptcha' => 'Security verification failed. Please try again.'
            ]);
        }
        
        // Log successful verification
        Log::info('reCAPTCHA verification successful', [
            'ip' => $request->ip(),
            'score' => $validation['score'],
            'action' => $action
        ]);

        return $next($request);
    }
    
    /**
     * Get reCAPTCHA action based on request
     */
    private function getRecaptchaAction(Request $request): string
    {
        $route = $request->route();
        
        if (!$route) {
            return 'submit';
        }
        
        $routeName = $route->getName();
        $uri = $request->getRequestUri();
        
        // Map routes to actions
        $actionMap = [
            'login' => 'login',
            'register' => 'register',
            'contact' => 'contact',
            'process-payment' => 'payment',
            'checkout' => 'checkout'
        ];
        
        foreach ($actionMap as $pattern => $action) {
            if (str_contains($uri, $pattern) || str_contains($routeName ?: '', $pattern)) {
                return $action;
            }
        }
        
        return 'submit';
    }

    /**
     * Log detection to database
     */
    private function logDetection(Request $request, string $type, ?string $details = null, int $riskScore = 50): void
    {
        // Check if logging is enabled
        if (!BotProtectionSettings::get('log_detections', true)) {
            return;
        }

        try {
            BotDetection::logDetection([
                'type' => $type,
                'details' => $details,
                'risk_score' => $riskScore,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'request_data' => $request->except(['password', '_token', 'g-recaptcha-response']),
                'headers' => $request->headers->all(),
                'is_blocked' => true
            ]);
        } catch (\Exception $e) {
            // Fail silently to avoid breaking the request
            Log::error('Failed to log bot detection', ['error' => $e->getMessage()]);
        }
    }
}