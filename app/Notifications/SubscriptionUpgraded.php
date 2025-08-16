<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgraded extends Notification implements ShouldQueue
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
            ->subject('تم ترقية اشتراكك - ' . config('app.name'))
            ->greeting('مرحباً')
            ->line('تم ترقية اشتراكك بنجاح إلى الخطة الجديدة')
            ->line('الخطة الجديدة: ' . ($this->subscription->plan_data['name'] ?? 'خطة مميزة'))
            ->line('تاريخ التفعيل: ' . $this->subscription->updated_at->format('Y-m-d H:i'))
            ->line('صالح حتى: ' . $this->subscription->expires_at->format('Y-m-d'))
            ->action('عرض التفاصيل', url('/subscriptions/' . $this->subscription->id));
    }

    public function toArray($notifiable)
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan_data['name'] ?? 'خطة مميزة',
            'expires_at' => $this->subscription->expires_at,
            'message' => 'تم ترقية اشتراكك بنجاح',
            'type' => 'subscription_upgraded'
        ];
    }
}