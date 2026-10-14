<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $groupIds = TeachingGroup::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->pluck('id');
        $rosterRows = $this->rosterRows($groupIds);
        $rosterCounts = DB::query()
            ->fromSub($rosterRows, 'roster')
            ->select('teaching_group_id')
            ->selectRaw('COUNT(DISTINCT student_id) as total')
            ->groupBy('teaching_group_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->teaching_group_id => (int) $row->total]);
        $totalStudents = (int) (clone DB::query()->fromSub($rosterRows, 'roster'))
            ->distinct()
            ->count('student_id');

        $groups = TeachingGroup::with([
            'assignment.subject:id,name',
            'assignment.gradeLevel:id,key,name',
            'term:id,name',
            'schedules',
        ])
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->withCount(['activeBookings', 'lessons'])
            ->latest()
            ->get()
            ->map(function (TeachingGroup $group) use ($materialsCounts, $worksheetsCounts, $rosterCounts): array {
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
                    'students_count' => (int) ($rosterCounts[$group->id] ?? 0),
                    'lessons_count' => $group->lessons_count,
                    'materials_count' => $materialsCount,
                    'worksheets_count' => $worksheetsCount,
                ];
            });

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

    public function workspace(int $groupId): Response
    {
        $group = TeachingGroup::query()
            ->with([
                'assignment.subject:id,name',
                'assignment.gradeLevel:id,key,name',
                'schedules',
            ])
            ->whereKey($groupId)
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', Auth::id()))
            ->firstOrFail();

        return Inertia::render('Teacher/GroupWorkspace', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'capacity' => $group->capacity,
                'is_active' => $group->is_active,
                'assignment_id' => $group->teaching_assignment_id,
                'subject' => $group->assignment?->subject?->only(['id', 'name']),
                'grade' => $group->assignment?->gradeLevel?->only(['id', 'key', 'name']),
                'schedules' => $group->schedules->map(fn ($schedule) => [
                    'day_of_week' => (int) $schedule->day_of_week,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ])->values(),
            ],
            'studentsCount' => $this->rosterRows(collect([$group->id]))->distinct()->count('student_id'),
        ]);
    }

    public function rosterPage(int $groupId): Response
    {
        $group = TeachingGroup::query()
            ->with([
                'assignment.subject:id,name',
                'assignment.gradeLevel:id,key,name',
            ])
            ->whereKey($groupId)
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', Auth::id()))
            ->firstOrFail();

        return Inertia::render('Teacher/GroupRoster', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'capacity' => $group->capacity,
                'subject' => $group->assignment?->subject?->only(['id', 'name']),
                'grade' => $group->assignment?->gradeLevel?->only(['id', 'key', 'name']),
            ],
            'studentsCount' => $this->rosterRows(collect([$group->id]))->distinct()->count('student_id'),
        ]);
    }

    public function roster(Request $request, int $groupId): JsonResponse
    {
        $group = TeachingGroup::query()
            ->whereKey($groupId)
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', Auth::id()))
            ->firstOrFail();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $students = User::query()
            ->role('student')
            ->where(function ($query) use ($group): void {
                $query->whereHas('bookings', fn ($booking) => $booking
                    ->where('teaching_group_id', $group->id)
                    ->where('status', 'confirmed'))
                    ->orWhereHas('subscriptions', fn ($subscription) => $subscription
                        ->where('teaching_group_id', $group->id)
                        ->where('status', 'active')
                        ->where('period_end', '>=', today()->toDateString()));
            })
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'data' => $students->getCollection()->map(fn (User $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'avatar' => $student->avatar,
            ])->values(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    private function rosterRows($groupIds)
    {
        $bookings = DB::table('session_bookings')
            ->select(['teaching_group_id', 'student_id'])
            ->whereIn('teaching_group_id', $groupIds)
            ->where('status', 'confirmed');
        $subscriptions = DB::table('subscriptions')
            ->select(['teaching_group_id', 'student_id'])
            ->whereIn('teaching_group_id', $groupIds)
            ->where('status', 'active')
            ->where('period_end', '>=', today()->toDateString());

        return $bookings->union($subscriptions);
    }
}
