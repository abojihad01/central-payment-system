<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_gateway_id',
        'account_id',
        'name',
        'description',
        'credentials',
        'is_active',
        'is_sandbox',
        'successful_transactions',
        'failed_transactions',
        'total_amount',
        'last_used_at',
        'settings',
    ];

    protected $casts = [
        'credentials' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_sandbox' => 'boolean',
        'total_amount' => 'decimal:2',
        'last_used_at' => 'datetime',
    ];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeProduction($query)
    {
        return $query->where('is_sandbox', false);
    }

    public function scopeSandbox($query)
    {
        return $query->where('is_sandbox', true);
    }

    public function scopeOrderByUsage($query)
    {
        return $query->orderBy('successful_transactions', 'asc');
    }

    public function scopeUnused($query)
    {
        return $query->where('successful_transactions', 0)->where('failed_transactions', 0);
    }

    public function getTotalTransactionsAttribute(): int
    {
        return $this->successful_transactions + $this->failed_transactions;
    }

    public function getSuccessRateAttribute(): float
    {
        $total = $this->total_transactions;
        return $total > 0 ? ($this->successful_transactions / $total) * 100 : 0;
    }

    public function incrementSuccessfulTransaction(float $amount = 0): void
    {
        $this->increment('successful_transactions');
        $this->increment('total_amount', $amount);
        $this->update(['last_used_at' => now()]);
    }

    public function incrementFailedTransaction(): void
    {
        $this->increment('failed_transactions');
        $this->update(['last_used_at' => now()]);
    }

    public function getCredential(string $key): mixed
    {
        return $this->credentials[$key] ?? null;
    }

    public function setCredential(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->update(['credentials' => $credentials]);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->update(['settings' => $settings]);
    }
}