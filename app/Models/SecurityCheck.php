<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_type',
        'check_name',
        'description',
        'score',
        'status',
        'priority',
        'frequency',
        'last_checked'
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'last_checked' => 'datetime'
    ];
}