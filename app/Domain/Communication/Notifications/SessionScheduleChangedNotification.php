<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SessionScheduleChangedNotification extends Notification implements ShouldQueue
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
        $sessionTitle = trim((string) $this->session->title);
        $formattedTitle = preg_match('/^حصة\b/u', $sessionTitle) ? $sessionTitle : 'حصة '.$sessionTitle;

        return [
            'title' => $this->isMakeup ? 'تم تحديد حصة تعويضية' : 'اعتذار عن الحصة',
            'message' => $this->isMakeup
                ? 'تم تحديد موعد تعويضي لـ '.$formattedTitle.' في '.$this->session->scheduled_at?->timezone('Asia/Qatar')->format('Y-m-d H:i').'.'
                : 'اعتذر المدرس عن '.$formattedTitle.'. السبب: '.$this->reason,
            'link' => route('dashboard'),
            'live_session_id' => $this->session->id,
            'icon' => $this->isMakeup ? '✅' : '📅',
        ];
    }
}
