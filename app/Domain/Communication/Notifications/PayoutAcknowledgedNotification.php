<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Payment\Models\TeacherPayout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayoutAcknowledgedNotification extends Notification
{
    use Queueable;

    public function __construct(public TeacherPayout $payout) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'المدرس أكد استلام التصفية',
            'message' => "أكد {$this->payout->teacher->name} استلام " . number_format($this->payout->amount / 100, 2) . ' ر.ق.',
            'link' => route('admin.payouts'),
            'payout_id' => $this->payout->id,
            'icon' => '✅',
        ];
    }
}
