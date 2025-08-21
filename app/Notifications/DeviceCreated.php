<?php

namespace App\Notifications;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeviceCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $device;

    /**
     * Create a new notification instance.
     */
    public function __construct(Device $device)
    {
        $this->device = $device;
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
        $credentials = $this->device->getFormattedCredentials();
        
        $message = (new MailMessage)
            ->subject('Your IPTV Device Has Been Created')
            ->greeting('Hello!')
            ->line('Your IPTV device has been successfully created and activated.')
            ->line('**Device Type:** ' . $this->device->type)
            ->line('**Expires:** ' . $this->device->expire_date->format('F j, Y'));

        // Add credentials based on device type
        if ($this->device->type === 'MAG') {
            $message->line('### Portal Access Information:')
                ->line('**MAC Address:** ' . ($credentials['MAC Address'] ?? 'N/A'))
                ->line('**Portal URL:** ' . ($credentials['Portal URL'] ?? 'N/A'))
                ->line('### Setup Instructions:')
                ->line('1. Open your MAG device settings')
                ->line('2. Navigate to System Settings > Servers')
                ->line('3. Enter the Portal URL provided above')
                ->line('4. Save and restart your device');
        } else {
            $message->line('### M3U Access Information:')
                ->line('**Username:** ' . ($credentials['Username'] ?? 'N/A'))
                ->line('**Password:** ' . ($credentials['Password'] ?? 'N/A'))
                ->line('**M3U URL:** ' . ($credentials['M3U URL'] ?? 'N/A'))
                ->line('### Setup Instructions:')
                ->line('1. Open your IPTV player (VLC, GSE Smart IPTV, etc.)')
                ->line('2. Add a new playlist using the M3U URL')
                ->line('3. Enter your username and password when prompted')
                ->action('Download M3U File', url('/devices/' . $this->device->id . '/download-m3u'));
        }

        $message->line('### Important Notes:')
            ->line('- Keep your credentials secure and do not share them')
            ->line('- Your subscription expires on ' . $this->device->expire_date->format('F j, Y'))
            ->line('- You will receive a reminder 3 days before expiration')
            ->action('View Device Details', url('/devices/' . $this->device->id))
            ->line('Thank you for your subscription!');

        return $message;
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
            'message' => 'Your ' . $this->device->type . ' device has been created'
        ];
    }
}
