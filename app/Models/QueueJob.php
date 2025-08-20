<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    protected $table = 'jobs';
    
    protected $fillable = [
        'queue',
        'payload',
        'attempts',
        'reserved_at',
        'available_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'reserved_at' => 'timestamp',
        'available_at' => 'timestamp',
        'created_at' => 'timestamp',
    ];

    public $timestamps = false;

    public function getJobTypeAttribute(): string
    {
        if (is_array($this->payload)) {
            return $this->payload['displayName'] ?? 'Unknown';
        }
        
        $payload = json_decode($this->payload, true);
        return $payload['displayName'] ?? 'Unknown';
    }

    public function getAvailableAtFormattedAttribute(): string
    {
        if (!$this->available_at) {
            return 'Now';
        }
        
        $date = \Carbon\Carbon::createFromTimestamp($this->available_at);
        return $date->diffForHumans();
    }

    public function getCreatedAtFormattedAttribute(): string
    {
        if (!$this->created_at) {
            return 'Unknown';
        }
        
        $date = \Carbon\Carbon::createFromTimestamp($this->created_at);
        return $date->diffForHumans();
    }

    public function getIsReservedAttribute(): bool
    {
        return !is_null($this->reserved_at);
    }

    public function getIsPendingAttribute(): bool
    {
        return is_null($this->reserved_at);
    }

    public function getHasFailedAttemptsAttribute(): bool
    {
        return $this->attempts > 0;
    }
}