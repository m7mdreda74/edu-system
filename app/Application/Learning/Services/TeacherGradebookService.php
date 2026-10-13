<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherGradebookService
{
    public const DEFAULT_PAGE_SIZE = 25;

    public const MAX_PAGE_SIZE = 100;

    /**
     * Build one read-only view over submitted homework and submitted quizzes.
     * Worksheets without a submission are intentionally absent rather than
     * being converted to zeroes.
     *
     * @return array{assignments: Collection<int, array<string, mixed>>, rows: Collection<int, array<string, mixed>>, summary: array<string, int|float|null>}
     */
    public function forTeacher(User $teacher, ?int $assignmentId = null): array
    {
        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->with([
                'subject:id,name',
                'gradeLevel:id,key,name',
            ])
            ->orderBy('id')
            ->get();

        $assignmentIds = $assignments->pluck('id');

        if ($assignmentId !== null && ! $assignmentIds->contains($assignmentId)) {
            abort(404);
        }

        if ($assignmentIds->isEmpty()) {
            return [
                'assignments' => collect(),
                'rows' => collect(),
                'summary' => $this->summary(collect()),
            ];
        }

        $scopedAssignmentIds = $assignmentId === null
            ? $assignmentIds
            : collect([$assignmentId]);

        $rows = collect();

        Worksheet::query()
            ->whereIn('type', [Worksheet::TYPE_HOMEWORK, Worksheet::TYPE_PAPER_EXAM])
            ->whereHas('unit', fn ($query) => $query->whereIn('teaching_assignment_id', $scopedAssignmentIds))
            ->with([
                'unit.assignment.subject:id,name',
                'submissions' => fn ($query) => $query
                    ->whereNotNull('submitted_at')
                    ->with('student:id,name,email')
                    ->latest('submitted_at'),
            ])
            ->get()
            ->each(function (Worksheet $worksheet) use ($rows): void {
                foreach ($worksheet->submissions as $submission) {
                    $rows->push($this->worksheetRow($worksheet, $submission));
                }
            });

        Quiz::query()
            ->whereHas('unit', fn ($query) => $query->whereIn('teaching_assignment_id', $scopedAssignmentIds))
            ->with([
                'unit.assignment.subject:id,name',
                'attempts' => fn ($query) => $query
                    ->whereNotNull('submitted_at')
                    ->with('user:id,name,email')
                    ->latest('submitted_at'),
            ])
            ->get()
            ->each(function (Quiz $quiz) use ($rows): void {
                // A student can have several permitted attempts. The gradebook
                // presents only the latest submitted attempt, preventing one
                // assessment from being double-counted in totals.
                $quiz->attempts
                    ->groupBy('user_id')
                    ->each(fn (Collection $attempts) => $rows->push(
                        $this->quizRow($quiz, $attempts->sortByDesc('submitted_at')->first()),
                    ));
            });

        $rows = $rows
            ->sortByDesc('assessed_at')
            ->values();

        return [
            'assignments' => $assignments
                ->whereIn('id', $scopedAssignmentIds)
                ->map(fn (TeachingAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'subject' => $assignment->subject?->only(['id', 'name']),
                    'grade' => $assignment->gradeLevel?->only(['id', 'key', 'name']),
                ])
                ->values(),
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ];
    }

    /**
     * Build the on-screen Gradebook page from a database-level union.
     *
     * Worksheets and quizzes live in separate tables, so the union normalizes
     * both sources before pagination. This keeps the dashboard from loading
     * every historical submission and attempt into PHP for each page visit.
     *
     * @return array{assignments: Collection<int, array<string, mixed>>, rows: LengthAwarePaginator, summary: array<string, int|float|null>}
     */
    public function forTeacherPage(
        User $teacher,
        ?int $assignmentId = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PAGE_SIZE,
    ): array {
        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->with([
                'subject:id,name',
                'gradeLevel:id,key,name',
            ])
            ->orderBy('id')
            ->get();

        $assignmentIds = $assignments->pluck('id');

        if ($assignmentId !== null && ! $assignmentIds->contains($assignmentId)) {
            abort(404);
        }

        $scopedAssignmentIds = $assignmentId === null
            ? $assignmentIds
            : collect([$assignmentId]);
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), self::MAX_PAGE_SIZE);

        if ($scopedAssignmentIds->isEmpty()) {
            return [
                'assignments' => collect(),
                'rows' => new LengthAwarePaginator([], 0, $perPage, $page),
                'summary' => $this->summary(collect()),
            ];
        }

        $query = $this->databaseRowsQuery($scopedAssignmentIds);
        $summary = $this->databaseSummary($query);
        $rows = $query
            ->orderByDesc('assessed_at')
            ->orderByDesc('source_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows->setCollection($rows->getCollection()
            ->map(fn (object $row): array => $this->databaseRow($row))
            ->values());

        return [
            'assignments' => $assignments
                ->whereIn('id', $scopedAssignmentIds)
                ->map(fn (TeachingAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'subject' => $assignment->subject?->only(['id', 'name']),
                    'grade' => $assignment->gradeLevel?->only(['id', 'key', 'name']),
                ])
                ->values(),
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /** @param Collection<int, int|string> $assignmentIds */
    private function databaseRowsQuery(Collection $assignmentIds): Builder
    {
        $worksheetRows = DB::table('worksheet_submissions as submission')
            ->join('worksheets as worksheet', 'worksheet.id', '=', 'submission.worksheet_id')
            ->join('curriculum_units as unit', 'unit.id', '=', 'worksheet.curriculum_unit_id')
            ->join('teaching_assignments as assignment', 'assignment.id', '=', 'unit.teaching_assignment_id')
            ->join('subjects as subject', 'subject.id', '=', 'assignment.subject_id')
            ->join('users as student', 'student.id', '=', 'submission.student_id')
            ->whereIn('assignment.id', $assignmentIds->all())
            ->whereNotNull('submission.submitted_at')
            ->select([
                'submission.id as source_id',
                DB::raw("'worksheet' as source_type"),
                'worksheet.id as assessment_id',
                'assignment.id as assignment_id',
                'worksheet.type as worksheet_type',
                DB::raw('NULL as quiz_score'),
                'worksheet.title',
                'student.id as student_id',
                'student.name as student_name',
                'student.email as student_email',
                'subject.id as subject_id',
                'subject.name as subject_name',
                'submission.score as earned',
                'worksheet.max_score as maximum',
                DB::raw("CASE WHEN submission.graded_at IS NULL THEN 'needs_grading' ELSE 'graded' END as status"),
                'submission.teacher_feedback as feedback',
                'submission.submitted_at as assessed_at',
            ]);

        $quizRows = DB::table('quiz_attempts as attempt')
            ->join('quizzes as quiz', 'quiz.id', '=', 'attempt.quiz_id')
            ->join('curriculum_units as unit', 'unit.id', '=', 'quiz.curriculum_unit_id')
            ->join('teaching_assignments as assignment', 'assignment.id', '=', 'unit.teaching_assignment_id')
            ->join('subjects as subject', 'subject.id', '=', 'assignment.subject_id')
            ->join('users as student', 'student.id', '=', 'attempt.user_id')
            ->whereIn('assignment.id', $assignmentIds->all())
            ->whereNotNull('attempt.submitted_at')
            ->whereNotExists(function (Builder $newer): void {
                $newer
                    ->selectRaw('1')
                    ->from('quiz_attempts as newer_attempt')
                    ->whereColumn('newer_attempt.quiz_id', 'attempt.quiz_id')
                    ->whereColumn('newer_attempt.user_id', 'attempt.user_id')
                    ->whereNotNull('newer_attempt.submitted_at')
                    ->where(function (Builder $tieBreaker): void {
                        $tieBreaker
                            ->whereColumn('newer_attempt.submitted_at', '>', 'attempt.submitted_at')
                            ->orWhere(function (Builder $sameTimestamp): void {
                                $sameTimestamp
                                    ->whereColumn('newer_attempt.submitted_at', '=', 'attempt.submitted_at')
                                    ->whereColumn('newer_attempt.id', '>', 'attempt.id');
                            });
                    });
            })
            ->select([
                'attempt.id as source_id',
                DB::raw("'quiz' as source_type"),
                'quiz.id as assessment_id',
                'assignment.id as assignment_id',
                DB::raw('NULL as worksheet_type'),
                'attempt.score as quiz_score',
                'quiz.title',
                'student.id as student_id',
                'student.name as student_name',
                'student.email as student_email',
                'subject.id as subject_id',
                'subject.name as subject_name',
                'attempt.earned_points as earned',
                'attempt.total_points as maximum',
                DB::raw("'graded' as status"),
                DB::raw('NULL as feedback'),
                'attempt.submitted_at as assessed_at',
            ]);

        return DB::query()->fromSub(
            $worksheetRows->unionAll($quizRows),
            'gradebook_rows',
        );
    }

    /** @return array<string, int|float|null> */
    private function databaseSummary(Builder $query): array
    {
        $summary = (clone $query)
            ->selectRaw(<<<'SQL'
                COUNT(*) as assessments,
                COUNT(DISTINCT student_id) as students,
                SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) as graded,
                SUM(CASE WHEN status = 'needs_grading' THEN 1 ELSE 0 END) as needs_grading,
                SUM(CASE WHEN earned IS NOT NULL AND maximum IS NOT NULL AND maximum > 0 THEN 1 ELSE 0 END) as scored_assessments,
                COALESCE(SUM(CASE WHEN earned IS NOT NULL AND maximum IS NOT NULL AND maximum > 0 THEN earned ELSE 0 END), 0) as earned_points,
                COALESCE(SUM(CASE WHEN earned IS NOT NULL AND maximum IS NOT NULL AND maximum > 0 THEN maximum ELSE 0 END), 0) as maximum_points
            SQL)
            ->first();

        $maximum = (int) ($summary->maximum_points ?? 0);

        return [
            'assessments' => (int) ($summary->assessments ?? 0),
            'students' => (int) ($summary->students ?? 0),
            'graded' => (int) ($summary->graded ?? 0),
            'needs_grading' => (int) ($summary->needs_grading ?? 0),
            'scored_assessments' => (int) ($summary->scored_assessments ?? 0),
            'earned_points' => (int) ($summary->earned_points ?? 0),
            'maximum_points' => $maximum,
            'overall_percentage' => $maximum > 0
                ? round(((int) ($summary->earned_points ?? 0) / $maximum) * 100, 1)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function databaseRow(object $row): array
    {
        $isQuiz = $row->source_type === 'quiz';
        $maximum = $row->maximum !== null && (int) $row->maximum > 0
            ? (int) $row->maximum
            : null;
        $earned = $row->earned !== null && (! $isQuiz || $maximum !== null)
            ? (int) $row->earned
            : null;
        $kind = $isQuiz
            ? 'quiz'
            : ($row->worksheet_type === Worksheet::TYPE_PAPER_EXAM ? 'paper_exam' : 'homework');
        $kindLabel = match ($kind) {
            'paper_exam' => 'اختبار ورقي',
            'quiz' => 'اختبار إلكتروني',
            default => 'واجب',
        };

        return [
            'id' => $row->source_type.'-'.$row->source_id,
            'assessment_id' => (int) $row->assessment_id,
            'assignment_id' => (int) $row->assignment_id,
            'kind' => $kind,
            'kind_label' => $kindLabel,
            'title' => $row->title,
            'student' => [
                'id' => (int) $row->student_id,
                'name' => $row->student_name,
                'email' => $row->student_email,
            ],
            'subject' => [
                'id' => (int) $row->subject_id,
                'name' => $row->subject_name,
            ],
            'earned' => $earned,
            'maximum' => $maximum,
            'percentage' => $maximum === null
                ? ($isQuiz && $row->quiz_score !== null ? (int) $row->quiz_score : null)
                : $this->percentage($earned, $maximum),
            'status' => $row->status,
            'feedback' => $row->feedback,
            'assessed_at' => $row->assessed_at === null
                ? null
                : Carbon::parse((string) $row->assessed_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function worksheetRow(Worksheet $worksheet, WorksheetSubmission $submission): array
    {
        $assignment = $worksheet->unit?->assignment;
        $maximum = $worksheet->max_score !== null ? (int) $worksheet->max_score : null;
        $earned = $submission->score !== null ? (int) $submission->score : null;

        return [
            'id' => 'worksheet-'.$submission->id,
            'assessment_id' => $worksheet->id,
            'assignment_id' => $assignment?->id,
            'kind' => $worksheet->type === Worksheet::TYPE_PAPER_EXAM ? 'paper_exam' : 'homework',
            'kind_label' => $worksheet->type === Worksheet::TYPE_PAPER_EXAM ? 'اختبار ورقي' : 'واجب',
            'title' => $worksheet->title,
            'student' => $submission->student?->only(['id', 'name', 'email']),
            'subject' => $assignment?->subject?->only(['id', 'name']),
            'earned' => $earned,
            'maximum' => $maximum,
            'percentage' => $this->percentage($earned, $maximum),
            'status' => $submission->graded_at !== null ? 'graded' : 'needs_grading',
            'feedback' => $submission->teacher_feedback,
            'assessed_at' => $submission->submitted_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function quizRow(Quiz $quiz, QuizAttempt $attempt): array
    {
        $assignment = $quiz->unit?->assignment;
        $maximum = $attempt->total_points > 0 ? (int) $attempt->total_points : null;
        $earned = $maximum !== null ? (int) $attempt->earned_points : null;

        return [
            'id' => 'quiz-'.$attempt->id,
            'assessment_id' => $quiz->id,
            'assignment_id' => $assignment?->id,
            'kind' => 'quiz',
            'kind_label' => 'اختبار إلكتروني',
            'title' => $quiz->title,
            'student' => $attempt->user?->only(['id', 'name', 'email']),
            'subject' => $assignment?->subject?->only(['id', 'name']),
            'earned' => $earned,
            'maximum' => $maximum,
            'percentage' => $maximum === null ? $attempt->score : $this->percentage($earned, $maximum),
            'status' => 'graded',
            'feedback' => null,
            'assessed_at' => $attempt->submitted_at?->toIso8601String(),
        ];
    }

    private function percentage(?int $earned, ?int $maximum): ?float
    {
        if ($earned === null || $maximum === null || $maximum <= 0) {
            return null;
        }

        return round(($earned / $maximum) * 100, 1);
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function summary(Collection $rows): array
    {
        $scored = $rows->filter(fn (array $row): bool => $row['earned'] !== null && $row['maximum'] !== null && $row['maximum'] > 0);
        $maximum = (int) $scored->sum('maximum');
        $earned = (int) $scored->sum('earned');

        return [
            'assessments' => $rows->count(),
            'students' => $rows->pluck('student.id')->filter()->unique()->count(),
            'graded' => $rows->where('status', 'graded')->count(),
            'needs_grading' => $rows->where('status', 'needs_grading')->count(),
            'scored_assessments' => $scored->count(),
            'earned_points' => $earned,
            'maximum_points' => $maximum,
            'overall_percentage' => $maximum > 0 ? round(($earned / $maximum) * 100, 1) : null,
        ];
    }
}
