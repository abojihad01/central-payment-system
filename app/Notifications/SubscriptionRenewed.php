<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewed extends Notification
{
    use Queueable;

    public $subscription;
    public $renewalPayment;

    public function __construct($subscription, $renewalPayment = null)
    {
        $this->subscription = $subscription;
        $this->renewalPayment = $renewalPayment;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('تم تجديد اشتراكك - ' . config('app.name'))
                    ->greeting('مرحباً ' . $this->subscription->customer_email)
                    ->line('تم تجديد اشتراكك بنجاح.')
                    ->line('تاريخ انتهاء الاشتراك الجديد: ' . $this->subscription->expires_at->format('Y-m-d'))
                    ->line('شكراً لك على ثقتك بخدماتنا.')
                    ->action('زيارة الموقع', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'message' => 'تم تجديد الاشتراك'
        ];
    }
}