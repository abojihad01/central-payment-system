<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Payment extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'subscription_id',
        'original_payment_id',
        'generated_link_id',
        'payment_account_id',
        'plan_id',
        'payment_gateway',
        'gateway_payment_id',
        'gateway_session_id',
        'amount',
        'currency',
        'status',
        'customer_email',
        'customer_name',
        'customer_phone',
        'type',
        'is_renewal',
        'gateway_response',
        'paid_at',
        'confirmed_at',
        'notes',
        'retry_count',
        'retry_log',
        'failure_reason',
        'attempts'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'retry_log' => 'array',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'is_renewal' => 'boolean'
    ];

    public function generatedLink(): BelongsTo
    {
        return $this->belongsTo(GeneratedLink::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'original_payment_id');
    }

    public function refunds()
    {
        return $this->hasMany(Payment::class, 'original_payment_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled']);
    }

    /**
     * Send payment completion notification.
     */
    public function sendCompletionNotification()
    {
        $this->notify(new \App\Notifications\PaymentCompleted($this));
        
        // Log the notification
        \App\Models\NotificationLog::create([
            'type' => 'payment_completed',
            'recipient_email' => $this->customer_email,
            'recipient_type' => 'payment',
            'recipient_id' => $this->id,
            'channel' => 'mail',
            'status' => 'sent',
            'data' => ['payment_id' => $this->id],
            'sent_at' => now()
        ]);
        
        // Also notify admins if configured
        if ($adminUsers = \App\Models\User::where('role', 'admin')->get()) {
            \Notification::send($adminUsers, new \App\Notifications\PaymentCompleted($this));
        }
    }

    /**
     * Send payment failure notification.
     */
    public function sendFailureNotification()
    {
        $this->notify(new \App\Notifications\PaymentFailed($this));
        
        // Also notify admins with AdminPaymentAlert
        if ($adminUsers = \App\Models\User::where('role', 'admin')->get()) {
            \Notification::send($adminUsers, new \App\Notifications\AdminPaymentAlert($this, 'payment_failed'));
        }
        
        // Log the notification in database
        \App\Models\NotificationLog::create([
            'type' => 'payment_failed',
            'recipient_email' => $this->customer_email,
            'recipient_type' => 'payment',
            'recipient_id' => $this->id,
            'channel' => 'mail',
            'status' => 'sent',
            'data' => [
                'payment_id' => $this->id,
                'failure_reason' => $this->failure_reason,
                'amount' => $this->amount
            ],
            'sent_at' => now()
        ]);
    }

    /**
     * Send payment refund notification.
     */
    public function sendRefundNotification()
    {
        // Send refund notification
        \Illuminate\Support\Facades\Notification::send($this, new \App\Notifications\PaymentRefunded($this));
        \Log::info('Payment refund notification sent', ['payment_id' => $this->id]);
    }

    public function routeNotificationFor($driver)
    {
        if ($driver === 'mail') {
            return $this->customer_email;
        }
        
        if ($driver === 'database') {
            return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable');
        }

        return null;
    }
}
