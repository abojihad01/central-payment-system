<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BotProtectionSettings extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'category',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Cache settings for 1 hour
    protected static $cacheTime = 3600;

    public function getValueAttribute($value)
    {
        return match($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value
        };
    }

    public function setValueAttribute($value)
    {
        $this->attributes['value'] = match($this->type ?? 'string') {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json' => is_array($value) ? json_encode($value) : (string) $value,
            default => (string) $value
        };
    }

    public static function get(string $key, $default = null)
    {
        $cacheKey = "bot_protection_settings.{$key}";
        
        return Cache::remember($cacheKey, self::$cacheTime, function() use ($key, $default) {
            $setting = self::where('key', $key)->where('is_active', true)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, $value, string $type = 'string', ?string $description = null, string $category = 'general'): self
    {
        $setting = new self([
            'key' => $key,
            'type' => $type,
            'description' => $description,
            'category' => $category,
            'is_active' => true
        ]);
        
        $setting->value = $value; // Use the mutator
        
        $setting = self::updateOrCreate(
            ['key' => $key],
            $setting->getAttributes()
        );

        // Clear cache
        Cache::forget("bot_protection_settings.{$key}");
        
        return $setting;
    }

    public static function seedDefaults(): void
    {
        $defaults = [
            // Rate Limiting
            [
                'key' => 'rate_limit_enabled',
                'value' => true,
                'type' => 'boolean',
                'description' => 'Enable rate limiting protection',
                'category' => 'rate_limiting'
            ],
            [
                'key' => 'rate_limit_requests',
                'value' => 10,
                'type' => 'integer',
                'description' => 'Maximum requests per minute per IP',
                'category' => 'rate_limiting'
            ],
            [
                'key' => 'rate_limit_window',
                'value' => 60,
                'type' => 'integer',
                'description' => 'Rate limit window in seconds',
                'category' => 'rate_limiting'
            ],

            // Bot Detection
            [
                'key' => 'bot_detection_enabled',
                'value' => true,
                'type' => 'boolean',
                'description' => 'Enable bot user agent detection',
                'category' => 'bot_detection'
            ],
            [
                'key' => 'bot_patterns',
                'value' => ['/bot/i', '/crawl/i', '/spider/i', '/scrape/i', '/curl/i', '/wget/i'],
                'type' => 'json',
                'description' => 'Bot detection patterns',
                'category' => 'bot_detection'
            ],

            // Honeypot
            [
                'key' => 'honeypot_enabled',
                'value' => true,
                'type' => 'boolean',
                'description' => 'Enable honeypot field protection',
                'category' => 'honeypot'
            ],
            [
                'key' => 'min_form_time',
                'value' => 3,
                'type' => 'integer',
                'description' => 'Minimum seconds for form submission',
                'category' => 'honeypot'
            ],

            // reCAPTCHA
            [
                'key' => 'recaptcha_enabled',
                'value' => false,
                'type' => 'boolean',
                'description' => 'Enable reCAPTCHA v3 verification',
                'category' => 'recaptcha'
            ],
            [
                'key' => 'recaptcha_threshold',
                'value' => 0.5,
                'type' => 'string',
                'description' => 'reCAPTCHA v3 score threshold (0.0-1.0). Higher values are more strict.',
                'category' => 'recaptcha'
            ],
            [
                'key' => 'recaptcha_version',
                'value' => 'v3',
                'type' => 'string',
                'description' => 'reCAPTCHA version to use (v2 or v3)',
                'category' => 'recaptcha'
            ],
            [
                'key' => 'recaptcha_actions',
                'value' => ['login', 'register', 'contact', 'payment', 'checkout'],
                'type' => 'json',
                'description' => 'Actions that require reCAPTCHA verification',
                'category' => 'recaptcha'
            ],

            // General
            [
                'key' => 'protection_enabled',
                'value' => true,
                'type' => 'boolean',
                'description' => 'Master bot protection toggle',
                'category' => 'general'
            ],
            [
                'key' => 'log_detections',
                'value' => true,
                'type' => 'boolean',
                'description' => 'Log bot detections to database',
                'category' => 'general'
            ],
            [
                'key' => 'whitelist_ips',
                'value' => ['127.0.0.1', '::1'],
                'type' => 'json',
                'description' => 'IP addresses to whitelist',
                'category' => 'general'
            ],
            [
                'key' => 'blacklist_ips',
                'value' => [],
                'type' => 'json',
                'description' => 'IP addresses to blacklist',
                'category' => 'general'
            ]
        ];

        foreach ($defaults as $setting) {
            $model = new self([
                'key' => $setting['key'],
                'type' => $setting['type'],
                'description' => $setting['description'],
                'category' => $setting['category'],
                'is_active' => true
            ]);
            
            $model->value = $setting['value']; // Use the mutator
            
            self::updateOrCreate(
                ['key' => $setting['key']],
                $model->getAttributes()
            );
        }
    }

    public static function clearCache(): void
    {
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget("bot_protection_settings.{$setting->key}");
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            Cache::forget("bot_protection_settings.{$model->key}");
        });

        static::deleted(function ($model) {
            Cache::forget("bot_protection_settings.{$model->key}");
        });
    }
}
