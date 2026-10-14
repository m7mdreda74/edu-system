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
use Illuminate\Support\Collection;

/**
 * Computes the optional student achievement summary from authorized records.
 *
 * Values are intentionally derived, not persisted: each activity is counted
 * once per lesson/quiz/worksheet/session so repeated low-value actions cannot
 * inflate the score, and no public ranking is exposed.
 */
class StudentAchievementService
{
    private const POINTS = [
        'lessons' => 10,
        'quizzes' => 15,
        'assignments' => 5,
        'attendance' => 5,
    ];

    /** @return array<string, mixed>|null */
    public function forStudent(User $student): ?array
    {
        if (! (bool) config('learning.gamification_enabled', false)) {
            return null;
        }

        $assignmentIds = Subscription::active()
            ->where('student_id', $student->id)
            ->pluck('teaching_assignment_id')
            ->filter()
            ->unique()
            ->values();

        $counts = [
            'lessons' => $this->completedLessons($student, $assignmentIds),
            'quizzes' => $this->passedQuizzes($student, $assignmentIds),
            'assignments' => $this->submittedAssignments($student, $assignmentIds),
            'attendance' => $this->attendedSessions($student, $assignmentIds),
        ];
        $points = ($counts['lessons'] * self::POINTS['lessons'])
            + ($counts['quizzes'] * self::POINTS['quizzes'])
            + ($counts['assignments'] * self::POINTS['assignments'])
            + ($counts['attendance'] * self::POINTS['attendance']);
        $milestones = $this->milestones($points);

        return [
            'points' => $points,
            'level' => $this->level($points),
            'next_milestone' => collect($milestones)->first(fn (array $milestone): bool => ! $milestone['achieved']),
            'milestones' => $milestones,
            'badges' => $this->badges($counts),
            'breakdown' => [
                ['key' => 'lessons', 'label' => 'دروس مكتملة', 'count' => $counts['lessons'], 'points' => $counts['lessons'] * self::POINTS['lessons']],
                ['key' => 'quizzes', 'label' => 'اختبارات مجتازة', 'count' => $counts['quizzes'], 'points' => $counts['quizzes'] * self::POINTS['quizzes']],
                ['key' => 'assignments', 'label' => 'واجبات مسلّمة', 'count' => $counts['assignments'], 'points' => $counts['assignments'] * self::POINTS['assignments']],
                ['key' => 'attendance', 'label' => 'حصص حضرها', 'count' => $counts['attendance'], 'points' => $counts['attendance'] * self::POINTS['attendance']],
            ],
            'privacy' => [
                'public_leaderboard' => false,
            ],
        ];
    }

    /** @param Collection<int, int|string> $assignmentIds */
    private function completedLessons(User $student, Collection $assignmentIds): int
    {
        if ($assignmentIds->isEmpty()) {
            return 0;
        }

        return (int) LessonProgress::query()
            ->where('student_id', $student->id)
            ->where('is_completed', true)
            ->whereHas('material.unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->distinct('lesson_id')
            ->count('lesson_id');
    }

    /** @param Collection<int, int|string> $assignmentIds */
    private function passedQuizzes(User $student, Collection $assignmentIds): int
    {
        if ($assignmentIds->isEmpty()) {
            return 0;
        }

        return (int) QuizAttempt::query()
            ->where('user_id', $student->id)
            ->where('passed', true)
            ->whereNotNull('submitted_at')
            ->whereHas('quiz.unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->distinct('quiz_id')
            ->count('quiz_id');
    }

    /** @param Collection<int, int|string> $assignmentIds */
    private function submittedAssignments(User $student, Collection $assignmentIds): int
    {
        if ($assignmentIds->isEmpty()) {
            return 0;
        }

        return (int) WorksheetSubmission::query()
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->whereHas('worksheet.unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->distinct('worksheet_id')
            ->count('worksheet_id');
    }

    /** @param Collection<int, int|string> $assignmentIds */
    private function attendedSessions(User $student, Collection $assignmentIds): int
    {
        if ($assignmentIds->isEmpty()) {
            return 0;
        }

        return LiveSessionAttendee::query()
            ->where('user_id', $student->id)
            ->whereNotNull('joined_at')
            ->whereHas('session', function ($query) use ($assignmentIds): void {
                $query
                    ->where('status', LiveSession::STATUS_ENDED)
                    ->where(function ($sessionQuery) use ($assignmentIds): void {
                        $sessionQuery
                            ->whereHas('teachingGroup', fn ($groupQuery) => $groupQuery
                                ->whereIn('teaching_assignment_id', $assignmentIds))
                            ->orWhereHas('privateSessionSlot', fn ($slotQuery) => $slotQuery
                                ->whereIn('teaching_assignment_id', $assignmentIds));
                    });
            })
            ->get()
            ->groupBy('live_session_id')
            ->filter(fn (Collection $attendees): bool => $attendees->sum(
                fn (LiveSessionAttendee $attendee): int => $attendee->durationSeconds(),
            ) > 0)
            ->count();
    }

    /** @return array<string, mixed> */
    private function level(int $points): array
    {
        $levels = [
            ['key' => 'starter', 'label' => 'بداية قوية', 'minimum_points' => 0],
            ['key' => 'active', 'label' => 'متعلم نشيط', 'minimum_points' => 50],
            ['key' => 'advanced', 'label' => 'متعلم متقدم', 'minimum_points' => 150],
            ['key' => 'champion', 'label' => 'بطل التعلم', 'minimum_points' => 300],
        ];

        return collect($levels)->last(fn (array $level): bool => $points >= $level['minimum_points']);
    }

    /** @return array<int, array<string, int|bool|string>> */
    private function milestones(int $points): array
    {
        return collect([
            ['key' => 'first_steps', 'label' => 'الخطوات الأولى', 'minimum_points' => 25],
            ['key' => 'active_learner', 'label' => 'متعلم نشيط', 'minimum_points' => 75],
            ['key' => 'consistent_learner', 'label' => 'تعلم مستمر', 'minimum_points' => 150],
            ['key' => 'learning_champion', 'label' => 'بطل التعلم', 'minimum_points' => 300],
        ])->map(fn (array $milestone): array => $milestone + [
            'achieved' => $points >= $milestone['minimum_points'],
            'remaining_points' => max(0, $milestone['minimum_points'] - $points),
        ])->all();
    }

    /** @param array<string, int> $counts @return array<int, array<string, string>> */
    private function badges(array $counts): array
    {
        $badges = [];
        if ($counts['lessons'] >= 1) {
            $badges[] = ['key' => 'first_lesson', 'label' => 'أول درس مكتمل', 'icon' => 'book'];
        }
        if ($counts['quizzes'] >= 3) {
            $badges[] = ['key' => 'quiz_master', 'label' => 'متقن للاختبارات', 'icon' => 'certificate'];
        }
        if ($counts['lessons'] >= 3) {
            $badges[] = ['key' => 'consistent_progress', 'label' => 'تقدم مستمر', 'icon' => 'trending-up'];
        }

        return $badges;
    }
}
