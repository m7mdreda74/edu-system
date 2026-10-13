<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Communication\Notifications\AcademicAlertNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AcademicAlertService
{
    public const ALERT_HOMEWORK_DEADLINE = 'homework_deadline';

    public const ALERT_MISSING_ASSIGNMENT = 'missing_assignment';

    public const ALERT_UPCOMING_CLASS = 'upcoming_class';

    public const ALERT_RECENT_ABSENCE = 'recent_absence';

    public const ALERT_DECLINING_ASSESSMENT = 'declining_assessment';

    public const ALERT_SUBSCRIPTION_EXPIRING = 'subscription_expiring';

    /**
     * Evaluate the configured on-platform academic alerts for active learners.
     *
     * The service intentionally sends only database notifications. External
     * push/SMS delivery remains a separate release concern, while this run is
     * safe to repeat because every recipient/event pair has a cooldown key.
     *
     * @return array{notifications_sent:int, alerts_evaluated:int, duplicates_skipped:int}
     */
    public function send(?Carbon $now = null): array
    {
        $now ??= now();
        $now = $now->copy();

        $stats = [
            'notifications_sent' => 0,
            'alerts_evaluated' => 0,
            'duplicates_skipped' => 0,
        ];

        $studentsByAssignment = $this->activeStudentsByAssignment();

        $this->sendHomeworkAlerts($studentsByAssignment, $now, $stats);
        $this->sendUpcomingClassAlerts($now, $stats);
        $this->sendRecentAbsenceAlerts($now, $stats);
        $this->sendDecliningAssessmentAlerts($studentsByAssignment, $now, $stats);
        $this->sendSubscriptionExpirationAlerts($now, $stats);

        return $stats;
    }

    public static function upcomingClassAlertKey(int $sessionId, int $studentId): string
    {
        return self::ALERT_UPCOMING_CLASS.':'.$sessionId.':'.$studentId;
    }

    public static function recentAbsenceAlertKey(int $sessionId, int $studentId): string
    {
        return self::ALERT_RECENT_ABSENCE.':'.$sessionId.':'.$studentId;
    }

    private function activeStudentsByAssignment(): Collection
    {
        return Subscription::query()
            ->active()
            ->with('student:id,name')
            ->whereNotNull('teaching_assignment_id')
            ->get()
            ->filter(fn (Subscription $subscription): bool => $subscription->student !== null)
            ->groupBy('teaching_assignment_id')
            ->map(fn (Collection $subscriptions): Collection => $subscriptions
                ->pluck('student')
                ->filter()
                ->unique('id')
                ->values());
    }

    private function sendHomeworkAlerts(Collection $studentsByAssignment, Carbon $now, array &$stats): void
    {
        $today = $now->copy()->startOfDay();
        $missingFrom = $today->copy()->subDays(max(1, (int) config('learning.academic_alerts.missing_assignment_days', 14)));
        $deadlineUntil = $today->copy()->addDays(max(0, (int) config('learning.academic_alerts.homework_deadline_days', 2)));
        $assignmentIds = $studentsByAssignment->keys()->filter()->values();

        $worksheets = Worksheet::query()
            ->where('type', Worksheet::TYPE_HOMEWORK)
            ->where('requires_submission', true)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$missingFrom->toDateString(), $deadlineUntil->toDateString()])
            ->whereHas('unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with(['unit.assignment.subject:id,name', 'material:id,title'])
            ->orderBy('due_date')
            ->get();

        if ($worksheets->isEmpty()) {
            return;
        }

        $studentIds = $studentsByAssignment
            ->values()
            ->flatten(1)
            ->pluck('id')
            ->unique()
            ->values();
        $submittedByWorksheet = WorksheetSubmission::query()
            ->whereIn('worksheet_id', $worksheets->pluck('id'))
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('submitted_at')
            ->get(['worksheet_id', 'student_id'])
            ->groupBy('worksheet_id');

        foreach ($worksheets as $worksheet) {
            $assignmentId = $worksheet->unit?->teaching_assignment_id;
            $students = $studentsByAssignment->get($assignmentId, collect());

            if ($students->isEmpty()) {
                continue;
            }

            $submittedStudentIds = $submittedByWorksheet
                ->get($worksheet->id, collect())
                ->pluck('student_id')
                ->flip();
            $isMissing = $worksheet->due_date->copy()->startOfDay()->isBefore($today);
            $alertType = $isMissing ? self::ALERT_MISSING_ASSIGNMENT : self::ALERT_HOMEWORK_DEADLINE;
            $dueDate = $worksheet->due_date->toDateString();
            $subject = $worksheet->unit?->assignment?->subject?->name;
            $subjectLabel = $subject ? " في مادة {$subject}" : '';

            foreach ($students as $student) {
                if ($submittedStudentIds->has($student->id)) {
                    continue;
                }

                $key = $alertType.':'.$worksheet->id.':'.$student->id.':'.$dueDate;
                $message = $isMissing
                    ? "لم يتم تسليم واجب {$worksheet->title}{$subjectLabel} وكان موعده {$dueDate}. راجع مركز الواجبات لإتمام المطلوب."
                    : "اقترب موعد تسليم واجب {$worksheet->title}{$subjectLabel} في {$dueDate}. افتح مركز الواجبات وخطط للتسليم في الوقت المناسب.";

                $this->notifyStudentAndParents(
                    $student,
                    [
                        'alert_type' => $alertType,
                        'title' => $isMissing ? 'واجب متأخر يحتاج متابعة' : 'موعد تسليم واجب قريب',
                        'message' => $message,
                        'link' => route('student.assignments'),
                        'parent_link' => route('parent.dashboard'),
                        'icon' => 'clipboard',
                        'student_id' => $student->id,
                        'entity_id' => $worksheet->id,
                    ],
                    $key,
                    $now,
                    $stats,
                );
            }
        }
    }

    private function sendUpcomingClassAlerts(Carbon $now, array &$stats): void
    {
        $until = $now->copy()->addHours(max(1, (int) config('learning.academic_alerts.upcoming_class_hours', 24)));

        LiveSession::query()
            ->where('status', LiveSession::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $until)
            ->with(['teachingGroup.assignment.subject:id,name', 'privateSessionSlot.assignment.subject:id,name'])
            ->orderBy('id')
            ->get()
            ->each(function (LiveSession $session) use ($now, &$stats): void {
                foreach ($this->studentsForSession($session) as $student) {
                    $when = $session->scheduled_at?->copy()
                        ->timezone($this->sessionTimezone($session))
                        ->format('Y-m-d H:i');
                    $subject = $session->teachingGroup?->assignment?->subject?->name
                        ?? $session->privateSessionSlot?->assignment?->subject?->name;
                    $subjectLabel = $subject ? " في مادة {$subject}" : '';

                    $this->notifyStudentAndParents(
                        $student,
                        [
                            'alert_type' => self::ALERT_UPCOMING_CLASS,
                            'title' => 'حصة قادمة',
                            'message' => "لديك حصة {$session->title}{$subjectLabel} في {$when}. جهّز نفسك وادخل من جدول الحصص.",
                            'link' => route('student.schedule'),
                            'parent_link' => route('parent.dashboard'),
                            'icon' => 'calendar',
                            'student_id' => $student->id,
                            'entity_id' => $session->id,
                        ],
                        self::upcomingClassAlertKey((int) $session->id, (int) $student->id),
                        $now,
                        $stats,
                    );
                }
            });
    }

    private function sendRecentAbsenceAlerts(Carbon $now, array &$stats): void
    {
        $from = $now->copy()->subDays(max(1, (int) config('learning.academic_alerts.recent_absence_days', 3)));

        LiveSession::query()
            ->where('status', LiveSession::STATUS_ENDED)
            ->whereNotNull('ended_at')
            ->whereBetween('scheduled_at', [$from, $now])
            ->with(['teachingGroup.assignment.subject:id,name', 'privateSessionSlot.assignment.subject:id,name'])
            ->orderBy('id')
            ->get()
            ->each(function (LiveSession $session) use ($now, &$stats): void {
                $students = $this->studentsForSession($session);
                if ($students->isEmpty()) {
                    return;
                }

                $attendedStudentIds = $session->attendees()
                    ->whereIn('user_id', $students->pluck('id'))
                    ->whereNotNull('joined_at')
                    ->pluck('user_id')
                    ->flip();

                foreach ($students as $student) {
                    if ($attendedStudentIds->has($student->id)) {
                        continue;
                    }

                    $this->notifyStudentAndParents(
                        $student,
                        [
                            'alert_type' => self::ALERT_RECENT_ABSENCE,
                            'title' => 'نحتاج متابعة غياب حديث',
                            'message' => "لم يظهر حضورك في الحصة {$session->title}. راجع جدولك، وإذا كان الغياب بعذر تواصل مع المعلم أو الإدارة.",
                            'link' => route('student.schedule'),
                            'parent_link' => route('parent.dashboard'),
                            'icon' => 'calendar',
                            'student_id' => $student->id,
                            'entity_id' => $session->id,
                        ],
                        self::recentAbsenceAlertKey((int) $session->id, (int) $student->id),
                        $now,
                        $stats,
                    );
                }
            });
    }

    private function sendDecliningAssessmentAlerts(Collection $studentsByAssignment, Carbon $now, array &$stats): void
    {
        $windowDays = max(14, (int) config('learning.academic_alerts.assessment_window_days', 60));
        $minimumAttempts = max(1, (int) config('learning.academic_alerts.assessment_min_attempts_per_period', 2));
        $declineThreshold = max(1, (int) config('learning.academic_alerts.assessment_decline_threshold', 10));
        $windowStart = $now->copy()->subDays($windowDays);
        $midpoint = $windowStart->copy()->addDays(intdiv($windowDays, 2));

        foreach ($studentsByAssignment as $assignmentId => $students) {
            $attempts = QuizAttempt::query()
                ->whereIn('user_id', $students->pluck('id'))
                ->whereNotNull('submitted_at')
                ->whereBetween('submitted_at', [$windowStart, $now])
                ->whereHas('quiz.unit', fn ($query) => $query
                    ->where('teaching_assignment_id', $assignmentId)
                    ->where('is_published', true))
                ->with('quiz:id,title')
                ->orderBy('submitted_at')
                ->get()
                ->groupBy('user_id');

            foreach ($students as $student) {
                $studentAttempts = $attempts->get($student->id, collect())
                    ->filter(fn (QuizAttempt $attempt): bool => $attempt->score !== null)
                    ->sortByDesc('submitted_at')
                    ->unique('quiz_id')
                    ->sortBy('submitted_at')
                    ->values();
                $early = $studentAttempts->filter(fn (QuizAttempt $attempt): bool => $attempt->submitted_at->lessThan($midpoint));
                $recent = $studentAttempts->filter(fn (QuizAttempt $attempt): bool => $attempt->submitted_at->greaterThanOrEqualTo($midpoint));

                if ($early->count() < $minimumAttempts || $recent->count() < $minimumAttempts) {
                    continue;
                }

                $earlyAverage = (float) $early->avg('score');
                $recentAverage = (float) $recent->avg('score');
                $drop = $earlyAverage - $recentAverage;

                if ($drop < $declineThreshold) {
                    continue;
                }

                $this->notifyStudentAndParents(
                    $student,
                    [
                        'alert_type' => self::ALERT_DECLINING_ASSESSMENT,
                        'title' => 'مؤشر يحتاج متابعة في نتائج الاختبارات',
                        'message' => 'انخفض متوسط نتائج اختباراتك مؤخرًا من '.round($earlyAverage).'% إلى '.round($recentAverage).'%. راجع الدروس والاختبارات القادمة أو تواصل مع معلمك.',
                        'link' => route('student.assessments'),
                        'parent_link' => route('parent.dashboard'),
                        'icon' => 'chart',
                        'student_id' => $student->id,
                        'entity_id' => (int) $assignmentId,
                    ],
                    self::ALERT_DECLINING_ASSESSMENT.':'.$assignmentId.':'.$student->id,
                    $now,
                    $stats,
                );
            }
        }
    }

    private function sendSubscriptionExpirationAlerts(Carbon $now, array &$stats): void
    {
        $today = $now->copy()->startOfDay();
        $until = $today->copy()->addDays(max(1, (int) config('learning.academic_alerts.subscription_expiration_days', 7)));

        Subscription::query()
            ->active()
            ->whereNull('renewal_reminder_sent_at')
            ->whereBetween('period_end', [$today->toDateString(), $until->toDateString()])
            ->with(['student:id,name', 'assignment.subject:id,name', 'group:id,name'])
            ->orderBy('id')
            ->get()
            ->each(function (Subscription $subscription) use ($today, $now, &$stats): void {
                if (! $subscription->student) {
                    return;
                }

                $days = max(0, (int) $today->diffInDays($subscription->period_end));
                $label = $subscription->assignment?->subject?->name
                    ? ' في مادة '.$subscription->assignment->subject->name
                    : '';

                $this->notifyStudentAndParents(
                    $subscription->student,
                    [
                        'alert_type' => self::ALERT_SUBSCRIPTION_EXPIRING,
                        'title' => 'اشتراكك يقترب من الانتهاء',
                        'message' => "ينتهي اشتراكك{$label} خلال {$days} يوم. جدّد الاشتراك مبكرًا حتى لا تتوقف حصصك ومتابعتك التعليمية.",
                        'link' => route('subscriptions.renewal.show', $subscription),
                        'parent_link' => route('parent.dashboard'),
                        'icon' => 'credit-card',
                        'student_id' => $subscription->student->id,
                        'entity_id' => $subscription->id,
                    ],
                    self::ALERT_SUBSCRIPTION_EXPIRING.':'.$subscription->id.':'.$subscription->period_end->toDateString(),
                    $now,
                    $stats,
                );
            });
    }

    private function studentsForSession(LiveSession $session): Collection
    {
        if (! $session->teaching_group_id && ! $session->private_session_slot_id) {
            return collect();
        }

        $studentIds = SessionBooking::query()
            ->where('status', 'confirmed')
            ->when(
                $session->teaching_group_id,
                fn ($query, $groupId) => $query->where('teaching_group_id', $groupId),
            )
            ->when(
                $session->private_session_slot_id,
                fn ($query, $slotId) => $query->where('private_session_slot_id', $slotId),
            )
            ->pluck('student_id')
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $studentIds)
            ->get();
    }

    private function sessionTimezone(LiveSession $session): string
    {
        return $session->teachingGroup?->timezone
            ?? $session->privateSessionSlot?->timezone
            ?? (string) config('app.timezone');
    }

    private function notifyStudentAndParents(
        User $student,
        array $payload,
        string $key,
        Carbon $now,
        array &$stats,
    ): void {
        $stats['alerts_evaluated']++;

        $recipients = collect([$student])
            ->merge(
                ParentStudentLink::query()
                    ->with('parent:id,name')
                    ->where('student_user_id', $student->id)
                    ->whereNotNull('verified_at')
                    ->get()
                    ->pluck('parent'),
            )
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $recipientPayload = $payload;
            if ($recipient->isParent() && isset($recipientPayload['parent_link'])) {
                $recipientPayload['link'] = $recipientPayload['parent_link'];
            }
            unset($recipientPayload['parent_link']);

            if ($this->notifyOnce($recipient, $recipientPayload, $key, $now)) {
                $stats['notifications_sent']++;
            } else {
                $stats['duplicates_skipped']++;
            }
        }
    }

    private function notifyOnce(User $recipient, array $payload, string $key, Carbon $now): bool
    {
        $cooldownDays = max(1, (int) config('learning.academic_alerts.dedupe_days', 7));

        if ($recipient->notifications()
            ->where('data->alert_key', $key)
            ->where('created_at', '>=', $now->copy()->subDays($cooldownDays))
            ->exists()) {
            return false;
        }

        $recipient->notify(new AcademicAlertNotification([
            ...$payload,
            'alert_key' => $key,
        ]));

        return true;
    }
}
