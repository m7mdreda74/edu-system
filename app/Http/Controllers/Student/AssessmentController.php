<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

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
    public function index(StudentAssessmentService $assessments): Response
    {
        return Inertia::render('Student/Assessments', $assessments->forStudent(Auth::user()));
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

        $worksheet = Worksheet::where('type', Worksheet::TYPE_PAPER_EXAM)
            ->with('unit')
            ->findOrFail($worksheetId);

        $assignmentId = $worksheet->unit?->teaching_assignment_id;

        abort_if(
            ! $assignmentId || ! $user->hasActiveSubscriptionToAssignment((int) $assignmentId),
            403,
            'غير مصرح لك بتسليم هذا الاختبار.'
        );

        abort_if(! $worksheet->requires_submission, 403, 'هذا الاختبار لا يتطلب تسليماً.');

        $isLate = $worksheet->due_date !== null && now()->startOfDay()->isAfter($worksheet->due_date);
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
            ? 'تم تسليم إجابة الاختبار بنجاح (ملاحظة: تم التسليم متأخراً بعد انتهاء الموعد المحدد).'
            : 'تم تسليم إجابة الاختبار المقالي بنجاح.';

        return back()->with('success', $message);
    }
}
