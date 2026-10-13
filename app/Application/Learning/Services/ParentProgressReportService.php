<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ParentProgressReportService
{
    private const WINDOW_DAYS = 7;

    /**
     * Build a rolling seven-day report from records the selected student is
     * currently authorized to access through an active subscription.
     *
     * @return array{range: array<string, mixed>, summary: array<string, int|float|null>, daily: Collection<int, array<string, mixed>>, by_subject: Collection<int, array<string, mixed>>, recent: Collection<int, array<string, mixed>>}
     */
    public function forStudent(User $student): array
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays(self::WINDOW_DAYS - 1)->startOfDay();
        $assignmentIds = Subscription::active()
            ->where('student_id', $student->id)
            ->pluck('teaching_assignment_id')
            ->filter()
            ->unique()
            ->values();

        if ($assignmentIds->isEmpty()) {
            return $this->emptyReport($start, $end);
        }

        $progress = LessonProgress::query()
            ->where('student_id', $student->id)
            ->whereBetween('updated_at', [$start, $end])
            ->whereHas('material.unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'material:id,curriculum_unit_id,title',
                'material.unit.assignment.subject:id,name',
            ])
            ->get();

        $submissions = WorksheetSubmission::query()
            ->where('student_id', $student->id)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('submitted_at', [$start, $end])
                    ->orWhereBetween('graded_at', [$start, $end]);
            })
            ->whereHas('worksheet.unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'worksheet:id,curriculum_unit_id,title,max_score',
                'worksheet.unit.assignment.subject:id,name',
            ])
            ->get();

        $attempts = QuizAttempt::query()
            ->where('user_id', $student->id)
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$start, $end])
            ->whereHas('quiz.unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'quiz:id,curriculum_unit_id,title',
                'quiz.unit.assignment.subject:id,name',
            ])
            ->get();

        $attendance = LiveSessionAttendee::query()
            ->where('user_id', $student->id)
            ->whereNotNull('joined_at')
            ->whereBetween('joined_at', [$start, $end])
            ->whereHas('session', function ($query) use ($assignmentIds, $start, $end): void {
                $query
                    ->where('status', LiveSession::STATUS_ENDED)
                    ->whereBetween('scheduled_at', [$start, $end])
                    ->where(function ($sessionQuery) use ($assignmentIds): void {
                        $sessionQuery
                            ->whereHas('teachingGroup', fn ($groupQuery) => $groupQuery
                                ->whereIn('teaching_assignment_id', $assignmentIds))
                            ->orWhereHas('privateSessionSlot', fn ($slotQuery) => $slotQuery
                                ->whereIn('teaching_assignment_id', $assignmentIds));
                    });
            })
            ->with([
                'session:id,title,scheduled_at,teaching_group_id,private_session_slot_id',
                'session.teachingGroup:id,teaching_assignment_id',
                'session.privateSessionSlot:id,teaching_assignment_id',
            ])
            ->get();

        $days = $this->days($start);
        $subjects = [];
        $recent = collect();

        foreach ($progress as $item) {
            $date = $this->dateKey($item->updated_at, $start, $end);
            if ($date === null) {
                continue;
            }

            $started = (int) $item->watched_seconds > 0;
            $completed = (bool) $item->is_completed;
            $days[$date]['lessons_started'] += $started ? 1 : 0;
            $days[$date]['lessons_completed'] += $completed ? 1 : 0;
            $days[$date]['study_seconds'] += (int) $item->watched_seconds;
            $subject = $item->material?->unit?->assignment?->subject?->only(['id', 'name']);
            $subjectKey = $this->ensureSubject($subjects, $subject);
            $subjects[$subjectKey]['lessons_started'] += $started ? 1 : 0;
            $subjects[$subjectKey]['lessons_completed'] += $completed ? 1 : 0;

            $recent->push([
                'type' => 'lesson',
                'title' => $item->material?->title ?? 'درس',
                'subject' => $subject['name'] ?? null,
                'detail' => $completed ? 'اكتمل الدرس' : 'تمت متابعة الدرس',
                'occurred_at' => $item->updated_at?->toIso8601String(),
            ]);
        }

        foreach ($submissions as $submission) {
            $submittedThisWeek = $this->inWindow($submission->submitted_at, $start, $end);
            $gradedThisWeek = $this->inWindow($submission->graded_at, $start, $end);
            $subject = $submission->worksheet?->unit?->assignment?->subject?->only(['id', 'name']);
            $subjectKey = $this->ensureSubject($subjects, $subject);
            $subjects[$subjectKey]['assignments_submitted'] += $submittedThisWeek ? 1 : 0;
            $subjects[$subjectKey]['assignments_graded'] += $gradedThisWeek ? 1 : 0;

            if ($submittedThisWeek) {
                $days[$submission->submitted_at->toDateString()]['assignments_submitted']++;
            }
            if ($gradedThisWeek) {
                $days[$submission->graded_at->toDateString()]['assignments_graded']++;
            }

            $occurredAt = $gradedThisWeek ? $submission->graded_at : $submission->submitted_at;
            $recent->push([
                'type' => 'assignment',
                'title' => $submission->worksheet?->title ?? 'واجب',
                'subject' => $subject['name'] ?? null,
                'detail' => $gradedThisWeek ? 'تم تصحيح الواجب' : 'تم تسليم الواجب',
                'score' => $submission->score,
                'max_score' => $submission->worksheet?->max_score,
                'occurred_at' => $occurredAt?->toIso8601String(),
            ]);
        }

        foreach ($attempts as $attempt) {
            $date = $attempt->submitted_at?->toDateString();
            if ($date === null || ! isset($days[$date])) {
                continue;
            }

            $days[$date]['quizzes_submitted']++;
            if ($attempt->score !== null) {
                $days[$date]['quiz_scores'][] = (int) $attempt->score;
            }

            $subject = $attempt->quiz?->unit?->assignment?->subject?->only(['id', 'name']);
            $subjectKey = $this->ensureSubject($subjects, $subject);
            $subjects[$subjectKey]['quizzes_submitted']++;
            if ($attempt->score !== null) {
                $subjects[$subjectKey]['quiz_scores'][] = (int) $attempt->score;
            }

            $recent->push([
                'type' => 'quiz',
                'title' => $attempt->quiz?->title ?? 'اختبار',
                'subject' => $subject['name'] ?? null,
                'detail' => $attempt->passed ? 'اجتاز الاختبار' : 'تم إنهاء الاختبار',
                'score' => $attempt->score,
                'occurred_at' => $attempt->submitted_at?->toIso8601String(),
            ]);
        }

        $attendanceSessions = $attendance->groupBy('live_session_id')->map(function (Collection $attendees): array {
            $first = $attendees->first();
            $session = $first?->session;
            $minutes = (int) round($attendees->sum(
                fn (LiveSessionAttendee $attendee): int => $attendee->durationSeconds(),
            ) / 60);

            return [
                'session' => $session,
                'minutes' => $minutes,
                'attended' => $minutes > 0,
            ];
        })->values();

        foreach ($attendanceSessions as $attendanceSession) {
            $session = $attendanceSession['session'];
            $date = $session?->scheduled_at?->toDateString();
            if ($date === null || ! isset($days[$date])) {
                continue;
            }

            $days[$date]['attendance_sessions']++;
            $days[$date]['attendance_present'] += $attendanceSession['attended'] ? 1 : 0;
            $days[$date]['attendance_minutes'] += $attendanceSession['minutes'];
            $recent->push([
                'type' => 'attendance',
                'title' => $session?->title ?? 'حصة مباشرة',
                'subject' => null,
                'detail' => $attendanceSession['attended']
                    ? "حضور {$attendanceSession['minutes']} دقيقة"
                    : 'لم يتم تسجيل حضور',
                'occurred_at' => $session?->scheduled_at?->toIso8601String(),
            ]);
        }

        $submittedScores = $attempts->pluck('score')->filter(fn ($score): bool => $score !== null);
        $attendanceCount = $attendanceSessions->count();

        return [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => self::WINDOW_DAYS,
            ],
            'summary' => [
                'activities' => $recent->count(),
                'lessons_started' => $progress->filter(fn (LessonProgress $item): bool => (int) $item->watched_seconds > 0)->count(),
                'lessons_completed' => $progress->where('is_completed', true)->count(),
                'study_minutes' => (int) round($progress->sum('watched_seconds') / 60),
                'assignments_submitted' => $submissions->filter(fn (WorksheetSubmission $submission): bool => $this->inWindow($submission->submitted_at, $start, $end))->count(),
                'assignments_graded' => $submissions->filter(fn (WorksheetSubmission $submission): bool => $this->inWindow($submission->graded_at, $start, $end))->count(),
                'quizzes_submitted' => $attempts->count(),
                'average_quiz_score' => $submittedScores->isEmpty() ? null : (int) round((float) $submittedScores->avg()),
                'attendance_sessions' => $attendanceCount,
                'attendance_present' => $attendanceSessions->where('attended', true)->count(),
                'attendance_rate' => $attendanceCount === 0
                    ? 0
                    : (int) round($attendanceSessions->where('attended', true)->count() / $attendanceCount * 100),
            ],
            'daily' => collect($days)->map(function (array $day): array {
                $scores = $day['quiz_scores'];
                unset($day['quiz_scores'], $day['study_seconds']);
                $day['quiz_average_score'] = $scores === [] ? null : (int) round((float) collect($scores)->avg());

                return $day;
            })->values(),
            'by_subject' => collect($subjects)->map(function (array $subject): array {
                $scores = $subject['quiz_scores'];
                unset($subject['quiz_scores']);
                $subject['quiz_average_score'] = $scores === [] ? null : (int) round((float) collect($scores)->avg());
                $subject['activities'] = $subject['lessons_started']
                    + $subject['assignments_submitted']
                    + $subject['quizzes_submitted'];

                return $subject;
            })->sortByDesc('activities')->values(),
            'recent' => $recent
                ->sortByDesc(fn (array $event): string => (string) ($event['occurred_at'] ?? ''))
                ->take(12)
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyReport(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => self::WINDOW_DAYS,
            ],
            'summary' => [
                'activities' => 0,
                'lessons_started' => 0,
                'lessons_completed' => 0,
                'study_minutes' => 0,
                'assignments_submitted' => 0,
                'assignments_graded' => 0,
                'quizzes_submitted' => 0,
                'average_quiz_score' => null,
                'attendance_sessions' => 0,
                'attendance_present' => 0,
                'attendance_rate' => 0,
            ],
            'daily' => collect(),
            'by_subject' => collect(),
            'recent' => collect(),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function days(CarbonImmutable $start): array
    {
        $days = [];
        for ($offset = 0; $offset < self::WINDOW_DAYS; $offset++) {
            $date = $start->addDays($offset)->toDateString();
            $days[$date] = [
                'date' => $date,
                'lessons_started' => 0,
                'lessons_completed' => 0,
                'study_seconds' => 0,
                'assignments_submitted' => 0,
                'assignments_graded' => 0,
                'quizzes_submitted' => 0,
                'quiz_scores' => [],
                'attendance_sessions' => 0,
                'attendance_present' => 0,
                'attendance_minutes' => 0,
            ];
        }

        return $days;
    }

    /** @param array<string, array<string, mixed>> $subjects */
    private function ensureSubject(array &$subjects, ?array $subject): string
    {
        $key = (string) ($subject['id'] ?? 'unclassified');
        if (! isset($subjects[$key])) {
            $subjects[$key] = [
                'id' => $subject['id'] ?? null,
                'name' => $subject['name'] ?? 'غير مصنف',
                'lessons_started' => 0,
                'lessons_completed' => 0,
                'assignments_submitted' => 0,
                'assignments_graded' => 0,
                'quizzes_submitted' => 0,
                'quiz_scores' => [],
            ];
        }

        return $key;
    }

    private function dateKey(?CarbonInterface $date, CarbonImmutable $start, CarbonImmutable $end): ?string
    {
        return $this->inWindow($date, $start, $end) ? $date->toDateString() : null;
    }

    private function inWindow(?CarbonInterface $date, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $date !== null && $date->betweenIncluded($start, $end);
    }
}
