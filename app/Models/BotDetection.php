<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class BotDetection extends Model
{
    protected $fillable = [
        'ip_address',
        'user_agent',
        'detection_type',
        'url_requested',
        'method',
        'request_data',
        'headers',
        'country_code',
        'is_blocked',
        'detection_details',
        'risk_score',
        'detected_at'
    ];

    protected $casts = [
        'request_data' => 'array',
        'headers' => 'array',
        'is_blocked' => 'boolean',
        'detected_at' => 'datetime',
        'risk_score' => 'integer'
    ];

    // Scopes
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('is_blocked', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('detection_type', $type);
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('detected_at', '>=', Carbon::now()->subHours($hours));
    }

    public function scopeByIp(Builder $query, string $ip): Builder
    {
        return $query->where('ip_address', $ip);
    }

    // Accessors
    public function getDetectionTypeNameAttribute(): string
    {
        return match($this->detection_type) {
            'bot_user_agent' => 'Bot User Agent',
            'honeypot' => 'Honeypot Trigger',
            'rate_limit' => 'Rate Limit Exceeded',
            'timing' => 'Fast Submission',
            'suspicious_pattern' => 'Suspicious Pattern',
            default => ucfirst(str_replace('_', ' ', $this->detection_type))
        };
    }

    public function getRiskLevelAttribute(): string
    {
        return match(true) {
            $this->risk_score >= 80 => 'High',
            $this->risk_score >= 50 => 'Medium',
            $this->risk_score >= 20 => 'Low',
            default => 'Very Low'
        };
    }

    public function getRiskColorAttribute(): string
    {
        return match(true) {
            $this->risk_score >= 80 => 'danger',
            $this->risk_score >= 50 => 'warning',
            $this->risk_score >= 20 => 'info',
            default => 'success'
        };
    }

    // Static methods
    public static function logDetection(array $data): self
    {
        return self::create([
            'ip_address' => $data['ip'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'detection_type' => $data['type'],
            'url_requested' => $data['url'] ?? request()->fullUrl(),
            'method' => $data['method'] ?? request()->method(),
            'request_data' => $data['request_data'] ?? request()->all(),
            'headers' => $data['headers'] ?? request()->headers->all(),
            'country_code' => $data['country_code'] ?? null,
            'is_blocked' => $data['is_blocked'] ?? true,
            'detection_details' => $data['details'] ?? null,
            'risk_score' => $data['risk_score'] ?? 50,
            'detected_at' => now()
        ]);
    }

    public static function getStats(int $hours = 24): array
    {
        $query = self::recent($hours);
        
        return [
            'total_detections' => $query->count(),
            'blocked_count' => $query->blocked()->count(),
            'unique_ips' => $query->distinct('ip_address')->count(),
            'by_type' => $query->selectRaw('detection_type, COUNT(*) as count')
                              ->groupBy('detection_type')
                              ->pluck('count', 'detection_type')
                              ->toArray(),
            'top_ips' => $query->selectRaw('ip_address, COUNT(*) as count')
                              ->groupBy('ip_address')
                              ->orderByDesc('count')
                              ->limit(10)
                              ->pluck('count', 'ip_address')
                              ->toArray(),
            'hourly_distribution' => $query->selectRaw('HOUR(detected_at) as hour, COUNT(*) as count')
                                          ->groupBy('hour')
                                          ->orderBy('hour')
                                          ->pluck('count', 'hour')
                                          ->toArray()
        ];
    }
}
