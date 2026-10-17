<?php

declare(strict_types=1);

namespace App\Application\Quiz\Services;

use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Support\Collection;

class StudentAssessmentService
{
    /**
     * Build the student's assessment center from published electronic quizzes and paper/essay
     * exams unlocked by an active subscription. Questions and answer keys are never included.
     *
     * @return array{items: Collection<int, array<string, mixed>>, summary: array<string, int|float>}
     */
    public function forStudent(User $student): array
    {
        $assignmentIds = Subscription::active()
            ->where('student_id', $student->id)
            ->pluck('teaching_assignment_id')
            ->filter()
            ->unique()
            ->values();

        if ($assignmentIds->isEmpty()) {
            return ['items' => collect(), 'summary' => $this->emptySummary()];
        }

        // 1. Electronic Quizzes / Exams
        $quizzes = Quiz::query()
            ->where('is_active', true)
            ->whereHas('unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'unit:id,teaching_assignment_id,title,order',
                'unit.assignment.subject:id,name',
                'unit.assignment.teacher:id,name',
                'questions:id,quiz_id,points',
                'attempts' => fn ($query) => $query
                    ->where('user_id', $student->id)
                    ->latest('created_at'),
            ])
            ->latest('created_at')
            ->limit(100)
            ->get();

        $quizItems = $quizzes
            ->map(fn (Quiz $quiz): array => $this->presentQuiz($quiz));

        // 2. Paper / Essay Exams (اختبارات مقالية / ورقية)
        $paperExams = Worksheet::query()
            ->where('type', Worksheet::TYPE_PAPER_EXAM)
            ->whereHas('unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'unit:id,teaching_assignment_id,title,order',
                'unit.assignment.subject:id,name',
                'unit.assignment.teacher:id,name',
                'submissions' => fn ($query) => $query
                    ->where('student_id', $student->id)
                    ->latest('submitted_at'),
            ])
            ->latest('created_at')
            ->limit(100)
            ->get();

        $paperItems = $paperExams
            ->map(fn (Worksheet $exam): array => $this->presentPaperExam($exam, $student));

        $items = $quizItems->concat($paperItems)->values();

        return [
            'items' => $items,
            'summary' => $this->summarize($items),
        ];
    }

    /** @return array<string, int|float> */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'available' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'passed' => 0,
            'average_score' => 0,
            'electronic_count' => 0,
            'paper_count' => 0,
            'unit_count' => 0,
            'midterm_count' => 0,
            'comprehensive_count' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function presentQuiz(Quiz $quiz): array
    {
        $attempts = $quiz->attempts;
        $submitted = $attempts->filter(fn (QuizAttempt $attempt): bool => $attempt->submitted_at !== null);
        $latest = $attempts->first();
        $best = $submitted->sortByDesc(fn (QuizAttempt $attempt): int => (int) ($attempt->score ?? 0))->first();
        $questionCount = $quiz->questions->count();
        $remainingAttempts = max(0, QuizService::MAX_ATTEMPTS - $attempts->count());
        $scope = $this->inferScope($quiz->title, $quiz->unit?->title);

        return [
            'id' => $quiz->id,
            'mode' => 'electronic',
            'type_label' => 'اختبار إلكتروني',
            'scope' => $scope['key'],
            'scope_label' => $scope['label'],
            'title' => $quiz->title,
            'subject' => $quiz->unit?->assignment?->subject?->only(['id', 'name']),
            'teacher' => $quiz->unit?->assignment?->teacher?->only(['id', 'name']),
            'unit' => $quiz->unit?->only(['id', 'title', 'order']),
            'questions_count' => $questionCount,
            'total_points' => (int) $quiz->questions->sum(fn ($question): int => max(1, (int) $question->points)),
            'passing_score' => (int) $quiz->passing_score,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'available_from' => $quiz->available_from?->toIso8601String(),
            'available_until' => $quiz->available_until?->toIso8601String(),
            'is_open' => $quiz->isOpen(),
            'status' => $this->status($quiz, $latest, $questionCount),
            'can_start' => $quiz->isOpen() && $questionCount > 0 && $remainingAttempts > 0,
            'attempts_used' => $attempts->count(),
            'remaining_attempts' => $remainingAttempts,
            'latest_attempt' => $latest ? $this->attempt($latest) : null,
            'best_attempt' => $best ? $this->attempt($best) : null,
            'attempts' => $attempts->map(fn (QuizAttempt $attempt): array => $this->attempt($attempt))->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentPaperExam(Worksheet $exam, User $student): array
    {
        /** @var WorksheetSubmission|null $submission */
        $submission = $exam->submissions->first();
        $scope = $this->inferScope($exam->title, $exam->unit?->title);

        $isGraded = $submission?->isGraded() ?? false;
        $hasSubmitted = $submission !== null && $submission->submitted_at !== null;
        $isPastDue = $exam->due_date?->isPast() ?? false;

        $status = match (true) {
            $isGraded => 'completed',
            $hasSubmitted => 'in_progress',
            $isPastDue => 'closed',
            default => 'available',
        };

        return [
            'id' => $exam->id,
            'mode' => 'paper',
            'type_label' => 'اختبار مقالي / ورقي',
            'scope' => $scope['key'],
            'scope_label' => $scope['label'],
            'title' => $exam->title,
            'subject' => $exam->unit?->assignment?->subject?->only(['id', 'name']),
            'teacher' => $exam->unit?->assignment?->teacher?->only(['id', 'name']),
            'unit' => $exam->unit?->only(['id', 'title', 'order']),
            'file_url' => filled($exam->file_path) ? route('learning.worksheet.download', $exam->id) : null,
            'due_date' => $exam->due_date?->toIso8601String(),
            'max_score' => (int) ($exam->max_score ?? 100),
            'requires_submission' => (bool) $exam->requires_submission,
            'status' => $status,
            'is_late' => $submission?->isLate() ?? false,
            'can_start' => true,
            'can_submit' => (bool) $exam->requires_submission,
            'submission' => $submission === null ? null : [
                'id' => $submission->id,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'score' => $submission->score,
                'score_percent' => ($exam->max_score && $submission->score !== null)
                    ? (int) round(($submission->score / $exam->max_score) * 100)
                    : null,
                'is_graded' => $isGraded,
                'is_late' => $submission->isLate(),
                'teacher_feedback' => $submission->teacher_feedback,
                'file_url' => filled($submission->submitted_file_path)
                    ? route('learning.submission.download', $submission->id)
                    : null,
            ],
        ];
    }

    /**
     * Infer whether this exam is on a single unit, multiple units/midterm, or comprehensive syllabus.
     *
     * @return array{key: 'unit'|'midterm'|'comprehensive', label: string}
     */
    private function inferScope(?string $title, ?string $unitTitle): array
    {
        $combined = mb_strtolower(($title ?? '').' '.($unitTitle ?? ''));

        if (
            str_contains($combined, 'شامل')
            || str_contains($combined, 'نهائي')
            || str_contains($combined, 'تجريبي')
            || str_contains($combined, 'نهاية الفصل')
            || str_contains($combined, 'مراجعة عامة')
        ) {
            return ['key' => 'comprehensive', 'label' => 'اختبار شامل / تجريبي'];
        }

        if (
            str_contains($combined, 'نصف')
            || str_contains($combined, 'منتصف')
            || str_contains($combined, 'ميدترم')
            || str_contains($combined, 'أجزاء')
            || str_contains($combined, 'فصلي')
        ) {
            return ['key' => 'midterm', 'label' => 'اختبار أجزاء المنهج / نصفي'];
        }

        return ['key' => 'unit', 'label' => 'اختبار نهاية الوحدة'];
    }

    private function status(Quiz $quiz, ?QuizAttempt $latest, int $questionCount): string
    {
        if ($questionCount === 0) {
            return 'not_ready';
        }

        if ($latest?->submitted_at === null && $latest?->started_at !== null) {
            return 'in_progress';
        }

        if ($quiz->available_from?->isFuture()) {
            return 'upcoming';
        }

        if ($quiz->available_until?->isPast()) {
            return 'closed';
        }

        if ($latest?->submitted_at !== null) {
            return 'completed';
        }

        return 'available';
    }

    /** @return array<string, mixed> */
    private function attempt(QuizAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'score' => $attempt->score,
            'earned_points' => $attempt->earned_points,
            'total_points' => $attempt->total_points,
            'passed' => (bool) $attempt->passed,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'violations' => (int) $attempt->violations,
            'teacher_feedback' => $attempt->teacher_feedback,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, int|float>
     */
    private function summarize(Collection $items): array
    {
        $quizScores = $items
            ->where('mode', 'electronic')
            ->pluck('attempts')
            ->flatten(1)
            ->filter(fn ($attempt): bool => is_array($attempt) && ($attempt['submitted_at'] ?? null) !== null)
            ->pluck('score')
            ->filter(fn ($score): bool => $score !== null);

        $paperScores = $items
            ->where('mode', 'paper')
            ->pluck('submission')
            ->filter(fn ($sub): bool => is_array($sub) && ($sub['score_percent'] ?? null) !== null)
            ->pluck('score_percent');

        $allScores = $quizScores->concat($paperScores);

        $quizPassedCount = $items
            ->where('mode', 'electronic')
            ->pluck('attempts')
            ->flatten(1)
            ->filter(fn ($attempt): bool => is_array($attempt) && ($attempt['submitted_at'] ?? null) !== null && ($attempt['passed'] ?? false))
            ->count();

        $paperPassedCount = $items
            ->where('mode', 'paper')
            ->filter(fn ($item): bool => ($item['submission']['is_graded'] ?? false) && (($item['submission']['score_percent'] ?? 0) >= 50))
            ->count();

        return [
            'total' => $items->count(),
            'available' => $items->where('status', 'available')->count(),
            'in_progress' => $items->where('status', 'in_progress')->count(),
            'completed' => $items->where('status', 'completed')->count(),
            'passed' => $quizPassedCount + $paperPassedCount,
            'average_score' => $allScores->isEmpty()
                ? 0
                : (int) round((float) $allScores->avg()),
            'electronic_count' => $items->where('mode', 'electronic')->count(),
            'paper_count' => $items->where('mode', 'paper')->count(),
            'unit_count' => $items->where('scope', 'unit')->count(),
            'midterm_count' => $items->where('scope', 'midterm')->count(),
            'comprehensive_count' => $items->where('scope', 'comprehensive')->count(),
        ];
    }
}

