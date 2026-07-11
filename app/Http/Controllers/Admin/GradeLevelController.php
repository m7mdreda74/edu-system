<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Course\Models\GradeLevel;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradeLevelController extends Controller
{
    public function index(): Response
    {
        $gradeLevels = GradeLevel::all()->map(function ($gl) {
            $gl->teachers_count = User::role('teacher')
                ->whereHas('coursesAsTeacher', function ($q) use ($gl) {
                    $q->where('grade_level', $gl->key);
                })
                ->count();
            $gl->subjects_count = $gl->subjects()->count();
            $gl->courses_count = $gl->courses()->count();
            $gl->students_count = $gl->students()->count();
            return $gl;
        });

        return Inertia::render('Admin/GradeLevels', [
            'gradeLevels' => $gradeLevels,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key'       => ['required', 'string', 'max:20', 'unique:grade_levels,key', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'name'      => ['required', 'string', 'max:255'],
            'name_en'   => ['nullable', 'string', 'max:255'],
            'stage'     => ['required', 'string', 'in:primary,preparatory,secondary,all'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'key.regex' => 'يجب أن يحتوي رمز المرحلة على أحرف إنجليزية وأرقام وعلامة شرطة فقط بدون مسافات.',
        ]);

        GradeLevel::create($validated);

        return back()->with('success', 'تم إضافة المرحلة الدراسية بنجاح.');
    }

    public function show(int $id): Response
    {
        $gl = GradeLevel::findOrFail($id);

        $subjects = $gl->subjects()->withCount('courses')->get();
        $courses  = $gl->courses()->with('teacher:id,name', 'subject:id,name')->withCount('enrollments')->get();
        $students = $gl->students()->get(['id', 'name', 'email', 'phone', 'is_active']);
        $teachers = $gl->teachers;

        return Inertia::render('Admin/GradeLevelShow', [
            'gradeLevel' => $gl,
            'subjects'   => $subjects,
            'courses'    => $courses,
            'students'   => $students,
            'teachers'   => $teachers,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $gl = GradeLevel::findOrFail($id);

        $validated = $request->validate([
            'key'       => ['required', 'string', 'max:20', 'unique:grade_levels,key,' . $gl->id, 'regex:/^[a-zA-Z0-9_-]+$/'],
            'name'      => ['required', 'string', 'max:255'],
            'name_en'   => ['nullable', 'string', 'max:255'],
            'stage'     => ['required', 'string', 'in:primary,preparatory,secondary,all'],
            'is_active' => ['required', 'boolean'],
        ], [
            'key.regex' => 'يجب أن يحتوي رمز المرحلة على أحرف إنجليزية وأرقام وعلامة شرطة فقط بدون مسافات.',
        ]);

        $gl->update($validated);

        return back()->with('success', 'تم تحديث المرحلة الدراسية بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $gl = GradeLevel::findOrFail($id);

        // Check if there are related records
        if ($gl->subjects()->exists()) {
            return back()->with('error', 'لا يمكن حذف المرحلة الدراسية لوجود مواد مرتبطة بها.');
        }

        if ($gl->courses()->exists()) {
            return back()->with('error', 'لا يمكن حذف المرحلة الدراسية لوجود كورسات مرتبطة بها.');
        }

        if ($gl->students()->exists()) {
            return back()->with('error', 'لا يمكن حذف المرحلة الدراسية لوجود طلاب مسجلين بها.');
        }

        $gl->delete();

        return back()->with('success', 'تم حذف المرحلة الدراسية بنجاح.');
    }
}
