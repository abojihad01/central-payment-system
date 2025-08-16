<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'event_type',
        'description',
        'metadata',
        'related_payment_id',
        'related_plan_id',
        'event_source',
        'triggered_by'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Create a subscription event.
     */
    public static function createEvent(
        int $subscriptionId, 
        string $eventType, 
        string $description, 
        array $eventData = []
    ): self {
        return static::create([
            'subscription_id' => $subscriptionId,
            'event_type' => $eventType,
            'description' => $description,
            'metadata' => $eventData,
            'event_source' => 'system'
        ]);
    }
}