<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Payment\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the student after a successful payment.
 * Carries invoice details for financial records.
 */
class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Payment $payment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amountQAR = number_format($this->payment->getAmountInMainUnit(), 2);

        return (new MailMessage)
            ->subject('✅ تأكيد الدفع — منصة التفوق')
            ->greeting("شكرًا {$notifiable->name}!")
            ->line("تمت عملية الدفع بنجاح بمبلغ **{$amountQAR} ريال قطري**.")
            ->line("الكورس: **{$this->payment->course->title}**")
            ->line("رقم المرجع: `{$this->payment->gateway_ref}`")
            ->action('مشاهدة الكورس', route('student.learn', ['slug' => $this->payment->course->slug]))
            ->salutation('فريق منصة التفوق');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'payment_success',
            'title'      => '✅ تم الدفع بنجاح',
            'message'    => "دفعت بنجاح لكورس: {$this->payment->course->title}",
            'payment_id' => $this->payment->id,
            'amount'     => $this->payment->amount,
            'icon'       => '💳',
        ];
    }
}
