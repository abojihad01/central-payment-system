<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRefunded extends Notification
{
    use Queueable;

    public $payment;
    public $refundPayment;
    public $refundAmount;

    public function __construct($payment, $refundAmount = null)
    {
        $this->payment = $payment;
        $this->refundPayment = $payment; // For backward compatibility
        $this->refundAmount = $refundAmount ?? abs($payment->amount);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('تم استرداد دفعتك - ' . config('app.name'))
                    ->greeting('مرحباً ' . $this->payment->customer_email)
                    ->line('تم استرداد دفعتك بنجاح.')
                    ->line('مبلغ الاسترداد: ' . $this->payment->currency . ' ' . $this->refundAmount)
                    ->line('معرف الدفعة الأصلية: ' . ($this->payment->original_payment_id ?? $this->payment->id))
                    ->line('تاريخ الاسترداد: ' . now()->format('Y-m-d H:i:s'))
                    ->line('شكراً لك على استخدام خدماتنا.')
                    ->salutation('مع أطيب التحيات، فريق ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'refund_amount' => $this->refundAmount,
            'currency' => $this->payment->currency,
            'customer_email' => $this->payment->customer_email
        ];
    }
}