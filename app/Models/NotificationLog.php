<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'recipient_email',
        'recipient_phone',
        'channel',
        'status',
        'retry_count',
        'sent_at',
        'failed_at',
        'error_message',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'retry_count' => 'integer'
    ];
}