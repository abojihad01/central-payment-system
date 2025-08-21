<?php

namespace App\Notifications;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeviceExpiringReminder extends Notification implements ShouldQueue
{
    use Queueable;

    protected $device;
    protected $daysRemaining;

    /**
     * Create a new notification instance.
     */
    public function __construct(Device $device)
    {
        $this->device = $device;
        $this->daysRemaining = $device->expire_date->diffInDays(now());
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your IPTV Subscription is Expiring Soon')
            ->greeting('Important Reminder!')
            ->line('Your IPTV subscription for device **' . $this->device->type . '** is expiring in **' . $this->daysRemaining . ' days**.')
            ->line('**Expiration Date:** ' . $this->device->expire_date->format('F j, Y'))
            ->line('To avoid service interruption, please renew your subscription before it expires.')
            ->action('Renew Now', url('/devices/' . $this->device->id . '/renew'))
            ->line('### Device Information:')
            ->line('- **Type:** ' . $this->device->type)
            ->line('- **Created:** ' . $this->device->created_at->format('F j, Y'))
            ->line('- **Duration:** ' . $this->device->sub_duration . ' months')
            ->line('If you have any questions or need assistance, please contact our support team.')
            ->salutation('Best regards, IPTV Service Team');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        return [
            'device_id' => $this->device->id,
            'type' => $this->device->type,
            'expire_date' => $this->device->expire_date->toISOString(),
            'days_remaining' => $this->daysRemaining,
            'message' => 'Your ' . $this->device->type . ' device expires in ' . $this->daysRemaining . ' days'
        ];
    }
}
