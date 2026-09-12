<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeacherGroupController extends Controller
{
    public function index(): Response
    {
        $teacherId = (int) Auth::id();

        $assignmentIds = TeachingAssignment::where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->pluck('id');

        $materialsCounts = GroupMaterial::countsByAssignment($assignmentIds);
        $worksheetsCounts = Worksheet::query()
            ->join('curriculum_units', 'worksheets.curriculum_unit_id', '=', 'curriculum_units.id')
            ->whereIn('curriculum_units.teaching_assignment_id', $assignmentIds)
            ->groupBy('curriculum_units.teaching_assignment_id')
            ->selectRaw('curriculum_units.teaching_assignment_id, count(*) as count')
            ->pluck('count', 'teaching_assignment_id');

        $groups = TeachingGroup::with([
            'assignment.subject:id,name',
            'assignment.gradeLevel:id,key,name',
            'term:id,name',
            'schedules',
            'lessons.liveSession:id,title,scheduled_at,status',
            'activeBookings.student:id,name,email,avatar',
            'subscriptions' => fn ($q) => $q->where('status', 'active')->with('student:id,name,email,avatar'),
        ])
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->withCount(['activeBookings', 'lessons'])
            ->latest()
            ->get()
            ->map(function (TeachingGroup $group) use ($materialsCounts, $worksheetsCounts): array {
                $students = $group->activeBookings
                    ->map(fn ($b) => $b->student)
                    ->merge($group->subscriptions->where('status', 'active')->map(fn ($s) => $s->student))
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->map(fn (User $student) => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'email' => $student->email,
                        'avatar' => $student->avatar,
                    ]);

                $materialsCount = (int) ($materialsCounts[$group->teaching_assignment_id] ?? 0);
                $worksheetsCount = (int) ($worksheetsCounts[$group->teaching_assignment_id] ?? 0);

                return [
                    'id' => $group->id,
                    'assignment_id' => $group->teaching_assignment_id,
                    'name' => $group->name,
                    'capacity' => $group->capacity,
                    'monthly_price' => $group->monthly_price,
                    'is_active' => $group->is_active,
                    'term' => $group->term?->name,
                    'subject' => $group->assignment?->subject?->only(['id', 'name']),
                    'grade' => $group->assignment?->gradeLevel?->only(['key', 'name']),
                    'schedules' => $group->schedules->map(fn ($s) => [
                        'id' => $s->id,
                        'day_of_week' => $s->day_of_week,
                        'start_time' => $s->start_time,
                        'end_time' => $s->end_time,
                    ]),
                    'students_count' => $students->count() ?: $group->active_bookings_count,
                    'students' => $students,
                    'lessons_count' => $group->lessons_count,
                    'materials_count' => $materialsCount,
                    'worksheets_count' => $worksheetsCount,
                ];
            });

        $totalStudents = $groups->pluck('students')->flatten(1)->unique('id')->count();

        return Inertia::render('Teacher/Groups', [
            'groups' => $groups,
            'stats' => [
                'total_groups' => $groups->count(),
                'active_groups' => $groups->where('is_active', true)->count(),
                'total_students' => $totalStudents,
                'total_capacity' => (int) $groups->sum('capacity'),
            ],
        ]);
    }
}
