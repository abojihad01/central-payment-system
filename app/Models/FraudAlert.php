<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FraudAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_id',
        'alert_type',
        'severity',
        'title',
        'description',
        'payment_id',
        'email',
        'ip_address',
        'risk_score',
        'triggered_rules',
        'status',
        'investigated_by',
        'investigated_at',
        'resolution',
        'metadata'
    ];

    protected $casts = [
        'triggered_rules' => 'array',
        'investigated_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Generate unique alert ID
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->alert_id) {
                $model->alert_id = 'FRAUD-' . strtoupper(uniqid());
            }
        });
    }

    /**
     * Scope for unresolved alerts
     */
    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['open', 'investigating']);
    }

    /**
     * Scope for high severity alerts
     */
    public function scopeHighSeverity($query)
    {
        return $query->where('severity', 'high');
    }
}
