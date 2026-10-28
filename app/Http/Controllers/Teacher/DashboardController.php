<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Domain\Subscription\Models\Subscription;
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
        $pendingGradingCount = WorksheetSubmission::query()
            ->whereNull('graded_at')
            ->whereHas('worksheet.unit.assignment', fn ($query) => $query->where('teacher_id', $teacherId))
            ->count();

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
                    'is_private' => (bool) ($group->is_private || $group->capacity === 1),
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

        // 5 recent pending submissions awaiting grading
        $pendingSubmissions = WorksheetSubmission::with([
            'student:id,name,email,avatar',
            'worksheet:id,title,type,curriculum_unit_id,max_score',
            'worksheet.unit.assignment.subject:id,name',
            'worksheet.unit.assignment.groups:id,teaching_assignment_id',
        ])
            ->whereNull('graded_at')
            ->whereHas('worksheet.unit.assignment', fn ($query) => $query->where('teacher_id', $teacherId))
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(function ($s) {
                $group = $s->worksheet?->unit?->assignment?->groups?->first();
                return [
                    'id' => $s->id,
                    'worksheet_id' => $s->worksheet_id,
                    'worksheet_title' => $s->worksheet?->title ?? 'واجب دراسي',
                    'subject_name' => $s->worksheet?->unit?->assignment?->subject?->name ?? 'مادة',
                    'group_id' => $group?->id,
                    'student_id' => $s->student_id,
                    'student_name' => $s->student?->name ?? 'طالب',
                    'student_avatar' => $s->student?->avatar,
                    'is_late' => (bool) ($s->is_late || $s->isLate()),
                    'submitted_at_formatted' => $s->submitted_at ? Carbon::parse($s->submitted_at)->locale('ar')->diffForHumans() : 'مؤخراً',
                ];
            });

        // Students needing attention (e.g. scored < 50 on recent quizzes)
        $atRiskStudents = QuizAttempt::with([
            'user:id,name,email,avatar',
            'quiz.unit.assignment.subject:id,name',
        ])
            ->whereHas('quiz.unit.assignment', fn ($q) => $q->where('teacher_id', $teacherId))
            ->whereNotNull('submitted_at')
            ->where('score', '<', 50)
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn ($attempt) => [
                'id' => $attempt->id,
                'student_id' => $attempt->user_id,
                'student_name' => $attempt->user?->name ?? 'طالب',
                'student_avatar' => $attempt->user?->avatar,
                'quiz_title' => $attempt->quiz?->title ?? 'اختبار',
                'subject_name' => $attempt->quiz?->unit?->assignment?->subject?->name ?? '',
                'score' => (int) $attempt->score,
                'submitted_at_formatted' => $attempt->submitted_at ? Carbon::parse($attempt->submitted_at)->locale('ar')->diffForHumans() : '',
            ]);

        // Recent student subscriptions & session bookings for this teacher
        $recentSubscriptions = Subscription::with([
            'student:id,name,email,avatar',
            'assignment.subject:id,name',
            'assignment.gradeLevel:id,key,name',
            'teachingGroup.schedules',
            'teachingGroup.assignment.subject:id,name',
            'teachingGroup.assignment.gradeLevel:id,key,name',
        ])
            ->where(function ($query) use ($assignmentIds) {
                $query->whereIn('teaching_assignment_id', $assignmentIds)
                    ->orWhereHas('teachingGroup', fn ($q) => $q->whereIn('teaching_assignment_id', $assignmentIds));
            })
            ->where('status', Subscription::STATUS_ACTIVE)
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $recentBookings = SessionBooking::with([
            'student:id,name,email,avatar',
            'group.schedules',
            'group.assignment.subject:id,name',
            'group.assignment.gradeLevel:id,key,name',
            'privateSlot.assignment.subject:id,name',
            'privateSlot.assignment.gradeLevel:id,key,name',
        ])
            ->where(function ($query) use ($assignmentIds) {
                $query->whereHas('group', fn ($q) => $q->whereIn('teaching_assignment_id', $assignmentIds))
                    ->orWhereHas('privateSlot', fn ($q) => $q->whereIn('teaching_assignment_id', $assignmentIds));
            })
            ->where('status', 'confirmed')
            ->latest('created_at')
            ->limit(10)
            ->get();

        $unifiedEnrollments = collect();

        foreach ($recentSubscriptions as $sub) {
            if (! $sub->student) {
                continue;
            }
            $group = $sub->teachingGroup;
            $isPrivate = (bool) ($sub->type === Subscription::TYPE_PRIVATE || $group?->is_private || $group?->capacity === 1);
            $subjectName = $sub->assignment?->subject?->name ?? $group?->assignment?->subject?->name ?? 'مادة دراسية';
            $gradeName = $sub->assignment?->gradeLevel?->name ?? $group?->assignment?->gradeLevel?->name ?? '';

            $scheduleText = $group ? $group->formattedSchedule() : '';

            $unifiedEnrollments->push([
                'id' => 'sub-' . $sub->id,
                'student_id' => $sub->student->id,
                'student_name' => $sub->student->name,
                'student_email' => $sub->student->email,
                'student_avatar' => $sub->student->avatar,
                'is_private' => $isPrivate,
                'type_label' => $isPrivate ? 'حصة خاصة (برايفت)' : 'مجموعة دراسية',
                'subject_name' => $subjectName,
                'grade_name' => $gradeName,
                'group_name' => $group?->name ?? ($isPrivate ? 'حصة خاصة' : 'مجموعة'),
                'schedule_text' => $scheduleText,
                'enrolled_at' => $sub->updated_at,
                'enrolled_at_formatted' => Carbon::parse($sub->updated_at)->locale('ar')->diffForHumans(),
                'status' => 'نشط ومؤكد',
            ]);
        }

        foreach ($recentBookings as $b) {
            if (! $b->student) {
                continue;
            }
            $group = $b->group;
            $slot = $b->privateSlot;
            $isPrivate = (bool) ($slot !== null || $group?->is_private || $group?->capacity === 1);
            $subjectName = $group?->assignment?->subject?->name ?? $slot?->assignment?->subject?->name ?? 'مادة دراسية';
            $gradeName = $group?->assignment?->gradeLevel?->name ?? $slot?->assignment?->gradeLevel?->name ?? '';

            $scheduleText = '';
            if ($group) {
                $scheduleText = $group->formattedSchedule();
            } elseif ($slot) {
                $scheduleText = Carbon::parse($slot->starts_at)->locale('ar')->translatedFormat('l d M · h:i A');
            }

            $exists = $unifiedEnrollments->contains(fn ($item) => $item['student_id'] === $b->student_id && $item['group_name'] === ($group?->name ?? 'جلسة خاصة'));
            if ($exists) {
                continue;
            }

            $unifiedEnrollments->push([
                'id' => 'booking-' . $b->id,
                'student_id' => $b->student->id,
                'student_name' => $b->student->name,
                'student_email' => $b->student->email,
                'student_avatar' => $b->student->avatar,
                'is_private' => $isPrivate,
                'type_label' => $slot?->is_free_intro ? 'حصة تجريبية مجانية' : ($isPrivate ? 'حصة خاصة (برايفت)' : 'مجموعة دراسية'),
                'subject_name' => $subjectName,
                'grade_name' => $gradeName,
                'group_name' => $group?->name ?? ($slot?->is_free_intro ? 'تجريبية مجانية' : 'جلسة خاصة'),
                'schedule_text' => $scheduleText,
                'enrolled_at' => $b->created_at,
                'enrolled_at_formatted' => Carbon::parse($b->created_at)->locale('ar')->diffForHumans(),
                'status' => 'مؤكد',
            ]);
        }

        $recentEnrollments = $unifiedEnrollments
            ->sortByDesc('enrolled_at')
            ->values()
            ->take(6);

        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'assignments' => $assignments->count(),
                'total_groups' => $allGroups->count(),
                'active_students' => $teacher->activeStudentsCount(),
                'lessons' => collect($materialCounts)->sum(),
                'upcoming_sessions_count' => $upcomingSessions->count(),
                'pending_grading' => $pendingGradingCount,
            ],
            'assignments' => $formattedAssignments,
            'groups' => $allGroups,
            'recent_enrollments' => $recentEnrollments,
            'upcoming_sessions' => $upcomingSessions,
            'pending_submissions' => $pendingSubmissions,
            'at_risk_students' => $atRiskStudents,
            'terms' => AcademicTerm::orderBy('starts_on')->get(['id', 'name', 'year_label'])->values(),
        ]);
    }
}
