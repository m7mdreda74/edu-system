<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Tells admins that money landed for a subscription. */
class PaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public User $student,
        public int $amountInSmallestUnit,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $qar = number_format($this->amountInSmallestUnit / 100, 2);

        return [
            'title'   => 'مدفوعات جديدة 💰',
            'message' => "تم تحصيل مبلغ {$qar} ر.ق. من الطالب '{$this->student->name}' لاشتراك {$this->subscription->label()}.",
            'link'    => route('admin.payments'),
        ];
    }
}
