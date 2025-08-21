<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerInfo extends Model
{
    use HasFactory;

    protected $table = 'reseller_info';

    protected $fillable = [
        'username',
        'credits',
        'enabled',
        'api_key',
        'last_fetched'
    ];

    protected $casts = [
        'credits' => 'integer',
        'enabled' => 'boolean',
        'last_fetched' => 'datetime'
    ];

    /**
     * Get the active reseller
     */
    public static function getActive(): ?self
    {
        return self::where('enabled', true)->first();
    }

    /**
     * Check if reseller has enough credits
     */
    public function hasCredits(int $required = 1): bool
    {
        return $this->credits >= $required;
    }

    /**
     * Decrement credits after usage
     */
    public function useCredits(int $amount = 1): bool
    {
        if (!$this->hasCredits($amount)) {
            return false;
        }

        $this->decrement('credits', $amount);
        return true;
    }

    /**
     * Update reseller info from API response
     */
    public function updateFromApi(array $data): void
    {
        $this->update([
            'credits' => $data['credits'] ?? $this->credits,
            'last_fetched' => now()
        ]);
    }
}
