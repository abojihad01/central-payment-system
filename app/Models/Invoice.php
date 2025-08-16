<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'subscription_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'type',
        'issued_at',
        'due_at',
        'paid_at',
        'line_items',
        'customer_email',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'line_items' => 'array',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime'
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}