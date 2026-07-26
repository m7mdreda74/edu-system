<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subjects are the platform's curriculum. Each one is attached to the grades it
 * is taught at — Arabic runs the whole way through, physics only from grade 10.
 */
class SubjectController extends Controller
{
    public function index(): Response
    {
        $subjects = Subject::with('gradeLevels:id,key,name,stage,track')
            ->withCount('teachingAssignments')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Subjects', [
            'subjects'    => $subjects,
            'gradeLevels' => GradeLevel::where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'key', 'name', 'stage', 'track']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $subject = Subject::create($this->attributes($validated));
        $subject->gradeLevels()->sync($validated['grade_level_ids']);

        return back()->with('success', 'تم إضافة المادة الدراسية بنجاح.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $subject   = Subject::findOrFail($id);
        $validated = $this->validated($request, $subject->id);

        $subject->update($this->attributes($validated));
        $subject->gradeLevels()->sync($validated['grade_level_ids']);

        return back()->with('success', 'تم تحديث المادة الدراسية بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $subject = Subject::findOrFail($id);

        if ($subject->teachingAssignments()->exists()) {
            return back()->with('error', 'لا يمكن حذف المادة لأنها مسندة إلى معلمين.');
        }

        $subject->delete();

        return back()->with('success', 'تم حذف المادة الدراسية بنجاح.');
    }

    // ─── Internals ────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'              => ['required', 'string', 'max:255', 'unique:subjects,name' . ($ignoreId ? ",{$ignoreId}" : '')],
            'name_en'           => ['nullable', 'string', 'max:255'],
            'icon'              => ['nullable', 'string', 'max:50'],
            'is_active'         => ['sometimes', 'boolean'],
            'grade_level_ids'   => ['required', 'array', 'min:1'],
            'grade_level_ids.*' => ['integer', 'exists:grade_levels,id'],
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(array $validated): array
    {
        return [
            'name'      => $validated['name'],
            'name_en'   => $validated['name_en'] ?? null,
            'icon'      => $validated['icon'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }
}
