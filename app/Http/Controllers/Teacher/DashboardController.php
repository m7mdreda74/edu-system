<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $teacherId = Auth::id();
        $assignmentIds = TeachingAssignment::where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->pluck('id');
        $groupIds = TeachingGroup::whereIn('teaching_assignment_id', $assignmentIds)->pluck('id');
        $materialCounts = GroupMaterial::countsByAssignment($assignmentIds);

        $groups = TeachingGroup::with([
            'assignment.subject:id,name',
            'assignment.gradeLevel:id,key,name',
            'activeBookings.student:id,name,email,avatar',
            'subscriptions.student:id,name,email,avatar',
        ])
            ->whereIn('id', $groupIds)
            ->withCount('activeBookings')
            ->latest()
            ->get()
            ->map(function (TeachingGroup $group) use ($materialCounts) {
                $students = $group->activeBookings
                    ->map(fn ($b) => $b->student)
                    ->merge($group->subscriptions->where('status', 'active')->map(fn ($s) => $s->student))
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->map(fn ($student) => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'email' => $student->email,
                        'avatar' => $student->avatar,
                    ]);

                return [
                    'id' => $group->id,
                    'assignment_id' => $group->teaching_assignment_id,
                    'name' => $group->name,
                    'subject' => $group->assignment?->subject?->only(['id', 'name']),
                    'grade' => $group->assignment?->gradeLevel?->only(['key', 'name']),
                    'students_count' => $students->count() ?: $group->active_bookings_count,
                    'students' => $students,
                    'materials_count' => $materialCounts[$group->teaching_assignment_id] ?? 0,
                    'is_active' => $group->is_active,
                ];
            });

        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'assignments' => $assignmentIds->count(),
                'total_groups' => $groups->count(),
                'active_students' => Auth::user()->activeStudentsCount(),
                'lessons' => collect($materialCounts)->sum(),
            ],
            'groups' => $groups,
        ]);
    }
}
