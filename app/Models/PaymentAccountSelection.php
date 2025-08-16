<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAccountSelection extends Model
{
    protected $fillable = [
        'payment_id',
        'payment_account_id',
        'gateway_name',
        'selection_method',
        'selection_criteria',
        'available_accounts',
        'account_stats',
        'selection_reason',
        'selection_priority',
        'was_fallback',
        'previous_account_id',
        'selection_time_ms',
    ];

    protected $casts = [
        'selection_criteria' => 'array',
        'available_accounts' => 'array',
        'account_stats' => 'array',
        'was_fallback' => 'boolean',
        'selection_time_ms' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    // Scopes for admin filtering
    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('gateway_name', $gateway);
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('selection_method', $method);
    }

    public function scopeFallbacks($query)
    {
        return $query->where('was_fallback', true);
    }

    public function scopeRecentSelections($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}