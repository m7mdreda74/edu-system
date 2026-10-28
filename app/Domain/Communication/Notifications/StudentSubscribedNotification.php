<?php

declare(strict_types=1);

namespace App\Domain\Communication\Notifications;

use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Tells a teacher that a student subscribed to one of their groups or private schedules. */
class StudentSubscribedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const DAY_NAMES = [
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    public function __construct(
        public Subscription $subscription,
        public User $student,
        public ?string $customSchedule = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->subscription->loadMissing([
            'teachingGroup.schedules',
            'teachingGroup.assignment.subject',
            'teachingGroup.assignment.gradeLevel',
            'assignment.subject',
            'assignment.gradeLevel',
        ]);

        $group = $this->subscription->teachingGroup;
        $subjectName = $this->subscription->assignment?->subject?->name
            ?? $group?->assignment?->subject?->name
            ?? 'المادة الدراسية';

        $isPrivate = (bool) (
            $this->subscription->type === Subscription::TYPE_PRIVATE
            || $group?->is_private
            || $group?->capacity === 1
        );

        $scheduleText = $this->customSchedule ?: $this->formatScheduleText($group);

        if ($isPrivate) {
            $title = 'اشتراك موعد برايفت جديد 🎯';
            $message = "اشترك معك الطالب '{$this->student->name}' في حصة خاصة (برايفت 1-on-1) لمادة {$subjectName}"
                . ($scheduleText ? " - موعد {$scheduleText}" : '') . '.';
        } else {
            $groupName = $group?->name ?? 'المجموعة';
            $title = 'اشتراك جديد في المجموعة 👥';
            $message = "اشترك معك الطالب '{$this->student->name}' في مجموعة '{$groupName}' لمادة {$subjectName}"
                . ($scheduleText ? " - موعد {$scheduleText}" : '') . '.';
        }

        return [
            'title' => $title,
            'message' => $message,
            'link' => route('teacher.dashboard'),
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'subscription_id' => $this->subscription->id,
            'type' => $isPrivate ? 'private' : 'group',
            'schedule' => $scheduleText,
        ];
    }

    private function formatScheduleText(?object $group): string
    {
        if (! $group || ! $group->relationLoaded('schedules') || $group->schedules->isEmpty()) {
            return '';
        }

        $schedules = $group->schedules->sortBy('day_of_week');

        // Check if all schedules share the exact same start_time
        $distinctTimes = $schedules->pluck('start_time')->unique();

        if ($distinctTimes->count() === 1) {
            $days = $schedules->map(fn ($s) => self::DAY_NAMES[(int) $s->day_of_week] ?? '')->filter()->implode(' و ');
            $time12 = TeachingGroupSchedule::formatTime12($distinctTimes->first());
            return "{$days} الساعة {$time12}";
        }

        return $schedules->map(function ($s) {
            $day = self::DAY_NAMES[(int) $s->day_of_week] ?? '';
            $time12 = TeachingGroupSchedule::formatTime12($s->start_time);
            return "{$day} الساعة {$time12}";
        })->implode(' و ');
    }
}
