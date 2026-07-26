<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
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
        $gradeLevels = GradeLevel::all()->map(function (GradeLevel $gl) {
            $assignments = TeachingAssignment::where('grade_level_id', $gl->id);

            $gl->teachers_count = (clone $assignments)->distinct('teacher_id')->count('teacher_id');
            $gl->subjects_count = (clone $assignments)->distinct('subject_id')->count('subject_id');
            $gl->groups_count   = TeachingGroup::whereIn('teaching_assignment_id', (clone $assignments)->select('id'))->count();
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

        $assignments = TeachingAssignment::with([
            'teacher:id,name,email,avatar',
            'subject:id,name',
            'groups' => fn ($q) => $q->withCount('activeBookings'),
        ])
            ->where('grade_level_id', $gl->id)
            ->get();

        return Inertia::render('Admin/GradeLevelShow', [
            'gradeLevel'  => $gl,
            'subjects'    => $gl->subjects()->get(['subjects.id', 'name', 'name_en', 'icon', 'is_active']),
            'assignments' => $assignments->map(fn (TeachingAssignment $assignment) => [
                'id'            => $assignment->id,
                'teacher'       => $assignment->teacher?->only(['id', 'name', 'email', 'avatar']),
                'subject'       => $assignment->subject?->only(['id', 'name']),
                'is_active'     => $assignment->is_active,
                'groups'        => $assignment->groups->map(fn (TeachingGroup $group) => [
                    'id'             => $group->id,
                    'name'           => $group->name,
                    'monthly_price'  => $group->monthly_price,
                    'capacity'       => $group->capacity,
                    'students_count' => $group->active_bookings_count,
                    'is_active'      => $group->is_active,
                ])->values(),
            ])->values(),
            'students'    => $gl->students()->get(['id', 'name', 'email', 'phone', 'is_active']),
            'teachers'    => User::whereIn('id', $assignments->pluck('teacher_id')->unique())
                ->get(['id', 'name', 'email', 'avatar']),
            'stats'       => [
                'active_subscriptions' => Subscription::active()
                    ->whereIn('teaching_assignment_id', $assignments->pluck('id'))
                    ->count(),
            ],
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
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $gl->update($validated);

        return back()->with('success', 'تم تحديث المرحلة الدراسية بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $gl = GradeLevel::findOrFail($id);

        if (TeachingAssignment::where('grade_level_id', $gl->id)->exists()) {
            return back()->with('error', 'لا يمكن حذف المرحلة لأن هناك معلمين مسندين إليها.');
        }

        $gl->delete();

        return back()->with('success', 'تم حذف المرحلة الدراسية بنجاح.');
    }
}
