<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TeacherPerformanceAnalyticsService
{
    public const DEFAULT_DAYS = 30;

    public const ALLOWED_DAYS = [7, 30, 90];

    public function __construct(
        private readonly LearningOutcomeProgressService $outcomeProgress,
    ) {}

    /**
     * Build a teacher-scoped, read-only performance view from current roster
     * membership and the real learning records already used by the platform.
     *
     * @return array<string, mixed>
     */
    public function forTeacher(
        User $teacher,
        ?int $assignmentId = null,
        ?int $groupId = null,
        ?int $studentId = null,
        int $days = self::DEFAULT_DAYS,
    ): array {
        $days = in_array($days, self::ALLOWED_DAYS, true) ? $days : self::DEFAULT_DAYS;
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();

        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->with([
                'subject:id,name',
                'gradeLevel:id,key,name',
            ])
            ->orderBy('id')
            ->get();

        $assignmentIds = $assignments->pluck('id')->map(fn ($id): int => (int) $id);
        if ($assignmentId !== null && ! $assignmentIds->contains($assignmentId)) {
            abort(404);
        }

        $scopedAssignmentIds = $assignmentId === null
            ? $assignmentIds
            : collect([$assignmentId]);

        $groups = TeachingGroup::query()
            ->whereIn('teaching_assignment_id', $scopedAssignmentIds)
            ->when($groupId !== null, fn ($query) => $query->whereKey($groupId))
            ->with('assignment.subject:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'teaching_assignment_id']);

        if ($groupId !== null && ! $groups->contains('id', $groupId)) {
            abort(404);
        }

        $rosterData = $this->buildRoster(
            $scopedAssignmentIds,
            $groups->pluck('id'),
            $groupId !== null,
        );
        $roster = $rosterData['students'];

        if ($studentId !== null && ! isset($roster[$studentId])) {
            abort(404);
        }

        $studentIds = $studentId === null
            ? array_map('intval', array_keys($roster))
            : [$studentId];
        $stats = $this->initialStats($studentIds, $roster);
        $trendBuckets = $this->trendBuckets($start, $end, $days);
        $quizHistory = collect();

        if ($studentIds !== []) {
            $this->collectLessonProgress(
                $stats,
                $trendBuckets,
                $roster,
                $studentIds,
                $scopedAssignmentIds,
                $start,
                $end,
            );
            $this->collectAssignments(
                $stats,
                $trendBuckets,
                $roster,
                $studentIds,
                $scopedAssignmentIds,
                $start,
                $end,
            );
            $this->collectQuizAttempts(
                $stats,
                $trendBuckets,
                $roster,
                $quizHistory,
                $studentIds,
                $scopedAssignmentIds,
                $start,
                $end,
            );
            $this->collectAttendance(
                $stats,
                $trendBuckets,
                $roster,
                $rosterData['private_students_by_slot'],
                $studentIds,
                $scopedAssignmentIds,
                $groupId,
                $teacher->id,
                $start,
                $end,
            );
        }

        $rows = $this->studentRows($stats, $start, $days);
        $trend = $this->finalizeTrend($trendBuckets);
        $quizHistory = $quizHistory
            ->sortByDesc('submitted_at')
            ->take(50)
            ->values();
        $learningOutcomes = $this->outcomeProgress->summaryForTeacher(
            collect($studentIds),
            $scopedAssignmentIds,
        );

        $allScores = collect($stats)->flatMap(
            fn (array $stat): Collection => collect($stat['quiz_scores'])->pluck('score'),
        );
        $expectedSessions = (int) collect($stats)->sum('expected_sessions');
        $attendedSessions = (int) collect($stats)->sum('attended_sessions');

        return [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => $days,
            ],
            'filters' => [
                'assignment' => $assignmentId,
                'group' => $groupId,
                'student' => $studentId,
                'days' => $days,
            ],
            'assignments' => $assignments->map(fn (TeachingAssignment $assignment): array => [
                'id' => $assignment->id,
                'subject' => $assignment->subject?->only(['id', 'name']),
                'grade' => $assignment->gradeLevel?->only(['id', 'key', 'name']),
            ])->values(),
            'groups' => $groups->map(fn (TeachingGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'assignment_id' => $group->teaching_assignment_id,
                'subject' => $group->assignment?->subject?->name,
            ])->values(),
            'students' => $rows->map(fn (array $row): array => [
                'id' => $row['id'],
                'name' => $row['name'],
            ])->values(),
            'summary' => [
                'students' => $rows->count(),
                'students_requiring_attention' => $rows->where('requires_attention', true)->count(),
                'lessons_completed' => (int) $rows->sum('lessons_completed'),
                'study_minutes' => (int) $rows->sum('study_minutes'),
                'assignments_submitted' => (int) $rows->sum('assignments_submitted'),
                'missing_assignments' => (int) $rows->sum('missing_assignments'),
                'quiz_attempts' => (int) $rows->sum('quiz_attempts'),
                'average_quiz_score' => $allScores->isEmpty() ? null : round((float) $allScores->avg(), 1),
                'attendance_rate' => $expectedSessions === 0
                    ? null
                    : round($attendedSessions / $expectedSessions * 100, 1),
            ],
            'trend' => $trend,
            'rows' => $rows,
            'attention' => $rows->where('requires_attention', true)->values(),
            'quiz_history' => $quizHistory,
            'learning_outcomes' => $learningOutcomes,
        ];
    }

    /** @return array{students: array<int, array<string, mixed>>, private_students_by_slot: array<int, array<int, bool>>} */
    private function buildRoster(Collection $assignmentIds, Collection $groupIds, bool $groupFilter): array
    {
        $students = [];
        $privateStudentsBySlot = [];

        Subscription::query()
            ->active()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->when($groupFilter, fn ($query) => $query->whereIn('teaching_group_id', $groupIds))
            ->with([
                'student:id,name,email,avatar',
                'group:id,name,teaching_assignment_id',
                'assignment:id,subject_id',
                'assignment.subject:id,name',
            ])
            ->get()
            ->each(function (Subscription $subscription) use (&$students): void {
                if (! $subscription->student || ! $subscription->teaching_assignment_id) {
                    return;
                }

                $this->addRosterStudent(
                    $students,
                    $subscription->student,
                    (int) $subscription->teaching_assignment_id,
                    $subscription->group?->id === null ? null : (int) $subscription->group->id,
                    $subscription->group?->name,
                    $subscription->assignment?->subject?->name,
                );
            });

        if ($groupIds->isNotEmpty()) {
            SessionBooking::query()
                ->where('status', 'confirmed')
                ->whereIn('teaching_group_id', $groupIds)
                ->with([
                    'student:id,name,email,avatar',
                    'group:id,name,teaching_assignment_id',
                    'group.assignment.subject:id,name',
                ])
                ->get()
                ->each(function (SessionBooking $booking) use (&$students): void {
                    if (! $booking->student || ! $booking->group?->teaching_assignment_id) {
                        return;
                    }

                    $this->addRosterStudent(
                        $students,
                        $booking->student,
                        (int) $booking->group->teaching_assignment_id,
                        (int) $booking->group->id,
                        $booking->group->name,
                        $booking->group->assignment?->subject?->name,
                    );
                });
        }

        if (! $groupFilter && $assignmentIds->isNotEmpty()) {
            $privateSlotIds = PrivateSessionSlot::query()
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->pluck('id');

            if ($privateSlotIds->isNotEmpty()) {
                SessionBooking::query()
                    ->where('status', 'confirmed')
                    ->whereIn('private_session_slot_id', $privateSlotIds)
                    ->with([
                        'student:id,name,email,avatar',
                        'privateSlot:id,teaching_assignment_id',
                        'privateSlot.assignment.subject:id,name',
                    ])
                    ->get()
                    ->each(function (SessionBooking $booking) use (&$students, &$privateStudentsBySlot): void {
                        if (! $booking->student || ! $booking->privateSlot?->teaching_assignment_id) {
                            return;
                        }

                        $assignmentId = (int) $booking->privateSlot->teaching_assignment_id;
                        $slotId = (int) $booking->privateSlot->id;
                        $privateStudentsBySlot[$slotId][(int) $booking->student->id] = true;
                        $this->addRosterStudent(
                            $students,
                            $booking->student,
                            $assignmentId,
                            null,
                            null,
                            $booking->privateSlot->assignment?->subject?->name,
                            $slotId,
                        );
                    });
            }
        }

        foreach ($students as &$student) {
            $student['assignment_ids'] = array_values($student['assignment_ids']);
            $student['group_ids'] = array_values($student['group_ids']);
            $student['groups'] = array_values($student['groups']);
            $student['subjects'] = array_values($student['subjects']);
            $student['private_slot_ids'] = array_values($student['private_slot_ids']);
        }
        unset($student);

        return [
            'students' => $students,
            'private_students_by_slot' => $privateStudentsBySlot,
        ];
    }

    /** @param array<int, array<string, mixed>> $students */
    private function addRosterStudent(
        array &$students,
        User $student,
        int $assignmentId,
        ?int $groupId,
        ?string $groupName,
        ?string $subjectName,
        ?int $privateSlotId = null,
    ): void {
        $studentId = (int) $student->id;
        $students[$studentId] ??= [
            'id' => $studentId,
            'name' => $student->name,
            'email' => $student->email,
            'avatar' => $student->avatar,
            'assignment_ids' => [],
            'group_ids' => [],
            'group_lookup' => [],
            'groups' => [],
            'subjects' => [],
            'private_slot_ids' => [],
        ];

        $students[$studentId]['assignment_ids'][$assignmentId] = $assignmentId;
        if ($groupId !== null) {
            $students[$studentId]['group_ids'][$groupId] = $groupId;
            $students[$studentId]['group_lookup'][$groupId] = true;
            $students[$studentId]['groups'][$groupId] = $groupName ?? 'Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø©';
        }
        if ($subjectName !== null) {
            $students[$studentId]['subjects'][$subjectName] = $subjectName;
        }
        if ($privateSlotId !== null) {
            $students[$studentId]['private_slot_ids'][$privateSlotId] = $privateSlotId;
        }
    }

    /** @param array<int, array<string, mixed>> $roster */
    private function initialStats(array $studentIds, array $roster): array
    {
        $stats = [];
        foreach ($studentIds as $studentId) {
            $stats[$studentId] = [
                'student' => $roster[$studentId],
                'completed_lesson_ids' => [],
                'study_seconds' => 0,
                'assignments_submitted' => 0,
                'missing_assignments' => 0,
                'quiz_attempts' => 0,
                'quiz_scores' => [],
                'expected_sessions' => 0,
                'attended_sessions' => 0,
            ];
        }

        return $stats;
    }

    /** @param array<int, array<string, mixed>> $stats */
    private function collectLessonProgress(
        array &$stats,
        array &$trendBuckets,
        array $roster,
        array $studentIds,
        Collection $assignmentIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): void {
        LessonProgress::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('updated_at', [$start, $end])
            ->whereHas('material.unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'material:id,curriculum_unit_id,title',
                'material.unit:id,teaching_assignment_id',
            ])
            ->get()
            ->each(function (LessonProgress $progress) use (&$stats, &$trendBuckets, $roster, $start): void {
                $studentId = (int) $progress->student_id;
                $assignmentId = (int) ($progress->material?->unit?->teaching_assignment_id ?? 0);
                if (! isset($stats[$studentId]) || ! in_array($assignmentId, $roster[$studentId]['assignment_ids'], true)) {
                    return;
                }

                $stats[$studentId]['study_seconds'] += (int) $progress->watched_seconds;
                if ($progress->is_completed) {
                    $stats[$studentId]['completed_lesson_ids'][(int) $progress->lesson_id] = true;
                }

                $bucket = $this->bucketIndex($progress->updated_at, $start, count($trendBuckets));
                if ($bucket === null) {
                    return;
                }
                $trendBuckets[$bucket]['study_seconds'] += (int) $progress->watched_seconds;
                $trendBuckets[$bucket]['lessons_completed'] += $progress->is_completed ? 1 : 0;
            });
    }

    /** @param array<int, array<string, mixed>> $stats */
    private function collectAssignments(
        array &$stats,
        array &$trendBuckets,
        array $roster,
        array $studentIds,
        Collection $assignmentIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): void {
        $worksheets = Worksheet::query()
            ->where('type', Worksheet::TYPE_HOMEWORK)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'unit:id,teaching_assignment_id',
                'submissions' => fn ($query) => $query
                    ->whereIn('student_id', $studentIds)
                    ->whereNotNull('submitted_at')
                    ->where('submitted_at', '<=', $end)
                    ->latest('submitted_at'),
            ])
            ->get();

        foreach ($worksheets as $worksheet) {
            $assignmentId = (int) ($worksheet->unit?->teaching_assignment_id ?? 0);
            $submissions = $worksheet->submissions
                ->groupBy('student_id')
                ->map(fn (Collection $items): WorksheetSubmission => $items->sortByDesc('submitted_at')->first());

            foreach ($studentIds as $studentId) {
                if (! in_array($assignmentId, $roster[$studentId]['assignment_ids'], true)) {
                    continue;
                }

                $submission = $submissions->get($studentId);
                if ($submission === null) {
                    $stats[$studentId]['missing_assignments']++;

                    continue;
                }

                if (! $this->inWindow($submission->submitted_at, $start, $end)) {
                    continue;
                }

                $stats[$studentId]['assignments_submitted']++;
                $bucket = $this->bucketIndex($submission->submitted_at, $start, count($trendBuckets));
                if ($bucket !== null) {
                    $trendBuckets[$bucket]['assignments_submitted']++;
                }
            }
        }
    }

    /** @param array<int, array<string, mixed>> $stats */
    private function collectQuizAttempts(
        array &$stats,
        array &$trendBuckets,
        array $roster,
        Collection &$quizHistory,
        array $studentIds,
        Collection $assignmentIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): void {
        QuizAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$start, $end])
            ->whereHas('quiz.unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'quiz:id,curriculum_unit_id,title',
                'quiz.unit:id,teaching_assignment_id',
            ])
            ->get()
            ->each(function (QuizAttempt $attempt) use (&$stats, &$trendBuckets, $roster, &$quizHistory, $start): void {
                $studentId = (int) $attempt->user_id;
                $assignmentId = (int) ($attempt->quiz?->unit?->teaching_assignment_id ?? 0);
                if (! isset($stats[$studentId]) || ! in_array($assignmentId, $roster[$studentId]['assignment_ids'], true)) {
                    return;
                }

                $score = $this->quizScore($attempt);
                $stats[$studentId]['quiz_attempts']++;
                if ($score !== null) {
                    $stats[$studentId]['quiz_scores'][] = [
                        'score' => $score,
                        'at' => $attempt->submitted_at,
                    ];
                }

                $quizHistory->push([
                    'id' => $attempt->id,
                    'student' => [
                        'id' => $studentId,
                        'name' => $stats[$studentId]['student']['name'],
                    ],
                    'title' => $attempt->quiz?->title ?? 'Ø§Ø®ØªØ¨Ø§Ø±',
                    'score' => $score,
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                ]);

                $bucket = $this->bucketIndex($attempt->submitted_at, $start, count($trendBuckets));
                if ($bucket !== null) {
                    $trendBuckets[$bucket]['quiz_attempts']++;
                    if ($score !== null) {
                        $trendBuckets[$bucket]['quiz_scores'][] = $score;
                    }
                }
            });
    }

    /** @param array<int, array<string, mixed>> $stats */
    private function collectAttendance(
        array &$stats,
        array &$trendBuckets,
        array $roster,
        array $privateStudentsBySlot,
        array $studentIds,
        Collection $assignmentIds,
        ?int $groupId,
        int $teacherId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): void {
        LiveSession::query()
            ->where('teacher_id', $teacherId)
            ->where('status', '!=', LiveSession::STATUS_CANCELLED)
            ->whereBetween('scheduled_at', [$start, $end])
            ->when($groupId !== null, fn ($query) => $query->where('teaching_group_id', $groupId))
            ->where(function ($query) use ($assignmentIds): void {
                $query
                    ->whereHas('teachingGroup', fn ($groupQuery) => $groupQuery
                        ->whereIn('teaching_assignment_id', $assignmentIds))
                    ->orWhereHas('privateSessionSlot', fn ($slotQuery) => $slotQuery
                        ->whereIn('teaching_assignment_id', $assignmentIds));
            })
            ->with([
                'teachingGroup:id,teaching_assignment_id',
                'attendees:id,live_session_id,user_id,joined_at,left_at,last_heartbeat_at',
            ])
            ->get()
            ->each(function (LiveSession $session) use (&$stats, &$trendBuckets, $roster, $privateStudentsBySlot, $studentIds, $start): void {
                $audience = [];
                if ($session->teaching_group_id !== null) {
                    $sessionGroupId = (int) $session->teaching_group_id;
                    foreach ($studentIds as $studentId) {
                        if (isset($roster[$studentId]['group_lookup'][$sessionGroupId])) {
                            $audience[] = $studentId;
                        }
                    }
                } elseif ($session->private_session_slot_id !== null) {
                    $slotAudience = $privateStudentsBySlot[(int) $session->private_session_slot_id] ?? [];
                    $audience = array_values(array_intersect($studentIds, array_map('intval', array_keys($slotAudience))));
                }

                if ($audience === []) {
                    return;
                }

                $attended = $session->attendees
                    ->filter(fn (LiveSessionAttendee $attendee): bool => $attendee->joined_at !== null)
                    ->pluck('user_id')
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->all();

                $bucket = $this->bucketIndex($session->scheduled_at, $start, count($trendBuckets));
                foreach ($audience as $studentId) {
                    $stats[$studentId]['expected_sessions']++;
                    $wasPresent = in_array($studentId, $attended, true);
                    $stats[$studentId]['attended_sessions'] += $wasPresent ? 1 : 0;
                    if ($bucket !== null) {
                        $trendBuckets[$bucket]['attendance_expected']++;
                        $trendBuckets[$bucket]['attendance_attended'] += $wasPresent ? 1 : 0;
                    }
                }
            });
    }

    /** @param array<int, array<string, mixed>> $stats */
    private function studentRows(array $stats, CarbonImmutable $start, int $days): Collection
    {
        return collect($stats)
            ->map(function (array $stat) use ($start, $days): array {
                $scores = collect($stat['quiz_scores']);
                $average = $scores->isEmpty() ? null : round((float) $scores->avg('score'), 1);
                $attendanceRate = $stat['expected_sessions'] === 0
                    ? null
                    : round($stat['attended_sessions'] / $stat['expected_sessions'] * 100, 1);
                $reasons = [];

                if ($stat['missing_assignments'] > 0) {
                    $reasons[] = [
                        'code' => 'missing_assignments',
                        'value' => $stat['missing_assignments'],
                    ];
                }
                if ($stat['expected_sessions'] >= 2 && $attendanceRate !== null && $attendanceRate < 75) {
                    $reasons[] = [
                        'code' => 'low_attendance',
                        'value' => $attendanceRate,
                    ];
                }

                $midpoint = $start->addDays((int) floor($days / 2));
                $earlyScores = $scores->filter(fn (array $item): bool => $item['at']?->lessThan($midpoint) ?? false);
                $recentScores = $scores->filter(fn (array $item): bool => $item['at']?->greaterThanOrEqualTo($midpoint) ?? false);
                if ($earlyScores->isNotEmpty() && $recentScores->isNotEmpty()) {
                    $earlyAverage = (float) $earlyScores->avg('score');
                    $recentAverage = (float) $recentScores->avg('score');
                    if ($recentAverage <= $earlyAverage - 10) {
                        $reasons[] = [
                            'code' => 'declining_results',
                            'value' => round($recentAverage - $earlyAverage, 1),
                        ];
                    }
                }

                return [
                    'id' => $stat['student']['id'],
                    'name' => $stat['student']['name'],
                    'email' => $stat['student']['email'],
                    'avatar' => $stat['student']['avatar'],
                    'groups' => $stat['student']['groups'],
                    'subjects' => $stat['student']['subjects'],
                    'lessons_completed' => count($stat['completed_lesson_ids']),
                    'study_minutes' => (int) round($stat['study_seconds'] / 60),
                    'assignments_submitted' => $stat['assignments_submitted'],
                    'missing_assignments' => $stat['missing_assignments'],
                    'quiz_attempts' => $stat['quiz_attempts'],
                    'average_quiz_score' => $average,
                    'expected_sessions' => $stat['expected_sessions'],
                    'attended_sessions' => $stat['attended_sessions'],
                    'attendance_rate' => $attendanceRate,
                    'requires_attention' => $reasons !== [],
                    'attention_reasons' => $reasons,
                ];
            })
            ->sortByDesc(fn (array $row): array => [
                $row['requires_attention'] ? 1 : 0,
                $row['missing_assignments'],
                $row['name'],
            ])
            ->values();
    }

    /** @param array<int, array<string, mixed>> $buckets */
    private function finalizeTrend(array $buckets): Collection
    {
        return collect($buckets)->map(function (array $bucket): array {
            $scores = $bucket['quiz_scores'];
            $expected = $bucket['attendance_expected'];
            $studySeconds = $bucket['study_seconds'];
            $attended = $bucket['attendance_attended'];
            unset($bucket['study_seconds'], $bucket['quiz_scores'], $bucket['attendance_expected'], $bucket['attendance_attended']);

            $bucket['study_minutes'] = (int) round($studySeconds / 60);
            $bucket['average_quiz_score'] = $scores === [] ? null : round((float) collect($scores)->avg(), 1);
            $bucket['attendance_rate'] = $expected === 0
                ? null
                : round($attended / $expected * 100, 1);

            return $bucket;
        })->values();
    }

    /** @return array<int, array<string, mixed>> */
    private function trendBuckets(CarbonImmutable $start, CarbonImmutable $end, int $days): array
    {
        $count = max(1, (int) ceil($days / 7));
        $buckets = [];
        for ($index = 0; $index < $count; $index++) {
            $bucketStart = $start->addDays($index * 7);
            $bucketEnd = $bucketStart->addDays(6)->endOfDay();
            if ($bucketEnd->greaterThan($end)) {
                $bucketEnd = $end;
            }

            $buckets[] = [
                'start' => $bucketStart->toDateString(),
                'end' => $bucketEnd->toDateString(),
                'lessons_completed' => 0,
                'assignments_submitted' => 0,
                'quiz_attempts' => 0,
                'study_seconds' => 0,
                'quiz_scores' => [],
                'attendance_expected' => 0,
                'attendance_attended' => 0,
            ];
        }

        return $buckets;
    }

    private function bucketIndex(
        ?CarbonInterface $date,
        CarbonImmutable $start,
        int $bucketCount,
    ): ?int {
        if ($date === null || $date->lessThan($start) || $date->greaterThan($start->addDays($bucketCount * 7 - 1)->endOfDay())) {
            return null;
        }

        return min($bucketCount - 1, intdiv((int) $start->diffInDays($date), 7));
    }

    private function quizScore(QuizAttempt $attempt): ?float
    {
        if ($attempt->total_points !== null && (int) $attempt->total_points > 0) {
            return round(((int) $attempt->earned_points / (int) $attempt->total_points) * 100, 1);
        }

        return $attempt->score === null ? null : (float) $attempt->score;
    }

    private function inWindow(?CarbonInterface $date, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $date !== null && $date->betweenIncluded($start, $end);
    }
}
