<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Illuminate\Support\Collection;

class TeacherGradebookService
{
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
