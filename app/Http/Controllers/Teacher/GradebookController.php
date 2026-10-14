<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Application\Learning\Services\TeacherGradebookService;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Http\Controllers\Controller;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradebookController extends Controller
{
    public function index(Request $request, TeacherGradebookService $gradebook): Response
    {
        $assignmentId = $request->integer('assignment') ?: null;
        $page = max(1, $request->integer('page', 1));
        $perPage = min(max(1, $request->integer('per_page', TeacherGradebookService::DEFAULT_PAGE_SIZE)), TeacherGradebookService::MAX_PAGE_SIZE);
        $result = $gradebook->forTeacherPage(Auth::user(), $assignmentId, $page, $perPage);
        $result['rows']->withQueryString();

        return Inertia::render('Teacher/Gradebook', [
            'assignments' => $result['assignments'],
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'selectedAssignment' => $assignmentId,
        ]);
    }

    public function export(Request $request, TeacherGradebookService $gradebook): StreamedResponse
    {
        $assignmentId = $request->integer('assignment') ?: null;
        $result = $gradebook->forTeacher(Auth::user(), $assignmentId);
        $filename = $assignmentId === null
            ? 'gradebook.csv'
            : 'gradebook-assignment-'.$assignmentId.'.csv';

        return response()->streamDownload(function () use ($result): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Student',
                'Email',
                'Assessment',
                'Type',
                'Subject',
                'Earned',
                'Maximum',
                'Percentage',
                'Status',
                'Feedback',
                'Assessed At',
            ]);

            foreach ($result['rows'] as $row) {
                fputcsv($handle, [
                    $this->csvCell($row['student']['name'] ?? null),
                    $this->csvCell($row['student']['email'] ?? null),
                    $this->csvCell($row['title'] ?? null),
                    $this->csvCell($row['kind_label'] ?? $row['kind'] ?? null),
                    $this->csvCell($row['subject']['name'] ?? null),
                    $row['earned'],
                    $row['maximum'],
                    $row['percentage'],
                    $this->csvCell($row['status'] ?? null),
                    $this->csvCell($row['feedback'] ?? null),
                    $this->csvCell($row['assessed_at'] ?? null),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function updateQuizFeedback(Request $request, int $attemptId): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $attempt = QuizAttempt::query()
            ->with(['quiz.unit.assignment', 'user.parentLinks.parent'])
            ->findOrFail($attemptId);

        abort_unless($attempt->quiz?->unit?->assignment?->teacher_id === Auth::id(), 404);
        abort_unless($attempt->submitted_at !== null, 422, 'لا يمكن إضافة ملاحظات قبل تسليم الاختبار.');

        $feedback = isset($validated['teacher_feedback'])
            ? (trim($validated['teacher_feedback']) ?: null)
            : null;
        $previousFeedback = $attempt->teacher_feedback;

        $feedbackChanged = false;

        if ($feedback !== $previousFeedback) {
            $feedbackChanged = QuizAttempt::query()
                ->whereKey($attempt->id)
                ->when(
                    $previousFeedback === null,
                    fn ($query) => $query->whereNull('teacher_feedback'),
                    fn ($query) => $query->where('teacher_feedback', $previousFeedback),
                )
                ->update(['teacher_feedback' => $feedback]) === 1;
        }

        if ($feedbackChanged && $feedback !== null) {
            $quizTitle = $attempt->quiz?->title ?? 'الاختبار';
            $student = $attempt->user;

            $student?->notify(new GenericDatabaseNotification([
                'title' => 'تم نشر ملاحظة على الاختبار 📝',
                'message' => "أضاف المعلم ملاحظة على اختبار '{$quizTitle}': {$feedback}",
                'link' => route('student.assessments'),
            ]));

            foreach ($student?->parentLinks ?? [] as $link) {
                if ($link->verified_at && $link->parent) {
                    $link->parent->notify(new GenericDatabaseNotification([
                        'title' => 'ملاحظة جديدة على اختبار الطالب',
                        'message' => "أضاف المعلم ملاحظة على اختبار {$student->name} '{$quizTitle}': {$feedback}",
                        'link' => route('parent.dashboard', ['student_id' => $student->id]),
                    ]));
                }
            }
        }

        return back()->with('success', 'تم حفظ ملاحظات الاختبار.');
    }

    private function csvCell(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        return preg_match('/^\s*[=+\-@]/u', $value) === 1
            ? "'{$value}"
            : $value;
    }
}
