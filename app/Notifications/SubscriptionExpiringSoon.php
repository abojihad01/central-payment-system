<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public $subscription;
    public $daysLeft;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
        $this->daysLeft = now()->diffInDays($subscription->expires_at);
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('اشتراكك ينتهي قريباً - ' . config('app.name'))
            ->greeting('مرحباً ' . $this->subscription->customer_email)
            ->line('اشتراكك في خطة: ' . $this->subscription->plan->name . ' ينتهي خلال ' . $this->daysLeft . ' أيام')
            ->line('تاريخ انتهاء الصلاحية: ' . $this->subscription->expires_at->format('Y-m-d H:i'))
            ->line('لتجنب انقطاع الخدمة، يرجى تجديد اشتراكك الآن.')
            ->action('تجديد الاشتراك', url('/renew/' . $this->subscription->id));
    }

    public function toArray($notifiable)
    {
        return [
            'subscription_id' => $this->subscription->id,
            'message' => 'اشتراكك ينتهي خلال ' . $this->daysLeft . ' أيام',
            'type' => 'subscription_expiring_soon',
            'expires_at' => $this->subscription->expires_at->toDateTimeString(),
            'days_left' => $this->daysLeft
        ];
    }
}