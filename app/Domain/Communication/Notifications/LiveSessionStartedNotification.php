<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LiveSessionStartedNotification extends Notification
{
    use Queueable;

    public function __construct(public LiveSession $session) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->session->loadMissing('teachingGroup.assignment.subject');

        $context = $this->session->teachingGroup?->assignment?->subject?->name
            ?? ($this->session->isPrivate() ? 'حصة خاصة' : 'حصة مباشرة');

        return [
            'title'   => 'بدأت حصة مباشرة الآن 🔴',
            'message' => "بدأت الحصة '{$this->session->title}' — {$context}.",
            'link'    => route('live-sessions.room', $this->session->id),
        ];
    }
}
