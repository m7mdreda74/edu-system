<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Payment\Models\TeacherPayout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayoutStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public TeacherPayout $payout) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'تم تحويل مستحقاتك المالية',
            'message' => 'سجلت الإدارة تحويل '.number_format(($this->payout->amount ?? 0) / 100, 2).' ر.ق. لك.',
            'link' => route('teacher.dashboard'),
            'payout_id' => $this->payout->id,
            'icon' => '💰',
        ];
    }
}
