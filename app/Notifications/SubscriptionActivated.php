<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivated extends Notification implements ShouldQueue
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
            ->subject('تم تفعيل اشتراكك - ' . config('app.name'))
            ->greeting('مرحباً ' . $this->subscription->customer_email)
            ->line('تم تفعيل اشتراكك بنجاح في خطة: ' . $this->subscription->plan->name)
            ->line('تاريخ البداية: ' . $this->subscription->starts_at->format('Y-m-d H:i'))
            ->line('تاريخ انتهاء الصلاحية: ' . $this->subscription->expires_at->format('Y-m-d H:i'))
            ->line('شكراً لاشتراكك معنا!')
            ->action('عرض الاشتراك', url('/subscriptions/' . $this->subscription->id));
    }

    public function toArray($notifiable)
    {
        return [
            'subscription_id' => $this->subscription->id,
            'message' => 'تم تفعيل اشتراكك في خطة: ' . $this->subscription->plan->name,
            'type' => 'subscription_activated',
            'expires_at' => $this->subscription->expires_at->toDateTimeString()
        ];
    }
}