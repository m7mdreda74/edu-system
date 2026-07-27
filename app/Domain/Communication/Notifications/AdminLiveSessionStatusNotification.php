<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminLiveSessionStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public LiveSession $session,
        public string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $teacher = $this->session->teacher?->name ?? 'مدرس';
        $subject = $this->session->teachingGroup?->assignment?->subject?->name;
        $location = $this->session->teachingGroup?->name ?? 'حصة برايفيت';
        $lesson = $subject ? ' مادة '.$subject : '';
        $students = $this->session->attendees
            ->pluck('user_id')
            ->unique()
            ->count();

        $message = $this->status === LiveSession::STATUS_LIVE
            ? "بدأ المدرس {$teacher} حصة{$lesson} مع {$location}."
            : "أنهى المدرس {$teacher} حصة{$lesson} مع {$location} بحضور {$students} طالب.";

        return [
            'title' => $this->status === LiveSession::STATUS_LIVE ? 'بدأت حصة مباشرة' : 'انتهت حصة مباشرة',
            'message' => $message,
            'link' => route('admin.reports'),
            'session_id' => $this->session->id,
            'status' => $this->status,
            'teacher_id' => $this->session->teacher_id,
            'students_count' => $students,
            'icon' => $this->status === LiveSession::STATUS_LIVE ? '▶️' : '✅',
        ];
    }
}
