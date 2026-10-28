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

    public function quizAttemptDetails(int $attemptId): \Illuminate\Http\JsonResponse
    {
        $attempt = QuizAttempt::query()
            ->with([
                'quiz.unit.assignment',
                'quiz.questions.options',
                'user:id,name,email',
            ])
            ->findOrFail($attemptId);

        abort_unless($attempt->quiz?->unit?->assignment?->teacher_id === Auth::id(), 404);
        abort_unless($attempt->submitted_at !== null, 422, 'لم يتم تسليم هذا الاختبار بعد.');

        $answers = $attempt->answers ?? [];

        $questions = $attempt->quiz->questions->map(function ($question) use ($answers, $attempt): array {
            $rawAnswer = $answers[$question->id] ?? null;
            $isEssay = $question->type === 'essay';

            $fileDownloadUrl = null;
            $hasPaperFile = false;
            $fileName = null;

            if ($isEssay && is_array($rawAnswer) && filled($rawAnswer['file_path'] ?? null)) {
                $hasPaperFile = true;
                $fileName = $rawAnswer['file_name'] ?? 'حل_ورقي';
                $fileDownloadUrl = route('quizzes.attempts.essay-file', [
                    'attemptId' => $attempt->id,
                    'questionId' => $question->id,
                ]);
            }

            return [
                'id' => $question->id,
                'order' => $question->order,
                'question_text' => $question->question_text,
                'type' => $question->type,
                'points' => max(1, (int) $question->points),
                'allow_file_upload' => (bool) $question->allow_file_upload,
                'model_answer' => $question->model_answer,
                'options' => $question->options->map(fn ($opt) => [
                    'id' => $opt->id,
                    'text' => $opt->option_text,
                    'is_correct' => (bool) $opt->is_correct,
                ])->values(),
                'student_answer' => $rawAnswer,
                'is_essay' => $isEssay,
                'has_paper_file' => $hasPaperFile,
                'fileName' => $fileName,
                'file_download_url' => $fileDownloadUrl,
                'earned_points' => is_array($rawAnswer) ? ($rawAnswer['earned_points'] ?? null) : null,
                'teacher_feedback' => is_array($rawAnswer) ? ($rawAnswer['feedback'] ?? null) : null,
            ];
        })->values();

        return response()->json([
            'attempt' => [
                'id' => $attempt->id,
                'quiz_id' => $attempt->quiz_id,
                'quiz_title' => $attempt->quiz?->title,
                'passing_score' => $attempt->quiz?->passing_score,
                'student' => $attempt->user?->only(['id', 'name', 'email']),
                'score' => $attempt->score,
                'earned_points' => $attempt->earned_points,
                'total_points' => $attempt->total_points,
                'status' => $attempt->status ?? 'graded',
                'teacher_feedback' => $attempt->teacher_feedback,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            ],
            'questions' => $questions,
        ]);
    }

    public function gradeQuizAttempt(Request $request, int $attemptId): RedirectResponse
    {
        $attempt = QuizAttempt::query()
            ->with([
                'quiz.unit.assignment',
                'quiz.questions.options',
                'user.parentLinks.parent',
            ])
            ->findOrFail($attemptId);

        abort_unless($attempt->quiz?->unit?->assignment?->teacher_id === Auth::id(), 404);
        abort_unless($attempt->submitted_at !== null, 422, 'لا يمكن تصحيح الاختبار قبل تسليمه.');

        $validated = $request->validate([
            'scores'             => ['required', 'array'],
            'scores.*'           => ['required', 'numeric', 'min:0'],
            'teacher_feedback'   => ['nullable', 'string', 'max:1000'],
            'question_feedback'  => ['nullable', 'array'],
            'question_feedback.*'=> ['nullable', 'string', 'max:500'],
        ]);

        $quiz = $attempt->quiz;
        $questions = $quiz->questions;
        $answers = $attempt->answers ?? [];
        $scores = $validated['scores'];
        $questionFeedback = $validated['question_feedback'] ?? [];

        $totalEarnedPoints = 0;
        $totalMaxPoints = (int) $questions->sum(fn ($q) => max(1, (int) $q->points));

        foreach ($questions as $question) {
            $maxPoints = max(1, (int) $question->points);
            $qId = (int) $question->id;

            if ($question->type === 'essay') {
                $awarded = round((float) ($scores[$qId] ?? 0), 2);
                if ($awarded > $maxPoints) {
                    return back()->with('error', "درجة السؤال '{$question->order}' لا يمكن أن تتجاوز {$maxPoints} درجات.");
                }

                $existing = is_array($answers[$qId] ?? null) ? $answers[$qId] : [];
                $existing['type'] = 'essay';
                $existing['earned_points'] = $awarded;
                $existing['max_points'] = $maxPoints;
                $existing['feedback'] = trim((string) ($questionFeedback[$qId] ?? '')) ?: null;
                $answers[$qId] = $existing;

                $totalEarnedPoints += $awarded;
            } else {
                $autoScore = 0;
                if (isset($scores[$qId])) {
                    $awarded = round((float) $scores[$qId], 2);
                    $autoScore = min($maxPoints, max(0, $awarded));
                } elseif (isset($answers[$qId]['earned_points'])) {
                    $autoScore = (float) $answers[$qId]['earned_points'];
                }

                if (isset($answers[$qId]) && is_array($answers[$qId])) {
                    $answers[$qId]['earned_points'] = $autoScore;
                    if (isset($questionFeedback[$qId])) {
                        $answers[$qId]['feedback'] = trim((string) $questionFeedback[$qId]) ?: null;
                    }
                }
                $totalEarnedPoints += $autoScore;
            }
        }

        $scorePercent = (int) round(($totalEarnedPoints / max(1, $totalMaxPoints)) * 100);
        $passed = $scorePercent >= $quiz->passing_score;

        $generalFeedback = isset($validated['teacher_feedback'])
            ? (trim($validated['teacher_feedback']) ?: null)
            : null;

        $attempt->update([
            'score'            => $scorePercent,
            'earned_points'    => (int) round($totalEarnedPoints),
            'total_points'     => $totalMaxPoints,
            'passed'           => $passed,
            'status'           => 'graded',
            'graded_at'        => now(),
            'teacher_feedback' => $generalFeedback,
            'answers'          => $answers,
        ]);

        $student = $attempt->user;
        $quizTitle = $quiz->title ?? 'الاختبار';
        $message = "تم تصحيح اختبار '{$quizTitle}' وحصلت على {$attempt->earned_points}/{$totalMaxPoints} ({$scorePercent}%).";
        if ($generalFeedback) {
            $message .= " ملاحظة المعلم: {$generalFeedback}";
        }

        $student?->notify(new GenericDatabaseNotification([
            'title' => 'تم تصحيح الاختبار ورصد الدرجة 📝',
            'message' => $message,
            'link' => route('student.quiz', $quiz->id),
        ]));

        foreach ($student?->parentLinks ?? [] as $link) {
            if ($link->verified_at && $link->parent) {
                $link->parent->notify(new GenericDatabaseNotification([
                    'title' => 'تم تصحيح اختبار الطالب',
                    'message' => "ابنكم الطالب {$student->name} حصل على {$attempt->earned_points}/{$totalMaxPoints} ({$scorePercent}%) في '{$quizTitle}'."
                        . ($generalFeedback ? " ملاحظة المعلم: {$generalFeedback}" : ''),
                    'link' => route('parent.dashboard', ['student_id' => $student->id]),
                ]));
            }
        }

        return back()->with('success', 'تم رصد وتصحيح درجات الاختبار بنجاح وإشعار الطالب وولي أمره.');
    }

    private function csvCell(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        return preg_match('/^\s*[=+\-@]/u', $value) === 1
            ? "'{$value}"
            : $value;
    }
}
