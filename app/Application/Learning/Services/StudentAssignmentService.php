<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Support\Collection;

class StudentAssignmentService
{
    /**
     * Build the student's assignment center from active authorized content.
     * A missing submission is never treated as a zero; status is derived from
     * the actual submission, grade, due date, and lesson progress records.
     *
     * @return array{items: Collection<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function forStudent(User $student): array
    {
        try {
            app(AcademicAlertService::class)->sendMissingHomeworkAlertsForStudent($student);
        } catch (\Throwable) {
            // Keep resilient even if notification evaluation encounters an issue
        }

        $subscriptions = Subscription::active()
            ->where('student_id', $student->id)
            ->with([
                'assignment.subject:id,name',
                'assignment.teacher:id,name',
                'group:id,name',
            ])
            ->get();

        $assignmentIds = $subscriptions->pluck('teaching_assignment_id')->filter()->unique()->values();

        if ($assignmentIds->isEmpty()) {
            return ['items' => collect(), 'summary' => $this->emptySummary()];
        }

        $worksheets = Worksheet::query()
            ->where('type', Worksheet::TYPE_HOMEWORK)
            ->whereHas('unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignmentIds)
                ->where('is_published', true))
            ->with([
                'unit.assignment.subject:id,name',
                'unit.assignment.teacher:id,name',
                'material:id,title',
                'submissions' => fn ($query) => $query
                    ->where('student_id', $student->id)
                    ->latest('submitted_at'),
            ])
            ->latest('due_date')
            ->get();

        $lessonIds = $worksheets->pluck('lesson_id')->filter()->unique();
        $progress = LessonProgress::query()
            ->where('student_id', $student->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        $items = $worksheets->map(function (Worksheet $worksheet) use ($subscriptions, $progress): array {
            $assignment = $worksheet->unit?->assignment;
            $subscription = $subscriptions->firstWhere('teaching_assignment_id', $assignment?->id);
            $submission = $worksheet->submissions->first();
            $lessonProgress = $progress->get($worksheet->lesson_id);
            $isLate = $this->isLate($worksheet, $submission);
            $status = $this->status($worksheet, $submission, $lessonProgress, $isLate);
            $submittedLate = ($submission?->is_late === true) || ($submission?->submitted_at !== null && $isLate);

            return [
                'id' => $worksheet->id,
                'title' => $worksheet->title,
                'subject' => $assignment?->subject?->only(['id', 'name']),
                'teacher' => $assignment?->teacher?->only(['id', 'name']),
                'group' => $subscription?->group?->only(['id', 'name']),
                'group_id' => $subscription?->teaching_group_id,
                'lesson' => $worksheet->material?->only(['id', 'title']),
                'lesson_id' => $worksheet->lesson_id,
                'term_id' => $worksheet->unit?->academic_term_id,
                'due_date' => $worksheet->due_date?->toDateString(),
                'max_score' => $worksheet->max_score,
                'allow_late_submission' => (bool) ($worksheet->allow_late_submission ?? true),
                'max_attempts' => (int) ($worksheet->max_attempts ?? 1),
                'can_submit' => (bool) ($worksheet->requires_submission && (! $isLate || ($worksheet->allow_late_submission ?? true))),
                'status' => $status,
                'is_late' => $isLate,
                'submitted_late' => $submittedLate,
                'late_notice' => $submittedLate
                    ? 'تم التسليم بعد انتهاء الموعد المحدد (تأخر في التسليم)'
                    : ($status === 'late' ? 'تأخرت في تسليم هذا الواجب عن الموعد المحدد' : null),
                'worksheet_file' => filled($worksheet->file_path)
                    ? route('learning.worksheet.download', $worksheet->id)
                    : null,
                'submission' => $submission === null ? null : [
                    'id' => $submission->id,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                    'is_late' => (bool) ($submission->is_late || $submittedLate),
                    'score' => $submission->score,
                    'teacher_feedback' => $submission->teacher_feedback,
                    'graded_at' => $submission->graded_at?->toIso8601String(),
                    'file' => filled($submission->submitted_file_path)
                        ? route('learning.submission.download', $submission->id)
                        : null,
                ],
            ];
        })->values();

        $summary = $this->emptySummary();
        foreach ($items as $item) {
            $summary[$item['status']] = ($summary[$item['status']] ?? 0) + 1;
            if ($item['submitted_late'] && $item['status'] !== 'late') {
                $summary['late']++;
            }
            $summary['total']++;
        }

        return ['items' => $items, 'summary' => $summary];
    }

    /** @return array<string, int> */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'submitted' => 0,
            'late' => 0,
            'graded' => 0,
        ];
    }

    private function isLate(Worksheet $worksheet, ?WorksheetSubmission $submission): bool
    {
        if ($worksheet->due_date === null) {
            return false;
        }

        if ($submission?->submitted_at !== null) {
            return (bool) $submission->is_late || $submission->submitted_at->startOfDay()->isAfter($worksheet->due_date);
        }

        return $worksheet->due_date->isBefore(today());
    }

    private function status(
        Worksheet $worksheet,
        ?WorksheetSubmission $submission,
        ?LessonProgress $progress,
        bool $isLate,
    ): string {
        if ($submission?->graded_at !== null) {
            return 'graded';
        }

        if ($submission?->submitted_at !== null) {
            return 'submitted';
        }

        if ($isLate) {
            return 'late';
        }

        if ($progress !== null && $progress->watched_seconds > 0) {
            return 'in_progress';
        }

        return 'pending';
    }
}
