<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Course\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LiveSessionStartedNotification extends Notification
{
    use Queueable;

    public function __construct(public LiveSession $session)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'بدأت حصة مباشرة الآن 🔴',
            'message' => "بدأت الحصة المباشرة بعنوان '{$this->session->title}' لكورس '{$this->session->course->title}'.",
            'link'    => route('live-sessions.room', $this->session->id),
        ];
    }
}
