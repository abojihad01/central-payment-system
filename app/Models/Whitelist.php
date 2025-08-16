<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Whitelist extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'value',
        'reason',
        'is_active',
        'expires_at',
        'added_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime'
    ];

    /**
     * Scope for active whitelist entries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Check if whitelist entry is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if value is whitelisted
     */
    public static function isWhitelisted(string $type, string $value): bool
    {
        return self::active()
            ->where('type', $type)
            ->where('value', $value)
            ->exists();
    }
}
