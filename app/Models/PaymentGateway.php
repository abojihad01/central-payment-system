<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'logo_url',
        'is_active',
        'priority',
        'supported_currencies',
        'supported_countries',
        'configuration',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supported_currencies' => 'array',
        'supported_countries' => 'array',
        'configuration' => 'array',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function activeAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class)->where('is_active', true);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_gateway', 'name');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function supportsCurrency(string $currency): bool
    {
        return empty($this->supported_currencies) || in_array($currency, $this->supported_currencies);
    }

    public function supportsCountry(string $country): bool
    {
        return empty($this->supported_countries) || in_array($country, $this->supported_countries);
    }

    public function getTotalTransactionsAttribute(): int
    {
        return $this->accounts->sum(function ($account) {
            return $account->successful_transactions + $account->failed_transactions;
        });
    }

    public function getSuccessfulTransactionsAttribute(): int
    {
        return $this->accounts->sum('successful_transactions');
    }

    public function getFailedTransactionsAttribute(): int
    {
        return $this->accounts->sum('failed_transactions');
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->accounts->sum('total_amount');
    }

    public function getSuccessRateAttribute(): float
    {
        $total = $this->total_transactions;
        return $total > 0 ? ($this->successful_transactions / $total) * 100 : 0;
    }
}