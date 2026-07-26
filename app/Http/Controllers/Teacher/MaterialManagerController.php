<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Learning\Models\GroupMaterial;
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
                'id'      => $group->id,
                'name'    => $group->name,
                'subject' => $group->assignment?->subject?->only(['id', 'name']),
                'grade'   => $group->assignment?->gradeLevel?->only(['key', 'name']),
            ],
            'materials' => $group->materials()->get(),
        ]);
    }

    public function store(Request $request, int $groupId): RedirectResponse
    {
        $group = $this->ownedGroupOrFail($groupId);

        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'video_url'        => ['nullable', 'url', 'max:2048'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'order'            => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_free_preview'  => ['nullable', 'boolean'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'attachment'       => ['nullable', 'file', 'mimes:pdf,docx,pptx,zip', 'max:20480'],
        ]);

        $validated['teaching_group_id'] = $group->id;
        $validated['order'] ??= (int) $group->materials()->max('order') + 1;
        $validated['duration_seconds'] ??= 0;
        $validated['is_free_preview'] = (bool) ($validated['is_free_preview'] ?? false);

        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = '/storage/' . $request->file('attachment')->store('materials', 'public');
        }

        unset($validated['attachment']);

        GroupMaterial::create($validated);

        return back()->with('success', 'تمت إضافة المادة بنجاح.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $material = GroupMaterial::with('group.assignment')->findOrFail($id);

        $this->assertOwns($material->group);

        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'video_url'        => ['nullable', 'url', 'max:2048'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'order'            => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_free_preview'  => ['nullable', 'boolean'],
            'description'      => ['nullable', 'string', 'max:2000'],
        ]);

        $material->update($validated);

        return back()->with('success', 'تم تحديث المادة بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $material = GroupMaterial::with('group.assignment')->findOrFail($id);

        $this->assertOwns($material->group);

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
        abort_unless(
            $group && $group->assignment?->teacher_id === Auth::id(),
            403,
            'هذه المجموعة ليست ضمن جدولك.',
        );
    }
}
