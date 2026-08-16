<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSession;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentLiveSessionActivityNotification extends Notification
{
    use Queueable;

    public const ACTIVITY_JOINED = 'joined';

    public const ACTIVITY_LEFT = 'left';

    public const ACTIVITY_ENDED = 'ended';

    public function __construct(
        public readonly LiveSession $session,
        public readonly User $student,
        public readonly string $activity,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->session->loadMissing([
            'teachingGroup.assignment.subject',
            'privateSessionSlot.assignment.subject',
        ]);

        $context = $this->session->teachingGroup?->assignment?->subject?->name
            ?? $this->session->privateSessionSlot?->assignment?->subject?->name
            ?? ($this->session->isPrivate() ? 'حصة خاصة' : 'حصة مباشرة');

        [$title, $message] = match ($this->activity) {
            self::ACTIVITY_JOINED => [
                'دخول الطالب إلى الحصة',
                "ابنكم الطالب {$this->student->name} دخل الحصة '{$this->session->title}' — {$context}.",
            ],
            self::ACTIVITY_LEFT => [
                'خروج الطالب من الحصة',
                "ابنكم الطالب {$this->student->name} خرج من الحصة '{$this->session->title}' — {$context}.",
            ],
            self::ACTIVITY_ENDED => [
                'انتهاء الحصة',
                "انتهت الحصة '{$this->session->title}' للطالب {$this->student->name} — {$context}.",
            ],
            default => [
                'نشاط جديد للطالب',
                "حدث نشاط جديد للطالب {$this->student->name} في الحصة '{$this->session->title}'.",
            ],
        };

        return [
            'type' => 'student_live_session_activity',
            'title' => $title,
            'message' => $message,
            'link' => route('parent.dashboard', ['student_id' => $this->student->id]),
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'live_session_id' => $this->session->id,
            'activity' => $this->activity,
            'icon' => 'video',
        ];
    }
}
