<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public $subscription;

    /**
     * Create a new notification instance.
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $daysRemaining = now()->diffInDays($this->subscription->expires_at);
        
        return (new MailMessage)
                    ->subject('اشتراكك على وشك الانتهاء - ' . config('app.name'))
                    ->greeting('مرحباً ' . $this->subscription->customer_email)
                    ->line('اشتراكك في خطة: ' . $this->subscription->plan->name . ' على وشك الانتهاء.')
                    ->line('المتبقي: ' . $daysRemaining . ' أيام')
                    ->line('تاريخ انتهاء الصلاحية: ' . $this->subscription->expires_at->format('Y-m-d H:i'))
                    ->action('تجديد الاشتراك', url('/subscriptions/' . $this->subscription->id . '/renew'))
                    ->line('لا تفوت الاستمتاع بخدماتنا!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'message' => 'اشتراكك في خطة: ' . $this->subscription->plan->name . ' على وشك الانتهاء',
            'type' => 'subscription_expiring',
            'expires_at' => $this->subscription->expires_at->toDateTimeString(),
            'days_remaining' => now()->diffInDays($this->subscription->expires_at)
        ];
    }
}