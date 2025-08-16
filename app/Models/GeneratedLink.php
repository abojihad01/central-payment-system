<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class GeneratedLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'plan_id',
        'token',
        'success_url',
        'failure_url',
        'price',
        'currency',
        'expires_at',
        'single_use',
        'is_used',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'expires_at' => 'datetime',
        'single_use' => 'boolean',
        'is_used' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired() && (!$this->single_use || !$this->is_used);
    }

    public function getPaymentLinkAttribute(): string
    {
        $jwt = $this->generateJWT();
        return config('app.url') . '/checkout?token=' . $jwt;
    }

    private function generateJWT(): string
    {
        $payload = [
            'iss' => config('app.url'),
            'aud' => 'payment-system',
            'iat' => now()->timestamp,
            'exp' => $this->expires_at ? $this->expires_at->timestamp : now()->addDays(30)->timestamp,
            'data' => [
                'link_id' => $this->id,
                'website_id' => $this->website_id,
                'plan_id' => $this->plan_id,
                'price' => $this->price,
                'currency' => $this->currency,
                'success_url' => $this->success_url,
                'failure_url' => $this->failure_url,
                'website_name' => $this->website->name,
                'website_language' => $this->website->language,
                'website_logo' => $this->website->logo,
                'plan_name' => $this->plan->name,
                'plan_description' => $this->plan->description,
                'plan_features' => $this->plan->features,
            ]
        ];

        return \Firebase\JWT\JWT::encode($payload, config('app.jwt_secret', config('app.key')), 'HS256');
    }
}
