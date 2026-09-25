<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Payment\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Informs the student and linked parents that a transfer receipt was rejected. */
class PaymentRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_receipt_rejected',
            'title' => 'تم رفض إيصال التحويل',
            'message' => "تم رفض إيصال تحويل اشتراكك. السبب: {$this->reason} يمكنك رفع إيصال جديد من صفحة الاشتراك.",
            'link' => route('checkout.show', $this->payment->subscription_id),
            'payment_id' => $this->payment->id,
            'subscription_id' => $this->payment->subscription_id,
            'icon' => '⚠️',
        ];
    }
}
