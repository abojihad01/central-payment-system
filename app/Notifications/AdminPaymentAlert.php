<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Payment;

class AdminPaymentAlert extends Notification
{
    use Queueable;

    public $payment;
    public $alertType;
    public $data;

    /**
     * Create a new notification instance.
     */
    public function __construct($paymentOrData, string $alertType = null)
    {
        if ($paymentOrData instanceof Payment) {
            $this->payment = $paymentOrData;
            $this->alertType = $alertType;
        } else {
            // For non-payment alerts like subscription expiration
            $this->data = $paymentOrData;
            $this->alertType = $paymentOrData['alert_type'] ?? $alertType;
        }
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
        $subject = 'Admin Alert: ' . ucfirst(str_replace('_', ' ', $this->alertType));
        
        $message = (new MailMessage)->subject($subject);
        
        if ($this->payment) {
            // Payment-related alert
            $message->line('A payment event requires your attention.')
                    ->line('Alert Type: ' . ucfirst(str_replace('_', ' ', $this->alertType)))
                    ->line('Payment ID: ' . $this->payment->id)
                    ->line('Customer: ' . $this->payment->customer_email)
                    ->line('Amount: ' . $this->payment->currency . ' ' . $this->payment->amount);
            
            if ($this->alertType === 'payment_failed') {
                $message->line('Failure Reason: ' . ($this->payment->failure_reason ?? 'Unknown error'));
            }
            
            $message->action('View Payment', url('/admin/payments/' . $this->payment->id))
                    ->line('Please review this payment and take appropriate action.');
        } else {
            // General alert (like subscription expiration)
            $message->line('A system event requires your attention.')
                    ->line('Alert Type: ' . ucfirst(str_replace('_', ' ', $this->alertType)));
            
            if (isset($this->data['message'])) {
                $message->line($this->data['message']);
            }
            
            if (isset($this->data['count'])) {
                $message->line('Count: ' . $this->data['count']);
            }
            
            $message->action('View Dashboard', url('/admin'))
                    ->line('Please review the dashboard for more details.');
        }
        
        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        if ($this->payment) {
            return [
                'payment_id' => $this->payment->id,
                'alert_type' => $this->alertType,
                'customer_email' => $this->payment->customer_email,
                'amount' => $this->payment->amount,
                'currency' => $this->payment->currency
            ];
        } else {
            return array_merge([
                'alert_type' => $this->alertType,
            ], $this->data ?? []);
        }
    }
}