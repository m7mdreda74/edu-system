<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SessionScheduleChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public LiveSession $session,
        public bool $isMakeup,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->isMakeup ? 'تم تحديد حصة تعويضية' : 'اعتذار عن حصة',
            'message' => $this->isMakeup
                ? 'تم تحديد موعد تعويضي لحصة '.$this->session->title.' في '.$this->session->scheduled_at?->timezone('Asia/Qatar')->format('Y-m-d H:i').'.'
                : 'اعتذر المدرس عن حصة '.$this->session->title.'. السبب: '.$this->reason,
            'link' => route('dashboard'),
            'live_session_id' => $this->session->id,
            'icon' => $this->isMakeup ? '✅' : '📅',
        ];
    }
}
