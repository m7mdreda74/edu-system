<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Learning\Services\StudentAssignmentService;
use App\Application\Quiz\Services\StudentAssessmentService;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function index(Request $request, StudentAssessmentService $assessments, StudentAssignmentService $assignments): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $assessmentData = $assessments->forStudent($user);
        $assignmentData = $assignments->forStudent($user);

        return Inertia::render('Student/Assessments', [
            'items' => $assessmentData['items'],
            'summary' => $assessmentData['summary'],
            'assignments' => $assignmentData['items'],
            'assignmentSummary' => $assignmentData['summary'],
            'initialTab' => $request->query('tab', 'all'),
        ]);
    }

    /**
     * Submit or re-submit a student's handwritten / PDF solution for a paper/essay exam.
     */
    public function submitPaperExam(Request $request, int $worksheetId): RedirectResponse
    {
        $request->validate([
            'submitted_file' => ['required', 'file', 'mimes:pdf,docx,png,jpg,jpeg', 'max:10240'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $worksheet = Worksheet::with('unit')->findOrFail($worksheetId);

        $assignmentId = $worksheet->unit?->teaching_assignment_id;

        abort_if(
            ! $assignmentId || ! $user->hasActiveSubscriptionToAssignment((int) $assignmentId),
            403,
            'غير مصرح لك بتسليم هذا التقييم.'
        );

        abort_if(! $worksheet->requires_submission, 403, 'هذا التقييم لا يتطلب تسليماً.');
        abort_if($worksheet->is_active === false, 403, 'هذا التقييم معلق حالياً.');

        if ($worksheet->available_from !== null && now()->isBefore($worksheet->available_from)) {
            abort(403, 'عفواً، لم يحن موعد بدء هذا التقييم بعد (يبدأ في ' . $worksheet->available_from->format('Y-m-d H:i') . ').');
        }

        $isLate = $worksheet->due_date !== null && now()->startOfDay()->isAfter($worksheet->due_date);

        if ($isLate && ! ($worksheet->allow_late_submission ?? true)) {
            abort(403, 'عفواً، انتهى موعد التسليم المحدد ولا يُسمح بالتسليم بعد انتهاء الوقت.');
        }

        $existing = WorksheetSubmission::where('worksheet_id', $worksheet->id)
            ->where('student_id', $user->id)
            ->first();

        $maxAttempts = (int) ($worksheet->max_attempts > 0 ? $worksheet->max_attempts : 1);
        if ($existing && $existing->isGraded() && $maxAttempts <= 1) {
            abort(403, 'تم رصد وتصحيح درجتك بالفعل ولا يمكن إعادة التسليم وفقاً لإعدادات المعلم.');
        }

        $path = $request->file('submitted_file')->store('submissions', 'local');

        WorksheetSubmission::updateOrCreate(
            ['worksheet_id' => $worksheet->id, 'student_id' => $user->id],
            [
                'submitted_file_path' => 'private://'.$path,
                'submitted_at' => now(),
                'is_late' => $isLate,
                'score' => null,
                'teacher_feedback' => null,
                'graded_at' => null,
            ],
        );

        $message = $isLate
            ? 'تم التسليم بنجاح (ملاحظة: تم التسليم بعد الموعد المحدد).'
            : 'تم التسليم بنجاح.';

        return back()->with('success', $message);
    }
}
