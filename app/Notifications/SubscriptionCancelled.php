<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCancelled extends Notification
{
    use Queueable;

    public $subscription;
    public $cancellationType;

    public function __construct($subscription, $cancellationType = 'immediate')
    {
        $this->subscription = $subscription;
        $this->cancellationType = $cancellationType;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('تم إلغاء اشتراكك - ' . config('app.name'))
                    ->greeting('مرحباً ' . $this->subscription->customer_email)
                    ->line('تم إلغاء اشتراكك بنجاح.')
                    ->line('سبب الإلغاء: ' . ($this->subscription->cancellation_reason ?? 'غير محدد'))
                    ->line('شكراً لك على استخدام خدماتنا.')
                    ->action('زيارة الموقع', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'message' => 'تم إلغاء الاشتراك'
        ];
    }
}