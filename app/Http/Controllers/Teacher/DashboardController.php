<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const STAGE_NAMES = [
        'primary' => 'المرحلة الابتدائية',
        'preparatory' => 'المرحلة الإعدادية',
        'secondary' => 'المرحلة الثانوية',
    ];

    private const DAY_NAMES = [
        'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'
    ];

    public function index(): Response
    {
        $teacher = Auth::user();
        $teacherId = (int) $teacher->id;

        $assignments = TeachingAssignment::with([
            'subject:id,name,icon',
            'gradeLevel:id,key,name,stage',
            'units:id,teaching_assignment_id,title,order',
            'privateSlots' => fn ($q) => $q->where('status', '!=', 'cancelled')->latest('starts_at')->limit(10),
            'privateSlots.booking.student:id,name,avatar',
            'groups' => fn ($q) => $q->withCount('activeBookings')->latest(),
            'groups.schedules' => fn ($q) => $q->orderBy('day_of_week')->orderBy('start_time'),
            'groups.lessons' => fn ($q) => $q->orderBy('position'),
            'groups.activeBookings.student:id,name,email,avatar',
            'groups.subscriptions.student:id,name,email,avatar',
        ])
            ->where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->get();

        $assignmentIds = $assignments->pluck('id');
        $materialCounts = GroupMaterial::countsByAssignment($assignmentIds);

        $formattedAssignments = $assignments->map(function (TeachingAssignment $assignment) use ($materialCounts) {
            $stageKey = (string) ($assignment->gradeLevel?->stage ?? '');
            $stageName = self::STAGE_NAMES[$stageKey] ?? 'مرحلة دراسية';

            $groups = $assignment->groups->map(function (TeachingGroup $group) use ($materialCounts) {
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

                $schedules = $group->schedules->map(fn (TeachingGroupSchedule $s) => [
                    'id' => $s->id,
                    'day_of_week' => (int) $s->day_of_week,
                    'day_name' => self::DAY_NAMES[(int) $s->day_of_week] ?? '',
                    'start_time' => (string) $s->start_time,
                    'end_time' => (string) $s->end_time,
                    'start_time_12' => TeachingGroupSchedule::formatTime12($s->start_time),
                    'end_time_12' => TeachingGroupSchedule::formatTime12($s->end_time),
                    'duration_minutes' => (int) $s->duration_minutes,
                ])->values();

                $lessons = $group->lessons;

                return [
                    'id' => $group->id,
                    'assignment_id' => $group->teaching_assignment_id,
                    'name' => $group->name,
                    'capacity' => (int) $group->capacity,
                    'seats_left' => $group->seatsLeft(),
                    'monthly_price' => (int) $group->monthly_price,
                    'students_count' => $students->count() ?: (int) $group->active_bookings_count,
                    'students' => $students,
                    'materials_count' => (int) ($materialCounts[$group->teaching_assignment_id] ?? 0),
                    'is_active' => (bool) $group->is_active,
                    'schedules' => $schedules,
                    'lessons_count' => $lessons->count(),
                    'pending_lessons_count' => $lessons->where('status', 'pending')->count(),
                    'scheduled_lessons_count' => $lessons->where('status', 'scheduled')->count(),
                ];
            })->values();

            $privateSlots = $assignment->privateSlots->map(fn ($slot) => [
                'id' => $slot->id,
                'starts_at' => (string) $slot->starts_at,
                'ends_at' => (string) $slot->ends_at,
                'starts_at_formatted' => Carbon::parse($slot->starts_at)->locale('ar')->translatedFormat('D d M · h:i A'),
                'is_free_intro' => (bool) $slot->is_free_intro,
                'status' => (string) $slot->status,
                'student_name' => $slot->booking?->student?->name,
            ])->values();

            return [
                'id' => $assignment->id,
                'subject' => [
                    'id' => $assignment->subject?->id,
                    'name' => $assignment->subject?->name ?? 'مادة دراسية',
                    'icon' => $assignment->subject?->icon,
                ],
                'grade' => [
                    'id' => $assignment->gradeLevel?->id,
                    'key' => $assignment->gradeLevel?->key,
                    'name' => $assignment->gradeLevel?->name ?? 'الصف الدراسي',
                ],
                'stage' => [
                    'key' => $stageKey,
                    'name' => $stageName,
                ],
                'accepts_private' => (bool) $assignment->accepts_private,
                'private_monthly_price' => (int) $assignment->private_monthly_price,
                'groups' => $groups,
                'units' => $assignment->units->map(fn ($u) => [
                    'id' => $u->id,
                    'title' => $u->title,
                    'order' => $u->order,
                ])->values(),
                'units_count' => $assignment->units->count(),
                'lessons_count' => (int) ($materialCounts[$assignment->id] ?? 0),
                'private_slots' => $privateSlots,
            ];
        })->values();

        // Next upcoming live sessions for the teacher
        $upcomingSessions = LiveSession::with(['group.assignment.subject', 'group.assignment.gradeLevel'])
            ->where('teacher_id', $teacherId)
            ->whereIn('status', [LiveSession::STATUS_SCHEDULED, LiveSession::STATUS_LIVE])
            ->where('scheduled_at', '>=', Carbon::now()->subHours(2))
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'title' => $session->title,
                'scheduled_at' => (string) $session->scheduled_at,
                'scheduled_at_formatted' => Carbon::parse($session->scheduled_at)->locale('ar')->translatedFormat('l d M · h:i A'),
                'group_name' => $session->group?->name,
                'subject_name' => $session->group?->assignment?->subject?->name,
                'grade_name' => $session->group?->assignment?->gradeLevel?->name,
                'status' => $session->status,
                'room_url' => route('live-sessions.room', $session->id),
            ]);

        $allGroups = $formattedAssignments->flatMap(fn ($a) => $a['groups'])->values();

        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'assignments' => $assignments->count(),
                'total_groups' => $allGroups->count(),
                'active_students' => $teacher->activeStudentsCount(),
                'lessons' => collect($materialCounts)->sum(),
                'upcoming_sessions_count' => $upcomingSessions->count(),
            ],
            'assignments' => $formattedAssignments,
            'groups' => $allGroups,
            'upcoming_sessions' => $upcomingSessions,
            'terms' => AcademicTerm::orderBy('starts_on')->get(['id', 'name', 'year_label'])->values(),
        ]);
    }
}
