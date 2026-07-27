<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
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

        $worksheets = $group->worksheets()
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

        $validated['file_path'] = '/storage/' . $request->file('file')->store('worksheets', 'public');

        // Homework inherits its lesson's unit; a loose study sheet falls back to
        // the assignment's first unit.
        $lessonUnitId = GroupMaterial::whereKey($validated['lesson_id'] ?? null)->value('curriculum_unit_id');

        $validated['curriculum_unit_id'] = $lessonUnitId ?? CurriculumUnit::firstFor($group)->id;

        unset($validated['file']);

        Worksheet::create($validated);

        return back()->with('success', 'تم رفع ملف الشيت/الواجب بنجاح.');
    }

    public function gradeSubmission(Request $request, int $submissionId): RedirectResponse
    {
        $submission = WorksheetSubmission::with('worksheet.unit.assignment')->findOrFail($submissionId);

        $this->assertOwnsAssignment($submission->worksheet?->unit?->assignment);

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
            'link'    => $this->learnLink($submission),
        ]));

        return back()->with('success', 'تم حفظ الدرجة والتقييم بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $worksheet = Worksheet::with('unit.assignment')->findOrFail($id);

        $this->assertOwnsAssignment($worksheet->unit?->assignment);

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
        $this->assertOwnsAssignment($group?->assignment);
    }

    /** Sheets hang off the assignment now, so ownership is checked there. */
    private function assertOwnsAssignment(?TeachingAssignment $assignment): void
    {
        abort_unless(
            $assignment && $assignment->teacher_id === Auth::id(),
            403,
            'هذه المجموعة ليست ضمن جدولك.',
        );
    }

    /**
     * The study room is still addressed by group, so send the student to the
     * slot they hold under this assignment — private students have none, and
     * land on their class list instead.
     */
    private function learnLink(WorksheetSubmission $submission): string
    {
        $groupId = Subscription::where('student_id', $submission->student_id)
            ->where('teaching_assignment_id', $submission->worksheet?->unit?->teaching_assignment_id)
            ->whereNotNull('teaching_group_id')
            ->value('teaching_group_id');

        return $groupId ? route('student.learn', $groupId) : route('student.my-classes');
    }
}
