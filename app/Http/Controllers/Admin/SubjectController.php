<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Academic\Models\Subject;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        $subjects = Subject::withCount('teachingAssignments')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Subjects', [
            'subjects' => $subjects,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'name_en'     => ['nullable', 'string', 'max:255'],
            'grade_level' => ['required', 'string', 'exists:grade_levels,key'],
            'icon'        => ['required', 'string', 'in:calculator,atom,flask,dna,landmark,globe,book,language'],
        ]);

        Subject::create($validated);

        return back()->with('success', 'تم إضافة المادة الدراسية بنجاح.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'name_en'     => ['nullable', 'string', 'max:255'],
            'grade_level' => ['required', 'string', 'exists:grade_levels,key'],
            'icon'        => ['required', 'string', 'in:calculator,atom,flask,dna,landmark,globe,book,language'],
            'is_active'   => ['required', 'boolean'],
        ]);

        $subject->update($validated);

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
}
