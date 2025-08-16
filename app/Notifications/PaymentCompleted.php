<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تم استلام دفعتك - ' . config('app.name'))
            ->greeting('مرحباً ' . $this->payment->customer_email)
            ->line('تم استلام دفعتك بنجاح بقيمة: ' . $this->payment->amount . ' ' . $this->payment->currency)
            ->line('رقم المعاملة: ' . $this->payment->gateway_payment_id)
            ->line('طريقة الدفع: ' . ucfirst($this->payment->payment_gateway))
            ->line('تاريخ الدفع: ' . $this->payment->confirmed_at->format('Y-m-d H:i'))
            ->line('شكراً لثقتك بخدماتنا!')
            ->action('عرض الإيصال', url('/payments/' . $this->payment->id));
    }

    public function toArray($notifiable)
    {
        return [
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'gateway' => $this->payment->payment_gateway,
            'message' => 'تم استلام دفعتك بنجاح بقيمة: ' . $this->payment->amount . ' ' . $this->payment->currency,
            'type' => 'payment_completed'
        ];
    }
}