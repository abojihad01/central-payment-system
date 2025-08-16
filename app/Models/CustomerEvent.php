<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'event_type',
        'description',
        'metadata',
        'source',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    /**
     * Customer relationship
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Create customer event
     */
    public static function createEvent(
        int $customerId,
        string $eventType,
        string $description,
        array $metadata = [],
        ?string $source = null,
        ?string $ipAddress = null
    ): self {
        return self::create([
            'customer_id' => $customerId,
            'event_type' => $eventType,
            'description' => $description,
            'metadata' => $metadata,
            'source' => $source ?? 'system',
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Scope for specific event types
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}