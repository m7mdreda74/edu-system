<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teachers publish recorded material — videos and files — to their groups.
 */
class MaterialManagerController extends Controller
{
    public function index(int $groupId): Response
    {
        $group = $this->ownedGroupOrFail($groupId);

        return Inertia::render('Teacher/Materials', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                // Lets this legacy screen point at the curriculum builder that replaced it.
                'assignment_id' => $group->teaching_assignment_id,
                'subject' => $group->assignment?->subject?->only(['id', 'name']),
                'grade' => $group->assignment?->gradeLevel?->only(['key', 'name']),
            ],
            'materials' => $group->materials()->with('liveSession:id,lesson_id')->get()
                ->each(function (GroupMaterial $material): void {
                    $material->setAttribute(
                        'attachment_path',
                        filled($material->attachment_path)
                            ? route('learning.material.download', $material->id)
                            : null,
                    );
                    $material->setAppends(['is_live_recording']);
                }),
        ]);
    }

    public function store(Request $request, int $groupId): RedirectResponse
    {
        $group = $this->ownedGroupOrFail($groupId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_free_preview' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,docx,pptx,zip', 'max:20480'],
        ]);

        // This screen predates the curriculum builder, so everything it uploads
        // lands in the assignment's first unit.
        $validated['curriculum_unit_id'] = CurriculumUnit::firstFor($group)->id;
        $validated['order'] ??= (int) $group->materials()->max('order') + 1;
        $validated['duration_seconds'] ??= 0;
        $validated['is_free_preview'] = (bool) ($validated['is_free_preview'] ?? false);

        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = 'private://'.$request->file('attachment')->store('materials', 'local');
        }

        unset($validated['attachment']);

        GroupMaterial::create($validated);

        return back()->with('success', 'تمت إضافة المادة بنجاح.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $material = GroupMaterial::with(['unit.assignment', 'liveSession'])->findOrFail($id);

        $this->assertOwnsAssignment($material->unit?->assignment);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_free_preview' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($material->liveSession && array_key_exists('video_url', $validated)
            && $validated['video_url'] !== $material->video_url) {
            abort(403, 'تسجيل الحصة محمي ولا يمكن للمدرس تغييره أو إزالته.');
        }

        $material->update($validated);

        return back()->with('success', 'تم تحديث المادة بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $material = GroupMaterial::with(['unit.assignment', 'liveSession'])->findOrFail($id);

        $this->assertOwnsAssignment($material->unit?->assignment);

        abort_if($material->liveSession, 403, 'تسجيلات الحصص لا يحذفها إلا مدير المنصة.');

        $material->delete();

        return back()->with('success', 'تم حذف المادة بنجاح.');
    }

    // ─── Internals ────────────────────────────────────────────────

    private function ownedGroupOrFail(int $groupId): TeachingGroup
    {
        $group = TeachingGroup::with(['assignment.subject:id,name', 'assignment.gradeLevel:id,key,name'])
            ->findOrFail($groupId);

        $this->assertOwns($group);

        return $group;
    }

    private function assertOwns(?TeachingGroup $group): void
    {
        $this->assertOwnsAssignment($group?->assignment);
    }

    /** Lessons hang off the assignment now, so ownership is checked there. */
    private function assertOwnsAssignment(?TeachingAssignment $assignment): void
    {
        abort_unless(
            $assignment && $assignment->teacher_id === Auth::id(),
            403,
            'هذه المجموعة ليست ضمن جدولك.',
        );
    }
}
