<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Http\Controllers\Controller;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Study sheets and homework, published per teaching group and graded here.
 */
class WorksheetController extends Controller
{
    public function index(int $groupId): Response
    {
        $group = $this->ownedGroupOrFail($groupId);

        $worksheets = Worksheet::where('teaching_group_id', $group->id)
            ->with(['material:id,title'])
            ->withCount('submissions')
            ->get();

        return Inertia::render('Teacher/Worksheets', [
            'group' => [
                'id'      => $group->id,
                'name'    => $group->name,
                'subject' => $group->assignment?->subject?->only(['id', 'name']),
            ],
            'worksheets'  => $worksheets,
            'materials'   => $group->materials()->get(['id', 'title']),
            'submissions' => WorksheetSubmission::whereIn('worksheet_id', $worksheets->pluck('id'))
                ->with(['worksheet:id,title,max_score', 'student:id,name,email'])
                ->latest('submitted_at')
                ->get(),
        ]);
    }

    public function store(Request $request, int $groupId): RedirectResponse
    {
        $group = $this->ownedGroupOrFail($groupId);

        $validated = $request->validate([
            'lesson_id'           => ['nullable', 'exists:group_materials,id'],
            'title'               => ['required', 'string', 'max:255'],
            'file'                => ['required', 'file', 'mimes:pdf,docx,png,jpg,jpeg', 'max:10240'],
            'type'                => ['required', 'string', 'in:study,homework'],
            'requires_submission' => ['required', 'boolean'],
            'due_date'            => ['nullable', 'date'],
            'max_score'           => ['nullable', 'integer', 'min:1'],
        ]);

        $validated['file_path']         = '/storage/' . $request->file('file')->store('worksheets', 'public');
        $validated['teaching_group_id'] = $group->id;

        unset($validated['file']);

        Worksheet::create($validated);

        return back()->with('success', 'تم رفع ملف الشيت/الواجب بنجاح.');
    }

    public function gradeSubmission(Request $request, int $submissionId): RedirectResponse
    {
        $submission = WorksheetSubmission::with('worksheet.group.assignment')->findOrFail($submissionId);

        $this->assertOwns($submission->worksheet?->group);

        $validated = $request->validate([
            'score'            => ['required', 'integer', 'min:0', 'max:' . ($submission->worksheet->max_score ?? 100)],
            'teacher_feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission->update([
            'score'            => $validated['score'],
            'teacher_feedback' => $validated['teacher_feedback'] ?? null,
            'graded_at'        => now(),
        ]);

        $submission->student?->notify(new GenericDatabaseNotification([
            'title'   => 'تم تصحيح الواجب 📝',
            'message' => "تم تصحيح واجبك في '{$submission->worksheet->title}' وحصلت على درجة {$validated['score']}/{$submission->worksheet->max_score}.",
            'link'    => route('student.learn', $submission->worksheet->teaching_group_id),
        ]));

        return back()->with('success', 'تم حفظ الدرجة والتقييم بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $worksheet = Worksheet::with('group.assignment')->findOrFail($id);

        $this->assertOwns($worksheet->group);

        $worksheet->delete();

        return back()->with('success', 'تم حذف ملف الشيت/الواجب بنجاح.');
    }

    // ─── Internals ────────────────────────────────────────────────

    private function ownedGroupOrFail(int $groupId): TeachingGroup
    {
        $group = TeachingGroup::with('assignment.subject:id,name')->findOrFail($groupId);

        $this->assertOwns($group);

        return $group;
    }

    private function assertOwns(?TeachingGroup $group): void
    {
        abort_unless(
            $group && $group->assignment?->teacher_id === Auth::id(),
            403,
            'هذه المجموعة ليست ضمن جدولك.',
        );
    }
}
