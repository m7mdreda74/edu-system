<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Learning\Models\LiveSession;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LiveSessionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly LiveSession $session,
        public readonly string $recipientRole = 'student',
        public readonly ?User $student = null,
        public readonly int $minutesBefore = 1440,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $timezone = $this->session->teachingGroup?->timezone
            ?? $this->session->privateSessionSlot?->timezone
            ?? config('app.timezone');
        $lessonAt = $this->session->scheduled_at?->copy()->timezone($timezone)->format('H:i');
        $subject = $this->session->teachingGroup?->assignment?->subject?->name
            ?? $this->session->privateSessionSlot?->assignment?->subject?->name;
        $subjectLabel = $subject ? ' في مادة '.$subject : '';

        if ($this->recipientRole === 'teacher') {
            return [
                'type' => 'live_session_reminder_teacher',
                'alert_key' => 'live_session_30m:teacher:'.$this->session->id.':'.$notifiable->getKey(),
                'title' => 'تذكير: موعد حصتك المباشرة بعد 30 دقيقة',
                'message' => "موعد حصتك المباشرة '{$this->session->title}'{$subjectLabel} يبدأ بعد 30 دقيقة في تمام الساعة {$lessonAt}. جهّز الغرفة واستعد لبدء الحصة.",
                'link' => route('live-sessions.room', $this->session->id),
                'live_session_id' => $this->session->id,
                'scheduled_at' => $this->session->scheduled_at?->toIso8601String(),
                'icon' => 'live',
            ];
        }

        if ($this->recipientRole === 'parent') {
            $studentName = $this->student?->name ?? 'ابنك/ابنتك';

            return [
                'type' => 'live_session_reminder_parent',
                'alert_key' => 'live_session_30m:parent:'.$this->session->id.':'.$notifiable->getKey().':'.($this->student?->id ?? '0'),
                'title' => "تذكير: حصة مباشرة قادمة لـ {$studentName}",
                'message' => "تبدأ حصة {$studentName} المباشرة '{$this->session->title}'{$subjectLabel} بعد 30 دقيقة (الساعة {$lessonAt}).",
                'link' => route('parent.dashboard'),
                'live_session_id' => $this->session->id,
                'student_id' => $this->student?->id,
                'scheduled_at' => $this->session->scheduled_at?->toIso8601String(),
                'icon' => 'calendar',
            ];
        }

        // Student recipient
        $is30m = $this->minutesBefore <= 30;

        return [
            'type' => 'live_session_reminder',
            'alert_key' => $is30m
                ? 'live_session_30m:student:'.$this->session->id.':'.$notifiable->getKey()
                : 'upcoming_class:'.$this->session->id.':'.$notifiable->getKey(),
            'title' => $is30m ? 'تذكير: موعد حصتك المباشرة بعد 30 دقيقة' : 'تذكير بحصتك القادمة',
            'message' => $is30m
                ? "متبقي 30 دقيقة على حصة '{$this->session->title}'{$subjectLabel} (الساعة {$lessonAt}). استعد وسيتفعل زر الدخول فور بدء المعلم للحصة."
                : 'متبقي أقل من 24 ساعة على حصة '.$this->session->title.$subjectLabel.'، وموعدها '.$this->session->scheduled_at?->copy()->timezone($timezone)->format('Y-m-d H:i').'.',
            'link' => route('student.schedule'),
            'live_session_id' => $this->session->id,
            'scheduled_at' => $this->session->scheduled_at?->toIso8601String(),
            'icon' => 'calendar',
        ];
    }
}
