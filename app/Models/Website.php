<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'domain',
        'language',
        'logo',
        'success_url',
        'failure_url',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function generatedLinks(): HasMany
    {
        return $this->hasMany(GeneratedLink::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
