<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\Worksheet;
use App\Domain\Course\Models\WorksheetSubmission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorksheetController extends Controller
{
    public function index(int $courseId): Response
    {
        $course = Course::findOrFail($courseId);
        abort_if($course->teacher_id !== auth()->id(), 403, 'غير مصرح.');

        $worksheets = Worksheet::where('course_id', $courseId)
            ->with(['lesson:id,title'])
            ->withCount('submissions')
            ->get();

        $lessons = $course->lessons()->get(['id', 'title']);

        // Load submissions needing grading
        $submissions = WorksheetSubmission::whereIn('worksheet_id', $worksheets->pluck('id'))
            ->with(['worksheet:id,title,max_score', 'student:id,name,email'])
            ->latest('submitted_at')
            ->get();

        return Inertia::render('Teacher/Worksheets', [
            'course'      => $course,
            'worksheets'  => $worksheets,
            'lessons'     => $lessons,
            'submissions' => $submissions,
        ]);
    }

    public function store(Request $request, int $courseId): RedirectResponse
    {
        $course = Course::findOrFail($courseId);
        abort_if($course->teacher_id !== auth()->id(), 403, 'غير مصرح.');

        $validated = $request->validate([
            'lesson_id'           => ['nullable', 'exists:course_lessons,id'],
            'title'               => ['required', 'string', 'max:255'],
            'file'                => ['required', 'file', 'mimes:pdf,docx,png,jpg,jpeg', 'max:10240'], // 10MB
            'type'                => ['required', 'string', 'in:study,homework'],
            'requires_submission' => ['required', 'boolean'],
            'due_date'            => ['nullable', 'date'],
            'max_score'           => ['nullable', 'integer', 'min:1'],
        ]);

        // Upload file
        $path = $request->file('file')->store('worksheets', 'public');
        $validated['file_path'] = '/storage/' . $path;
        $validated['course_id'] = $course->id;

        unset($validated['file']);

        Worksheet::create($validated);

        return back()->with('success', 'تم رفع ملف الشيت/الواجب بنجاح.');
    }

    public function gradeSubmission(Request $request, int $submissionId): RedirectResponse
    {
        $submission = WorksheetSubmission::with('worksheet.course')->findOrFail($submissionId);
        abort_if($submission->worksheet->course->teacher_id !== auth()->id(), 403, 'غير مصرح.');

        $validated = $request->validate([
            'score'            => ['required', 'integer', 'min:0', 'max:' . ($submission->worksheet->max_score ?? 100)],
            'teacher_feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission->update([
            'score'            => $validated['score'],
            'teacher_feedback' => $validated['teacher_feedback'],
            'graded_at'        => now(),
        ]);

        // Send notification to student
        $student = $submission->student;
        if ($student) {
            $student->notify(new \App\Notifications\GenericDatabaseNotification([
                'title'   => 'تم تصحيح الواجب 📝',
                'message' => "تم تصحيح واجبك في '{$submission->worksheet->title}' وحصلت على درجة {$validated['score']}/{$submission->worksheet->max_score}.",
                'link'    => route('student.learn', ['slug' => $submission->worksheet->course->slug]),
            ]));
        }

        return back()->with('success', 'تم حفظ الدرجة والتقييم بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $worksheet = Worksheet::with('course')->findOrFail($id);
        abort_if($worksheet->course->teacher_id !== auth()->id(), 403, 'غير مصرح.');

        $worksheet->delete();

        return back()->with('success', 'تم حذف ملف الشيت/الواجب بنجاح.');
    }
}
