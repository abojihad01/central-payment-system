<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentScheduleSettings extends Model
{
    protected $fillable = [
        'name',
        'schedule_type',
        'interval_minutes',
        'min_age_minutes',
        'max_age_minutes',
        'batch_limit',
        'is_active',
        'command',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'interval_minutes' => 'integer',
        'min_age_minutes' => 'integer',
        'max_age_minutes' => 'integer',
        'batch_limit' => 'integer',
    ];

    public function getFormattedCommandAttribute(): string
    {
        return str_replace([
            '{min_age}',
            '{max_age}',
            '{limit}'
        ], [
            $this->min_age_minutes,
            $this->max_age_minutes,
            $this->batch_limit
        ], $this->command);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('schedule_type', $type);
    }
}
