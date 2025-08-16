<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpired extends Notification implements ShouldQueue
{
    use Queueable;

    public $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('انتهت صلاحية اشتراكك - ' . config('app.name'))
            ->greeting('مرحباً ' . $this->subscription->customer_email)
            ->line('انتهت صلاحية اشتراكك في خطة: ' . $this->subscription->plan->name)
            ->line('تاريخ انتهاء الصلاحية: ' . $this->subscription->expires_at->format('Y-m-d H:i'))
            ->line('لمتابعة الخدمة، يرجى تجديد اشتراكك.')
            ->action('تجديد الاشتراك', url('/renew/' . $this->subscription->id));
    }

    public function toArray($notifiable)
    {
        return [
            'subscription_id' => $this->subscription->id,
            'message' => 'انتهت صلاحية اشتراكك في خطة: ' . $this->subscription->plan->name,
            'type' => 'subscription_expired',
            'expired_at' => $this->subscription->expires_at->toDateTimeString()
        ];
    }
}