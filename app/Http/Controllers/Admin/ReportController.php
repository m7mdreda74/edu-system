<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:scheduled,live,ended,cancelled'],
        ]);

        $teacherId = $filters['teacher_id'] ?? null;
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;

        $teachers = User::role('teacher')
            ->with([
                'subject:id,name',
                'teachingAssignments:id,teacher_id',
            ])
            ->withCount('teachingAssignments')
            ->orderBy('name')
            ->get();

        $groups = TeachingGroup::query()
            ->with([
                'assignment:id,teacher_id,subject_id,grade_level_id',
                'assignment.teacher:id,name',
                'assignment.subject:id,name',
                'assignment.gradeLevel:id,name',
                'term:id,name',
            ])
            ->withCount(['activeBookings', 'liveSessions'])
            ->when($teacherId, fn (Builder $query) => $query->whereHas(
                'assignment',
                fn (Builder $assignment) => $assignment->where('teacher_id', $teacherId),
            ))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $sessionQuery = LiveSession::query()
            ->with([
                'teacher:id,name',
                'teachingGroup:id,name,teaching_assignment_id',
                'teachingGroup.assignment.subject:id,name',
                'privateSessionSlot:id,starts_at,ends_at',
            ])
            ->withCount('attendees')
            ->when($teacherId, fn (Builder $query) => $query->where('teacher_id', $teacherId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($start, fn (Builder $query, string $date) => $query->whereDate('scheduled_at', '>=', $date))
            ->when($end, fn (Builder $query, string $date) => $query->whereDate('scheduled_at', '<=', $date));

        $sessions = $sessionQuery
            ->latest('scheduled_at')
            ->paginate(10)
            ->withQueryString();

        $sessionCountQuery = (clone $sessionQuery);
        $sessionCount = $sessionCountQuery->count();
        $liveCount = (clone $sessionQuery)->where('status', LiveSession::STATUS_LIVE)->count();
        $endedCount = (clone $sessionQuery)->where('status', LiveSession::STATUS_ENDED)->count();

        $groupIds = $groups->pluck('id');
        $confirmedGroupStudents = SessionBooking::query()
            ->where('status', 'confirmed')
            ->whereIn('teaching_group_id', $groupIds)
            ->distinct()
            ->count('student_id');

        $privateStudents = SessionBooking::query()
            ->where('status', 'confirmed')
            ->whereHas('privateSlot.assignment', function (Builder $query) use ($teacherId): void {
                $query->when($teacherId, fn (Builder $q, int $id) => $q->where('teacher_id', $id));
            })
            ->distinct()
            ->count('student_id');

        $studentCounts = SessionBooking::query()
            ->selectRaw('teaching_group_id, COUNT(DISTINCT student_id) AS students_count')
            ->where('status', 'confirmed')
            ->whereIn('teaching_group_id', $groupIds)
            ->groupBy('teaching_group_id')
            ->pluck('students_count', 'teaching_group_id');

        $teacherGroups = $groups->groupBy(fn (TeachingGroup $group) => $group->assignment?->teacher_id);
        $teacherSessions = LiveSession::query()
            ->selectRaw('teacher_id, COUNT(*) AS sessions_count')
            ->when($teacherId, fn (Builder $query) => $query->where('teacher_id', $teacherId))
            ->when($start, fn (Builder $query, string $date) => $query->whereDate('scheduled_at', '>=', $date))
            ->when($end, fn (Builder $query, string $date) => $query->whereDate('scheduled_at', '<=', $date))
            ->groupBy('teacher_id')
            ->pluck('sessions_count', 'teacher_id');

        $teachers->each(function (User $teacher) use ($teacherGroups, $teacherSessions, $studentCounts): void {
            $teacherGroupRows = $teacherGroups->get($teacher->id, collect());
            $teacher->setAttribute('groups_count', $teacherGroupRows->count());
            $teacher->setAttribute('students_count', $teacherGroupRows
                ->sum(fn (TeachingGroup $group) => (int) $studentCounts->get($group->id, 0)));
            $teacher->setAttribute('sessions_count', (int) $teacherSessions->get($teacher->id, 0));
        });

        return Inertia::render('Admin/Reports', [
            'filters' => [
                'start_date' => $start,
                'end_date' => $end,
                'teacher_id' => $teacherId,
                'status' => $filters['status'] ?? '',
            ],
            'teachers' => $teachers,
            'groups' => $groups,
            'sessions' => $sessions,
            'summary' => [
                'teachers' => $teachers->count(),
                'groups' => $groups->count(),
                'students' => $confirmedGroupStudents + $privateStudents,
                'sessions' => $sessionCount,
                'live_sessions' => $liveCount,
                'ended_sessions' => $endedCount,
            ],
        ]);
    }
}

