<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LiveSessionReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly LiveSession $session) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $timezone = $this->session->teachingGroup?->timezone
            ?? $this->session->privateSessionSlot?->timezone
            ?? config('app.timezone');
        $lessonAt = $this->session->scheduled_at?->copy()->timezone($timezone)->format('Y-m-d H:i');
        $subject = $this->session->teachingGroup?->assignment?->subject?->name
            ?? $this->session->privateSessionSlot?->assignment?->subject?->name;
        $subjectLabel = $subject ? ' في مادة '.$subject : '';

        return [
            'type' => 'live_session_reminder',
            'title' => 'تذكير بحصتك القادمة',
            'message' => 'متبقي أقل من 24 ساعة على حصة '.$this->session->title.$subjectLabel.'، وموعدها '.$lessonAt.'.',
            'link' => route('student.schedule'),
            'live_session_id' => $this->session->id,
            'scheduled_at' => $this->session->scheduled_at?->toIso8601String(),
            'icon' => 'calendar',
        ];
    }
}
