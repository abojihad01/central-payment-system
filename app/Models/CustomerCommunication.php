<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCommunication extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'type',
        'subject',
        'content',
        'status',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'metadata'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
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
     * Mark as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);
    }

    /**
     * Mark as opened
     */
    public function markAsOpened(): void
    {
        $this->update([
            'opened_at' => now()
        ]);
    }

    /**
     * Mark as clicked
     */
    public function markAsClicked(): void
    {
        $this->update([
            'clicked_at' => now()
        ]);
    }

    /**
     * Scope for specific communication type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for specific status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}