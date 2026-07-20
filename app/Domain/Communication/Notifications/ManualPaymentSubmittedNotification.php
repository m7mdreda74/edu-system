<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Payment\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ManualPaymentSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'إيصال تحويل جديد يحتاج مراجعة',
            'message' => "رفع {$this->payment->user->name} إيصال دفع بمبلغ " . number_format($this->payment->amount / 100, 2) . ' ر.ق.',
            'link' => route('admin.payments', ['status' => Payment::STATUS_PENDING_VERIFICATION]),
            'payment_id' => $this->payment->id,
            'icon' => '🧾',
        ];
    }
}
