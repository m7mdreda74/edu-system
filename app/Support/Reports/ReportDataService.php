<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportDataService
{
    public const MAX_PRINT_ROWS = 2_000;

    /**
     * Gather and aggregate all report data based on request filters.
     *
     * @return array<string, mixed>
     */
    public function getReportData(Request $request, bool $isExport = false): array
    {
        $filters = $request->validate([
            'report_type'  => ['nullable', 'string', 'in:all,teachers,students,attendance,sessions,groups'],
            'start_date'   => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date'     => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'teacher_id'   => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'student_id'   => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'session_type' => ['nullable', 'string', 'in:all,private,free,group'],
            'group_id'     => ['nullable', 'integer', 'min:1', 'exists:teaching_groups,id'],
            'status'       => ['nullable', 'in:scheduled,live,ended,cancelled'],
            'print'        => ['nullable', 'boolean'],
        ]);

        $print = $request->boolean('print') || $isExport;
        $reportType = $filters['report_type'] ?? 'all';
        $teacherId = isset($filters['teacher_id']) ? (int) $filters['teacher_id'] : null;
        $studentId = isset($filters['student_id']) ? (int) $filters['student_id'] : null;
        $sessionType = $filters['session_type'] ?? null;
        $groupId = isset($filters['group_id']) ? (int) $filters['group_id'] : null;
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;
        $startAt = $start ? CarbonImmutable::parse($start)->startOfDay() : null;
        $endAt = $end ? CarbonImmutable::parse($end)->endOfDay() : null;

        if ($startAt && $endAt && $startAt->diffInDays($endAt) > 366) {
            throw ValidationException::withMessages([
                'end_date' => 'نطاق التقرير لا يمكن أن يتجاوز 366 يوماً.',
            ]);
        }

        // 1. Grade Levels lookup for student and teacher display
        $gradeLevels = GradeLevel::pluck('name', 'key');

        // 2. Groups Query
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
            ->when($groupId, fn (Builder $query) => $query->where('id', $groupId))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $groupIds = $groups->pluck('id');

        // 3. Teachers Query & Aggregations
        $teachersQuery = User::role('teacher')
            ->with([
                'subject:id,name',
                'teachingAssignments:id,teacher_id,grade_level_id',
                'teachingAssignments.gradeLevel:id,name',
            ])
            ->withCount('teachingAssignments')
            ->when($teacherId, fn (Builder $query) => $query->where('id', $teacherId))
            ->when($startAt, fn (Builder $query) => $query->where('created_at', '>=', $startAt))
            ->when($endAt, fn (Builder $query) => $query->where('created_at', '<=', $endAt))
            ->orderBy('name');

        $teachers = $teachersQuery->get();

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
            ->when($startAt, fn (Builder $query) => $query->where('scheduled_at', '>=', $startAt))
            ->when($endAt, fn (Builder $query) => $query->where('scheduled_at', '<=', $endAt))
            ->groupBy('teacher_id')
            ->pluck('sessions_count', 'teacher_id');

        $teachers->each(function (User $teacher) use ($teacherGroups, $teacherSessions, $studentCounts): void {
            $teacherGroupRows = $teacherGroups->get($teacher->id, collect());
            $teacher->setAttribute('groups_count', $teacherGroupRows->count());
            $teacher->setAttribute('students_count', $teacherGroupRows
                ->sum(fn (TeachingGroup $group) => (int) $studentCounts->get($group->id, 0)));
            $teacher->setAttribute('sessions_count', (int) $teacherSessions->get($teacher->id, 0));
        });

        // 4. Live Sessions Query
        $sessionQuery = LiveSession::query()
            ->with([
                'teacher:id,name',
                'teachingGroup:id,name,teaching_assignment_id',
                'teachingGroup.assignment.subject:id,name',
                'privateSessionSlot:id,teaching_assignment_id,starts_at,ends_at',
            ])
            ->withCount('attendees')
            ->when($teacherId, fn (Builder $query) => $query->where('teacher_id', $teacherId))
            ->when($groupId, fn (Builder $query) => $query->where('teaching_group_id', $groupId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($startAt, fn (Builder $query) => $query->where('scheduled_at', '>=', $startAt))
            ->when($endAt, fn (Builder $query) => $query->where('scheduled_at', '<=', $endAt));

        if ($sessionType === 'private') {
            $sessionQuery->whereNotNull('private_session_slot_id');
        } elseif ($sessionType === 'free') {
            $sessionQuery->where('is_free', true);
        } elseif ($sessionType === 'group') {
            $sessionQuery->whereNotNull('teaching_group_id')
                ->where(fn (Builder $q) => $q->where('is_free', false)->orWhereNull('is_free'));
        }

        $sessionCount = (clone $sessionQuery)->count();
        $liveCount = (clone $sessionQuery)->where('status', LiveSession::STATUS_LIVE)->count();
        $endedCount = (clone $sessionQuery)->where('status', LiveSession::STATUS_ENDED)->count();

        if ($print) {
            $sessionRows = $sessionQuery->latest('scheduled_at')->limit(self::MAX_PRINT_ROWS)->get();
            $sessions = [
                'data' => $sessionRows,
                'links' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => max(1, $sessionRows->count()),
                    'total' => $sessionCount,
                ],
            ];
        } else {
            $sessions = $sessionQuery
                ->latest('scheduled_at')
                ->paginate(10)
                ->withQueryString();
        }

        // 5. Attendance Query
        $attendanceQuery = LiveSessionAttendee::query()
            ->with([
                'user:id,name,email,phone',
                'session:id,title,teacher_id,teaching_group_id,private_session_slot_id,scheduled_at,status',
                'session.teacher:id,name',
                'session.teachingGroup:id,name,teaching_assignment_id',
                'session.teachingGroup.assignment.subject:id,name',
                'session.privateSessionSlot.assignment.subject:id,name',
            ])
            ->whereHas('user', fn (Builder $query) => $query->role('student'))
            ->when($studentId, fn (Builder $query) => $query->where('user_id', $studentId))
            ->whereHas('session', function (Builder $query) use ($teacherId, $groupId, $sessionType, $startAt, $endAt, $filters): void {
                $query
                    ->when($teacherId, fn (Builder $q, int $id) => $q->where('teacher_id', $id))
                    ->when($groupId, fn (Builder $q, int $id) => $q->where('teaching_group_id', $id))
                    ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
                    ->when($startAt, fn (Builder $q) => $q->where('scheduled_at', '>=', $startAt))
                    ->when($endAt, fn (Builder $q) => $q->where('scheduled_at', '<=', $endAt));

                if ($sessionType === 'private') {
                    $query->whereNotNull('private_session_slot_id');
                } elseif ($sessionType === 'free') {
                    $query->where('is_free', true);
                } elseif ($sessionType === 'group') {
                    $query->whereNotNull('teaching_group_id')
                        ->where(fn (Builder $q) => $q->where('is_free', false)->orWhereNull('is_free'));
                }
            });

        $attendanceTotal = (clone $attendanceQuery)->count();

        if ($print) {
            $attendanceRows = $attendanceQuery->latest('joined_at')->limit(self::MAX_PRINT_ROWS)->get();
            $attendance = [
                'data' => $attendanceRows,
                'links' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => max(1, $attendanceRows->count()),
                    'total' => $attendanceTotal,
                ],
            ];
            $attendanceItems = $attendanceRows;
        } else {
            $attendance = $attendanceQuery
                ->latest('joined_at')
                ->paginate(20, ['*'], 'attendance_page')
                ->withQueryString();
            $attendanceItems = $attendance->getCollection();
        }

        $attendanceItems->transform(function (LiveSessionAttendee $attendee): array {
            $session = $attendee->session;

            return [
                'id' => $attendee->id,
                'student' => $attendee->user?->only(['id', 'name', 'email', 'phone']),
                'teacher' => $session?->teacher?->only(['id', 'name']),
                'session' => $session?->title,
                'subject' => $session?->teachingGroup?->assignment?->subject?->name
                    ?? $session?->privateSessionSlot?->assignment?->subject?->name,
                'scheduled_at' => $session?->scheduled_at?->toIso8601String(),
                'joined_at' => $attendee->joined_at?->toIso8601String(),
                'left_at' => $attendee->left_at?->toIso8601String(),
                'minutes' => (int) round($attendee->durationSeconds() / 60),
            ];
        });

        // 6. Comprehensive Students Query & Detailed Metrics
        // Attendance mapping for all students
        $attendeeRecords = LiveSessionAttendee::query()
            ->select(['id', 'user_id', 'joined_at', 'left_at', 'last_heartbeat_at'])
            ->when($startAt, fn ($q) => $q->where('joined_at', '>=', $startAt))
            ->when($endAt, fn ($q) => $q->where('joined_at', '<=', $endAt))
            ->get()
            ->groupBy('user_id');

        $studentsQuery = User::role('student')
            ->with([
                'parents:id,name,phone,email',
                'subscriptions' => fn ($q) => $q->with([
                    'assignment.teacher:id,name',
                    'assignment.subject:id,name',
                    'group:id,name',
                ]),
            ])
            ->when($studentId, fn (Builder $query) => $query->where('id', $studentId))
            ->when($teacherId, function (Builder $query) use ($teacherId): void {
                $query->where(function (Builder $subQ) use ($teacherId): void {
                    $subQ->whereHas('subscriptions.assignment', fn ($q) => $q->where('teacher_id', $teacherId))
                        ->orWhereHas('bookings.teachingGroup.assignment', fn ($q) => $q->where('teacher_id', $teacherId));
                });
            })
            ->when($groupId, function (Builder $query) use ($groupId): void {
                $query->where(function (Builder $subQ) use ($groupId): void {
                    $subQ->whereHas('subscriptions', fn ($q) => $q->where('teaching_group_id', $groupId))
                        ->orWhereHas('bookings', fn ($q) => $q->where('teaching_group_id', $groupId));
                });
            })
            ->when($startAt, fn (Builder $query) => $query->where('created_at', '>=', $startAt))
            ->when($endAt, fn (Builder $query) => $query->where('created_at', '<=', $endAt))
            ->orderBy('name');

        $students = $studentsQuery->get();

        $students->each(function (User $student) use ($gradeLevels, $attendeeRecords): void {
            $studentAttendees = $attendeeRecords->get($student->id, collect());
            $sessionsAttendedCount = $studentAttendees->count();
            $totalDurationSeconds = $studentAttendees->sum(fn (LiveSessionAttendee $att) => $att->durationSeconds());
            $totalMinutes = (int) round($totalDurationSeconds / 60);
            $totalHours = round($totalMinutes / 60, 1);

            $activeSubs = $student->subscriptions->filter(fn (Subscription $sub) => $sub->isActive());
            $activeSubsCount = $activeSubs->count();

            $subsDetails = $activeSubs->map(function (Subscription $sub): string {
                $subjectName = $sub->assignment?->subject?->name ?? $sub->group?->assignment?->subject?->name ?? 'مادة';
                $teacherName = $sub->assignment?->teacher?->name ?? $sub->group?->assignment?->teacher?->name ?? 'معلم';
                $typeName = $sub->type === 'private' ? 'مرافقة خاصة' : ($sub->group?->name ?? 'مجموعة');

                return "{$subjectName} ({$teacherName} - {$typeName})";
            })->unique()->values()->join('، ');

            $parentName = $student->parents->pluck('name')->filter()->join('، ') ?: '—';
            $parentPhone = $student->parents->pluck('phone')->filter()->join('، ') ?: '—';
            $gradeName = $gradeLevels->get($student->grade_level, $student->grade_level ?: 'غير محدد');

            $student->setAttribute('grade_name', $gradeName);
            $student->setAttribute('parent_name', $parentName);
            $student->setAttribute('parent_phone', $parentPhone);
            $student->setAttribute('active_subscriptions_count', $activeSubsCount);
            $student->setAttribute('active_subscriptions_details', $subsDetails ?: 'لا توجد اشتراكات نشطة');
            $student->setAttribute('sessions_attended_count', $sessionsAttendedCount);
            $student->setAttribute('attendance_minutes', $totalMinutes);
            $student->setAttribute('attendance_hours', $totalHours);
        });

        // 7. Summary Metrics
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

        $totalActiveSubscriptions = Subscription::active()->count();
        $totalRegisteredStudents = User::role('student')->count();
        $allPlatformMinutes = (int) round($attendeeRecords->flatten()->sum(fn (LiveSessionAttendee $att) => $att->durationSeconds()) / 60);

        $filterSummary = $this->buildFilterSummary(
            $reportType,
            $filters,
            $teachers,
            $students,
            $groups,
        );

        return [
            'printMode'     => $print,
            'reportType'    => $reportType,
            'filters'       => [
                'report_type'  => $reportType,
                'start_date'   => $start,
                'end_date'     => $end,
                'teacher_id'   => $teacherId,
                'student_id'   => $studentId,
                'session_type' => $sessionType ?? 'all',
                'group_id'     => $groupId,
                'status'       => $filters['status'] ?? '',
                'print'        => $print,
            ],
            'filterSummary' => $filterSummary,
            'teachers'      => $teachers,
            'students'      => $students,
            'groups'        => $groups,
            'sessions'      => $sessions,
            'attendance'    => $attendance,
            'summary'       => [
                'teachers'               => $teachers->count(),
                'groups'                 => $groups->count(),
                'students'               => $confirmedGroupStudents + $privateStudents,
                'students_registered'    => $totalRegisteredStudents,
                'active_subscriptions'   => $totalActiveSubscriptions,
                'sessions'               => $sessionCount,
                'live_sessions'          => $liveCount,
                'ended_sessions'         => $endedCount,
                'attendance_records'     => $attendanceTotal,
                'total_attendance_hours' => round($allPlatformMinutes / 60, 1),
            ],
        ];
    }

    /**
     * Human-readable summary of applied filters.
     *
     * @param array<string, mixed> $filters
     * @param Collection<int, User> $teachers
     * @param Collection<int, User> $students
     * @param Collection<int, TeachingGroup> $groups
     */
    public function buildFilterSummary(
        string $reportType,
        array $filters,
        Collection $teachers,
        Collection $students,
        Collection $groups,
    ): string {
        $teacher = $teachers->firstWhere('id', (int) ($filters['teacher_id'] ?? 0));
        $student = $students->firstWhere('id', (int) ($filters['student_id'] ?? 0));
        $group   = $groups->firstWhere('id', (int) ($filters['group_id'] ?? 0));

        $sessionTypeNames = [
            'all'     => 'كل الأنواع',
            'private' => 'مرافقة خاصة',
            'free'    => 'مجانية / تجريبية',
            'group'   => 'مجموعات دراسية',
        ];

        $statusLabels = [
            'scheduled' => 'مجدولة',
            'live'      => 'مباشرة الآن',
            'ended'     => 'منتهية',
            'cancelled' => 'ملغاة',
        ];

        $catMap = [
            'all'        => 'التقرير الشامل للمنصة',
            'teachers'   => 'تقرير المعلمين والتكليفات',
            'students'   => 'تقرير بيانات ومتابعة الطلاب',
            'attendance' => 'سجل حضور الطلاب الفعلي',
            'sessions'   => 'تقرير الحصص المباشرة',
            'groups'     => 'تقرير المجموعات والدورات',
        ];

        $values = [];
        if ($reportType && $reportType !== 'all') {
            $values[] = 'الفئة: ' . ($catMap[$reportType] ?? $reportType);
        }
        if ($teacher) {
            $values[] = 'المدرس: ' . $teacher->name;
        }
        if ($student) {
            $values[] = 'الطالب: ' . $student->name;
        }
        if ($group) {
            $values[] = 'المجموعة: ' . $group->name;
        }
        if (! empty($filters['session_type']) && $filters['session_type'] !== 'all') {
            $values[] = 'نوع الحصة: ' . ($sessionTypeNames[$filters['session_type']] ?? $filters['session_type']);
        }
        if (! empty($filters['status'])) {
            $values[] = 'الحالة: ' . ($statusLabels[$filters['status']] ?? $filters['status']);
        }
        if (! empty($filters['start_date'])) {
            $values[] = 'من ' . $filters['start_date'];
        }
        if (! empty($filters['end_date'])) {
            $values[] = 'إلى ' . $filters['end_date'];
        }

        return count($values) ? implode(' — ', $values) : 'كافة بيانات المنصة دون فلاتر';
    }
}
