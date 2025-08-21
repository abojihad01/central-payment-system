<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_pack_id',
        'name'
    ];

    protected $casts = [
        'api_pack_id' => 'integer'
    ];

    /**
     * Get the devices using this package
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'pack_id', 'api_pack_id');
    }

    /**
     * Find or create package from API data
     */
    public static function findOrCreateFromApi(int $apiPackId, string $name): self
    {
        return self::firstOrCreate(
            ['api_pack_id' => $apiPackId],
            ['name' => $name]
        );
    }
}
