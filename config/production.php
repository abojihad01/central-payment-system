<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Production Security Configuration
    |--------------------------------------------------------------------------
    */

    'security' => [
        'force_https' => env('FORCE_HTTPS', true),
        'hsts_max_age' => env('HSTS_MAX_AGE', 31536000),
        'content_security_policy' => env('CSP_ENABLED', true),
        'rate_limiting' => [
            'enabled' => true,
            'max_attempts' => env('THROTTLE_REQUESTS', 60),
            'decay_minutes' => env('THROTTLE_DECAY_MINUTES', 1),
        ],
        'admin_2fa' => env('ADMIN_2FA_ENABLED', true),
        'session_timeout' => env('ADMIN_SESSION_TIMEOUT', 30),
    ],

    'monitoring' => [
        'sentry_enabled' => env('SENTRY_LARAVEL_DSN') !== null,
        'error_reporting' => E_ERROR | E_WARNING | E_PARSE,
        'log_level' => env('LOG_LEVEL', 'error'),
    ],

    'performance' => [
        'opcache_enabled' => true,
        'config_cache' => true,
        'route_cache' => true,
        'view_cache' => true,
        'event_cache' => true,
    ],

    'maintenance' => [
        'allowed_ips' => [
            '127.0.0.1',
            '::1',
            // Add your admin IPs here
        ],
        'retry_after' => 3600, // 1 hour
    ],

    'fraud_detection' => [
        'enabled' => env('FRAUD_DETECTION_ENABLED', true),
        'strict_mode' => true,
        'auto_block_threshold' => 85,
        'review_threshold' => 50,
    ],

    'payments' => [
        'max_retry_attempts' => env('PAYMENT_RETRY_MAX_ATTEMPTS', 5),
        'retry_delay_minutes' => [1, 5, 15, 60, 240], // Exponential backoff
        'webhook_timeout' => 30,
        'refund_window_days' => 30,
    ],

    'subscriptions' => [
        'grace_period_days' => env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 3),
        'expiration_warning_days' => [30, 7, 1],
        'auto_renewal_enabled' => true,
    ],

    'database' => [
        'backup_enabled' => true,
        'backup_schedule' => '0 2 * * *', // Daily at 2 AM
        'retention_days' => 30,
    ],
];