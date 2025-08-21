<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'type',
        'credentials',
        'pack_id',
        'sub_duration',
        'notes',
        'country',
        'api_user_id',
        'url',
        'status',
        'expire_date'
    ];

    protected $casts = [
        'credentials' => 'array',
        'expire_date' => 'date',
        'sub_duration' => 'integer',
        'pack_id' => 'integer',
        'api_user_id' => 'integer'
    ];

    /**
     * Get the customer that owns the device
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the package for this device
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'pack_id', 'api_pack_id');
    }

    /**
     * Get the logs for this device
     */
    public function logs(): HasMany
    {
        return $this->hasMany(DeviceLog::class);
    }

    /**
     * Check if device is active
     */
    public function isActive(): bool
    {
        return $this->status === 'enable' && 
               $this->expire_date && 
               $this->expire_date->isFuture();
    }

    /**
     * Check if device is expiring soon (within 3 days)
     */
    public function isExpiringSoon(): bool
    {
        if (!$this->expire_date) {
            return false;
        }
        
        return $this->expire_date->diffInDays(now()) <= 3 && 
               $this->expire_date->isFuture();
    }

    /**
     * Get formatted credentials for display
     */
    public function getFormattedCredentials(): array
    {
        if ($this->type === 'MAG') {
            return [
                'MAC Address' => $this->credentials['mac'] ?? 'N/A',
                'Portal URL' => $this->url ?? 'N/A'
            ];
        } elseif ($this->type === 'M3U') {
            return [
                'Username' => $this->credentials['username'] ?? 'N/A',
                'Password' => $this->credentials['password'] ?? 'N/A',
                'M3U URL' => $this->url ?? 'N/A'
            ];
        }

        return [];
    }

    /**
     * Log an action for this device
     */
    public function logAction(string $action, ?string $message = null, ?array $response = null): DeviceLog
    {
        return $this->logs()->create([
            'action' => $action,
            'message' => $message,
            'response' => $response
        ]);
    }
}
