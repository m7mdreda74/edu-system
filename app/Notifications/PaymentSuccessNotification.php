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
        $label     = $this->subscriptionLabel();

        return (new MailMessage)
            ->subject('✅ تأكيد الدفع — منصة التفوق')
            ->greeting("شكرًا {$notifiable->name}!")
            ->line("تمت عملية الدفع بنجاح بمبلغ **{$amountQAR} ريال قطري**.")
            ->line("الاشتراك: **{$label}**")
            ->line("رقم المرجع: `{$this->payment->gateway_ref}`")
            ->action('حصصي', route('student.my-classes'))
            ->salutation('فريق منصة التفوق');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'payment_success',
            'title'      => '✅ تم الدفع بنجاح',
            'message'    => "دفعت بنجاح لاشتراك: {$this->subscriptionLabel()}",
            'payment_id' => $this->payment->id,
            'amount'     => $this->payment->amount,
            'icon'       => '💳',
        ];
    }

    private function subscriptionLabel(): string
    {
        $this->payment->loadMissing('subscription');

        return $this->payment->subscription?->label() ?? 'اشتراك شهري';
    }
}
