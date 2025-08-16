<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FraudRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'priority',
        'conditions',
        'action',
        'risk_score_impact',
        'times_triggered',
        'false_positives',
        'accuracy_rate'
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'accuracy_rate' => 'decimal:2'
    ];

    /**
     * Scope for active rules only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered by priority
     */
    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Calculate accuracy rate
     */
    public function calculateAccuracyRate(): float
    {
        if ($this->times_triggered === 0) {
            return 0;
        }

        $truePositives = $this->times_triggered - $this->false_positives;
        return ($truePositives / $this->times_triggered) * 100;
    }

    /**
     * Mark as false positive
     */
    public function markFalsePositive(): void
    {
        $this->increment('false_positives');
        $this->update([
            'accuracy_rate' => $this->calculateAccuracyRate()
        ]);
    }
}