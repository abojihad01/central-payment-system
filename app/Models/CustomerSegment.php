<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'criteria',
        'is_active',
        'customer_count',
        'created_by'
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Scope for active segments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Update customer count for this segment
     */
    public function updateCustomerCount(): void
    {
        $count = $this->calculateCustomerCount();
        $this->update(['customer_count' => $count]);
    }

    /**
     * Calculate how many customers match this segment criteria
     */
    private function calculateCustomerCount(): int
    {
        $query = Customer::query();

        foreach ($this->criteria as $criterion) {
            $field = $criterion['field'] ?? null;
            $operator = $criterion['operator'] ?? '=';
            $value = $criterion['value'] ?? null;

            if ($field && $value !== null) {
                $query->where($field, $operator, $value);
            }
        }

        return $query->count();
    }
}