<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Application\Subscription\Services\SubscriptionRenewalReminderService;
use App\Application\User\Services\ParentStudentLinkService;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Communication\Notifications\AdminLiveSessionStatusNotification;
use App\Domain\Communication\Notifications\LiveSessionStartedNotification;
use App\Domain\Communication\Notifications\SessionApologySubmittedNotification;
use App\Domain\Communication\Notifications\SessionScheduleChangedNotification;
use App\Domain\Communication\Notifications\StudentLiveSessionActivityNotification;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionApology;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupLesson;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Support\YouTubeUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live classrooms are scheduled straight off the teacher's own timetable:
 * either a weekly group meeting, or a private slot a student has booked.
 */
class LiveSessionController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalReminderService $renewalReminders,
        private readonly ParentStudentLinkService $parentStudentLinks,
    ) {}

    public function index(): Response
    {
        $teacherId = Auth::id();

        $assignments = TeachingAssignment::with([
            'subject:id,name',
            'gradeLevel:id,key,name',
            'groups' => fn ($q) => $q->where('is_active', true)->with('schedules')->orderBy('day_of_week')->orderBy('start_time'),
            // A private class only exists once a student confirms the slot.
            'privateSlots' => fn ($q) => $q->where('status', 'booked')->where('starts_at', '>=', now())->orderBy('starts_at'),
        ])
            ->where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->get();

        $sessions = LiveSession::with([
            'teachingGroup:id,name,teaching_assignment_id',
            'teachingGroup.assignment.subject:id,name',
            'privateSessionSlot:id,teaching_assignment_id,starts_at,ends_at,is_free_intro',
            'privateSessionSlot.assignment.subject:id,name',
            'privateSessionSlot.booking.student:id,name,email',
            'attendees:id,live_session_id,user_id,joined_at,left_at',
            'attendees.user:id,name,email',
            'apology:id,live_session_id,reason,status,makeup_session_id,makeup_scheduled_at,deduction_amount,admin_note,teacher_payout_id',
            'apology.makeupSession:id,title,scheduled_at,status',
        ])
            ->where('teacher_id', $teacherId)
            ->latest('scheduled_at')
            ->get();

        $sessions->each(function (LiveSession $session): void {
            $students = $this->eligibleStudents($session);
            $studentIds = $students->pluck('id');
            $studentAttendees = $session->attendees->whereIn('user_id', $studentIds);
            $presentIds = $studentAttendees->pluck('user_id')->unique();

            $session->setAttribute('attendance_students', $students->map(fn (User $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'present' => $presentIds->contains($student->id),
                'visits' => $studentAttendees->where('user_id', $student->id)->map(fn (LiveSessionAttendee $attendee) => [
                    'id' => $attendee->id,
                    'joined_at' => $attendee->joined_at?->toIso8601String(),
                    'left_at' => $attendee->left_at?->toIso8601String(),
                    'minutes' => (int) round($attendee->durationSeconds() / 60),
                ])->values(),
            ])->values());
            $session->setAttribute('attendees_count', $presentIds->count());
            $session->setAttribute(
                'attendance_minutes',
                (int) round($studentAttendees->sum(fn (LiveSessionAttendee $attendee) => $attendee->durationSeconds()) / 60),
            );
        });

        $attendanceReport = $sessions
            ->flatMap(fn (LiveSession $session) => $session->attendees
                ->where('user_id', '!=', $session->teacher_id)
                ->map(fn (LiveSessionAttendee $attendee) => [
                    'id' => $attendee->id,
                    'student' => $attendee->user?->only(['id', 'name', 'email']),
                    'session' => $session->title,
                    'subject' => $session->teachingGroup?->assignment?->subject?->name
                        ?? $session->privateSessionSlot?->assignment?->subject?->name,
                    'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                    'joined_at' => $attendee->joined_at?->toIso8601String(),
                    'left_at' => $attendee->left_at?->toIso8601String(),
                    'minutes' => (int) round($attendee->durationSeconds() / 60),
                ]))
            ->sortByDesc('joined_at')
            ->values();

        return Inertia::render('Teacher/LiveSessions', [
            'sessions' => $sessions,
            'assignments' => $assignments,
            'attendanceReport' => $attendanceReport,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_type' => ['required', 'in:group,private'],
            'teaching_group_id' => ['exclude_unless:source_type,group', 'required', 'integer', 'min:1', 'exists:teaching_groups,id'],
            'private_session_slot_id' => ['exclude_unless:source_type,private', 'required', 'integer', 'min:1', 'exists:private_session_slots,id'],
            'scheduled_date' => ['exclude_unless:source_type,group', 'required', 'date', 'after:today'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // External meeting links were retired in favor of Jitsi rooms.
            'room_id' => ['prohibited'],
        ]);

        $group = null;
        $privateSlot = null;

        if ($validated['source_type'] === 'group') {
            abort_if(
                empty($validated['teaching_group_id']) || empty($validated['scheduled_date']),
                422,
                'اختر المجموعة وتاريخ الحصة.',
            );

            $group = TeachingGroup::with(['assignment', 'schedules'])->findOrFail($validated['teaching_group_id']);
            $this->assertOwnsAssignment($group->assignment);

            $date = Carbon::parse($validated['scheduled_date'], $group->timezone);

            // The date must land on one of the group's weekly meeting days.
            $meetingDays = $group->schedules->isNotEmpty()
                ? $group->schedules->pluck('day_of_week')->map(fn ($d) => (int) $d)
                : collect([(int) $group->day_of_week]);

            abort_unless($meetingDays->contains($date->dayOfWeek), 422, 'التاريخ لا يوافق أحد أيام المجموعة.');

            $schedule = $group->schedules->firstWhere('day_of_week', $date->dayOfWeek);
            $startTime = $schedule->start_time ?? $group->start_time;
            $scheduledAt = $date->setTimeFromTimeString($startTime)->utc();
        } else {
            abort_if(empty($validated['private_session_slot_id']), 422, 'اختر موعد الحصة الخاصة.');

            $privateSlot = PrivateSessionSlot::with(['assignment', 'booking'])->findOrFail($validated['private_session_slot_id']);
            $this->assertOwnsAssignment($privateSlot->assignment);

            abort_if(
                $privateSlot->status !== 'booked' || $privateSlot->booking?->status !== 'confirmed',
                422,
                'موعد الحصة الخاصة يجب أن يكون محجوزاً لطالب أولاً.',
            );

            $scheduledAt = $privateSlot->starts_at;
        }

        abort_if($scheduledAt->isPast(), 422, 'موعد الحصة يجب أن يكون في المستقبل.');

        $duplicate = LiveSession::where('teacher_id', Auth::id())
            ->whereIn('status', [LiveSession::STATUS_SCHEDULED, LiveSession::STATUS_LIVE])
            ->where('scheduled_at', $scheduledAt)
            ->exists();

        abort_if($duplicate, 422, 'لديك حصة أخرى مجدولة في نفس الموعد.');

        LiveSession::create([
            'teacher_id' => Auth::id(),
            'teaching_group_id' => $group?->id,
            'private_session_slot_id' => $privateSlot?->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'scheduled_at' => $scheduledAt,
            'status' => LiveSession::STATUS_SCHEDULED,
        ]);

        return back()->with('success', 'تم إنشاء الحصة بنجاح.');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $session = LiveSession::findOrFail($id);

        abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');

        $validated = $request->validate([
            'status' => ['required', 'in:scheduled,live,ended'],
            'recording_url' => [
                'nullable',
                'url',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (
                        filled($value)
                        && ! YouTubeUrl::isValid((string) $value)
                        && ! $this->isAllowedJitsiRecordingUrl((string) $value)
                    ) {
                        $fail('رابط التسجيل يجب أن يكون رابط YouTube أو رابط تسجيل معتمد من Jitsi.');
                    }
                },
            ],
        ]);

        $allowedTransitions = [
            // A scheduled class becomes live only after the teacher joins its room.
            LiveSession::STATUS_SCHEDULED => [LiveSession::STATUS_SCHEDULED],
            LiveSession::STATUS_LIVE => [LiveSession::STATUS_LIVE, LiveSession::STATUS_ENDED],
            // Re-saving an ended session is how the teacher publishes a recording later.
            LiveSession::STATUS_ENDED => [LiveSession::STATUS_ENDED],
            LiveSession::STATUS_CANCELLED => [],
        ];

        abort_unless(
            in_array($validated['status'], $allowedTransitions[$session->status] ?? [], true),
            422,
            'لا يمكن تنفيذ هذا الانتقال على حالة الحصة الحالية.',
        );

        if (filled($validated['recording_url'] ?? null) && $validated['status'] !== LiveSession::STATUS_ENDED) {
            throw ValidationException::withMessages([
                'recording_url' => 'يمكن إضافة التسجيل بعد إنهاء الحصة فقط.',
            ]);
        }

        $statusChanged = $validated['status'] !== $session->status;

        if ($validated['status'] === LiveSession::STATUS_LIVE && ! $session->isLive()) {
            $session->started_at = now();
            $this->notifyAttendees($session);
        } elseif ($validated['status'] === LiveSession::STATUS_ENDED && $session->status !== LiveSession::STATUS_ENDED) {
            $session->ended_at = now();
        }

        $session->status = $validated['status'];

        if (filled($validated['recording_url'] ?? null)) {
            $session->recording_url = $validated['recording_url'];
        }

        $studentsForcedOutIds = [];

        DB::transaction(function () use ($session, &$studentsForcedOutIds): void {
            $session->save();

            if ($session->status === LiveSession::STATUS_ENDED && $session->ended_at) {
                $studentsForcedOutIds = LiveSessionAttendee::query()
                    ->where('live_session_id', $session->id)
                    ->whereNull('left_at')
                    ->where('user_id', '!=', $session->teacher_id)
                    ->pluck('user_id')
                    ->all();

                LiveSessionAttendee::where('live_session_id', $session->id)
                    ->whereNull('left_at')
                    ->update([
                        'left_at' => $session->ended_at,
                        'updated_at' => now(),
                    ]);
            }

            if ($session->status === LiveSession::STATUS_ENDED && filled($session->recording_url)) {
                $this->publishRecording($session);
            }
        });

        if ($statusChanged && in_array($validated['status'], [LiveSession::STATUS_LIVE, LiveSession::STATUS_ENDED], true)) {
            $this->notifyAdminsAboutStatus($session, $validated['status']);
        }

        if ($statusChanged && $validated['status'] === LiveSession::STATUS_ENDED) {
            $this->notifyParentsAboutStudentIds(
                $session,
                $studentsForcedOutIds,
                StudentLiveSessionActivityNotification::ACTIVITY_LEFT,
            );
            $this->notifyParentsAboutSessionActivity(
                $session,
                StudentLiveSessionActivityNotification::ACTIVITY_ENDED,
            );
            $this->renewalReminders->sendForEndedSession($session);
        }

        return back()->with('success', 'تم تحديث حالة الحصة.');
    }

    /** Start a class only after the teacher has connected to its room. */
    public function startFromRoom(int $id): JsonResponse
    {
        $started = false;
        $session = DB::transaction(function () use ($id, &$started): LiveSession {
            $session = LiveSession::query()->lockForUpdate()->findOrFail($id);

            abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');
            abort_unless(
                in_array($session->status, [LiveSession::STATUS_SCHEDULED, LiveSession::STATUS_LIVE], true),
                422,
                'لا يمكن بدء هذه الحصة من الغرفة.',
            );

            if ($session->status === LiveSession::STATUS_SCHEDULED) {
                $session->update([
                    'status' => LiveSession::STATUS_LIVE,
                    'started_at' => now(),
                    'ended_at' => null,
                ]);
                $started = true;
            }

            return $session->fresh();
        });

        if ($started) {
            $this->notifyAttendees($session);
            $this->notifyAdminsAboutStatus($session, LiveSession::STATUS_LIVE);
        }

        return response()->json([
            'status' => $session->status,
            'started_at' => $session->started_at?->toIso8601String(),
        ]);
    }

    /** End a class from inside the room; only its teacher can call this. */
    public function endFromRoom(int $id): JsonResponse
    {
        $ended = false;
        $studentsForcedOutIds = [];
        $session = DB::transaction(function () use ($id, &$ended, &$studentsForcedOutIds): LiveSession {
            $session = LiveSession::query()->lockForUpdate()->findOrFail($id);

            abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');
            abort_unless(
                in_array($session->status, [LiveSession::STATUS_LIVE, LiveSession::STATUS_ENDED], true),
                422,
                'لا يمكن إنهاء هذه الحصة الآن.',
            );

            if ($session->status === LiveSession::STATUS_LIVE) {
                $session->update([
                    'status' => LiveSession::STATUS_ENDED,
                    'ended_at' => now(),
                ]);
                $ended = true;

                $studentsForcedOutIds = LiveSessionAttendee::query()
                    ->where('live_session_id', $session->id)
                    ->whereNull('left_at')
                    ->where('user_id', '!=', $session->teacher_id)
                    ->pluck('user_id')
                    ->all();

                LiveSessionAttendee::where('live_session_id', $session->id)
                    ->whereNull('left_at')
                    ->update([
                        'left_at' => $session->ended_at,
                        'updated_at' => now(),
                    ]);
            }

            return $session->fresh();
        });

        if ($ended) {
            $this->notifyAdminsAboutStatus($session, LiveSession::STATUS_ENDED);
            $this->notifyParentsAboutStudentIds(
                $session,
                $studentsForcedOutIds,
                StudentLiveSessionActivityNotification::ACTIVITY_LEFT,
            );
            $this->notifyParentsAboutSessionActivity(
                $session,
                StudentLiveSessionActivityNotification::ACTIVITY_ENDED,
            );
            $this->renewalReminders->sendForEndedSession($session);
        }

        return response()->json([
            'status' => $session->status,
            'ended_at' => $session->ended_at?->toIso8601String(),
        ]);
    }

    /**
     * Save the server-side Jitsi recording link emitted after a file recording
     * finishes. The browser never needs to download or re-upload the video.
     */
    public function storeRecordingLink(Request $request, int $id): JsonResponse
    {
        $session = LiveSession::findOrFail($id);

        abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');
        abort_unless(
            in_array($session->status, [LiveSession::STATUS_LIVE, LiveSession::STATUS_ENDED], true),
            422,
            'لا يمكن حفظ تسجيل لحصة لم تبدأ أو انتهت.',
        );

        $validated = $request->validate([
            'recording_url' => ['required', 'url', 'max:2048'],
        ]);

        abort_unless(
            $this->isAllowedJitsiRecordingUrl($validated['recording_url']),
            422,
            'رابط التسجيل صادر من خادم غير معتمد.',
        );

        $sessionId = $session->id;

        DB::transaction(function () use ($sessionId, $validated): void {
            $session = LiveSession::query()->lockForUpdate()->findOrFail($sessionId);

            if ($session->is_published_as_lesson && $session->lesson_id) {
                return;
            }

            $session->recording_url = $validated['recording_url'];
            $session->save();

            if ($session->status === LiveSession::STATUS_ENDED) {
                $this->publishRecording($session);
            }
        });

        $session->refresh();

        return response()->json([
            'saved' => filled($session->recording_url),
            'published' => (bool) $session->is_published_as_lesson,
        ]);
    }

    public function updateAttendance(Request $request, int $id): RedirectResponse
    {
        $session = LiveSession::findOrFail($id);

        abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');
        abort_unless(
            in_array($session->status, [LiveSession::STATUS_LIVE, LiveSession::STATUS_ENDED], true),
            422,
            'يمكن تسجيل الحضور لحصة بدأت أو انتهت فقط.',
        );

        $data = $request->validate([
            'student_ids' => ['present', 'array', 'max:1000'],
            'student_ids.*' => ['integer', 'min:1', 'distinct', 'exists:users,id'],
        ]);

        $eligibleIds = $this->eligibleStudents($session)->pluck('id');
        $presentIds = collect($data['student_ids'] ?? [])->map(fn ($studentId) => (int) $studentId)->unique();

        if ($presentIds->diff($eligibleIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'student_ids' => 'لا يمكن تسجيل حضور طالب غير مشترك في هذه الحصة.',
            ]);
        }

        DB::transaction(function () use ($session, $presentIds): void {
            $joinedAt = $session->started_at ?? $session->scheduled_at ?? now();
            $leftAt = $session->ended_at ?? now();

            foreach ($presentIds as $studentId) {
                // Preserve an existing attendance record; otherwise the
                // teacher's confirmed roll is the source of truth.
                if (! LiveSessionAttendee::where('live_session_id', $session->id)
                    ->where('user_id', $studentId)
                    ->exists()) {
                    LiveSessionAttendee::create([
                        'live_session_id' => $session->id,
                        'user_id' => $studentId,
                        'joined_at' => $joinedAt,
                        'left_at' => $leftAt,
                    ]);
                }
            }
        });

        return back()->with('success', 'تم حفظ كشف حضور الحصة.');
    }

    public function apologize(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $apology = DB::transaction(function () use ($id, $data): LiveSessionApology {
            $session = LiveSession::lockForUpdate()->findOrFail($id);

            abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');
            abort_unless($session->status === LiveSession::STATUS_SCHEDULED, 422, 'يمكن الاعتذار عن حصة مجدولة فقط.');
            abort_if($session->apology()->exists(), 422, 'تم تقديم اعتذار عن هذه الحصة بالفعل.');

            $apology = LiveSessionApology::create([
                'live_session_id' => $session->id,
                'teacher_id' => Auth::id(),
                'reason' => $data['reason'],
                'status' => LiveSessionApology::STATUS_PENDING,
            ]);

            $session->update(['status' => LiveSession::STATUS_CANCELLED]);

            return $apology;
        });

        $apology->load(['teacher:id,name', 'session']);
        foreach (User::role('admin')->get() as $admin) {
            $admin->notify(new SessionApologySubmittedNotification($apology));
        }
        $this->notifyStudents(
            $apology->session,
            new SessionScheduleChangedNotification($apology->session, false, $apology->reason),
        );

        return back()->with('success', 'تم إرسال الاعتذار للإدارة. يمكنك تحديد حصة تعويضية ما دام الخصم لم يُسجل.');
    }

    public function scheduleMakeup(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $makeup = DB::transaction(function () use ($id, $data): LiveSession {
            $apology = LiveSessionApology::with('session')
                ->lockForUpdate()
                ->findOrFail($id);

            abort_if($apology->teacher_id !== Auth::id(), 403, 'غير مصرح.');
            abort_unless($apology->status === LiveSessionApology::STATUS_PENDING, 422, 'تم حسم الاعتذار بالفعل.');

            $original = $apology->session;
            $scheduledAt = Carbon::parse($data['scheduled_at'])->utc();

            $conflict = LiveSession::where('teacher_id', Auth::id())
                ->whereIn('status', [LiveSession::STATUS_SCHEDULED, LiveSession::STATUS_LIVE])
                ->where('scheduled_at', $scheduledAt)
                ->exists();

            abort_if($conflict, 422, 'لديك حصة أخرى في نفس الموعد.');

            $makeup = LiveSession::create([
                'teacher_id' => $original->teacher_id,
                'teaching_group_id' => $original->teaching_group_id,
                'private_session_slot_id' => $original->private_session_slot_id,
                'title' => 'حصة تعويضية — '.$original->title,
                'description' => 'تعويض عن الحصة الملغاة بتاريخ '.$original->scheduled_at?->format('Y-m-d H:i'),
                'scheduled_at' => $scheduledAt,
                'status' => LiveSession::STATUS_SCHEDULED,
            ]);

            TeachingGroupLesson::where('live_session_id', $original->id)
                ->update(['live_session_id' => $makeup->id]);

            $apology->update([
                'status' => LiveSessionApology::STATUS_MAKEUP_SCHEDULED,
                'makeup_session_id' => $makeup->id,
                'makeup_scheduled_at' => $scheduledAt,
                'resolved_at' => now(),
            ]);

            return $makeup;
        });

        $this->notifyStudents($makeup, new SessionScheduleChangedNotification($makeup, true));

        return back()->with('success', 'تم تحديد الحصة التعويضية وإغلاق الاعتذار بدون خصم.');
    }

    // ─── Internals ────────────────────────────────────────────────

    private function assertOwnsAssignment(?TeachingAssignment $assignment): void
    {
        abort_unless($assignment && $assignment->teacher_id === Auth::id(), 403, 'غير مصرح.');
    }

    /** @return Collection<int, User> */
    private function eligibleStudents(LiveSession $session): Collection
    {
        return User::query()
            ->whereIn('id', SessionBooking::query()
                ->where('status', 'confirmed')
                ->when($session->teaching_group_id, fn ($query) => $query->where('teaching_group_id', $session->teaching_group_id))
                ->when($session->private_session_slot_id, fn ($query) => $query->where('private_session_slot_id', $session->private_session_slot_id))
                ->when(! $session->teaching_group_id && ! $session->private_session_slot_id, fn ($query) => $query->whereRaw('1 = 0'))
                ->select('student_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /** Publish a completed recording once and permanently link it to its live class. */
    private function publishRecording(LiveSession $session): void
    {
        if ($session->is_published_as_lesson && $session->lesson_id) {
            return;
        }

        $session->loadMissing(['teachingGroup', 'privateSessionSlot']);
        $assignmentId = $session->teachingGroup?->teaching_assignment_id
            ?? $session->privateSessionSlot?->teaching_assignment_id;

        if (! $assignmentId) {
            return;
        }

        $termId = $session->teachingGroup?->academic_term_id ?? AcademicTerm::currentOrNext()?->id;

        if (! $termId) {
            throw ValidationException::withMessages([
                'recording_url' => 'يجب إعداد فصل دراسي قبل نشر تسجيل الحصة.',
            ]);
        }

        $unit = CurriculumUnit::firstOrCreate(
            [
                'teaching_assignment_id' => $assignmentId,
                'academic_term_id' => $termId,
                'order' => 1,
            ],
            ['title' => 'الوحدة الأولى', 'is_published' => true],
        );

        $duration = $session->started_at && $session->ended_at
            ? max(0, $session->started_at->diffInSeconds($session->ended_at))
            : 0;
        $isFreePreview = (bool) ($session->privateSessionSlot?->is_free_intro ?? false);

        $material = GroupMaterial::create([
            'curriculum_unit_id' => $unit->id,
            'academic_term_id' => $termId,
            'title' => $session->title,
            'description' => $session->description,
            'video_url' => $session->recording_url,
            'duration_seconds' => min($duration, 86400),
            'order' => ((int) $unit->lessons()->max('order')) + 1,
            'is_free_preview' => $isFreePreview,
        ]);

        $session->update([
            'lesson_id' => $material->id,
            'is_published_as_lesson' => true,
        ]);
    }

    private function isAllowedJitsiRecordingUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            return false;
        }

        $jitsiHost = strtolower(rtrim(explode(':', (string) config('services.jitsi.domain'), 2)[0], '.'));
        $allowedHosts = collect(config('services.jitsi.recording.allowed_hosts', []))
            ->map(fn (mixed $host): string => strtolower(rtrim((string) $host, '.')))
            ->filter()
            ->push($jitsiHost)
            ->unique()
            ->all();

        return in_array(strtolower(rtrim((string) $parts['host'], '.')), $allowedHosts, true);
    }

    /** Everyone holding a confirmed seat in this session gets a ping. */
    private function notifyAttendees(LiveSession $session): void
    {
        $studentIds = SessionBooking::where('status', 'confirmed')
            ->when($session->teaching_group_id, fn ($q) => $q->where('teaching_group_id', $session->teaching_group_id))
            ->when($session->private_session_slot_id, fn ($q) => $q->where('private_session_slot_id', $session->private_session_slot_id))
            ->when(
                ! $session->teaching_group_id && ! $session->private_session_slot_id,
                fn ($q) => $q->whereRaw('1 = 0'), // Unattached session — nobody to notify.
            )
            ->pluck('student_id');

        foreach (User::whereIn('id', $studentIds)->get() as $student) {
            /** @var User $student */
            $student->notify(new LiveSessionStartedNotification($session));
        }
    }

    private function notifyAdminsAboutStatus(LiveSession $session, string $status): void
    {
        $session->load([
            'teacher:id,name',
            'teachingGroup:id,name,teaching_assignment_id',
            'teachingGroup.assignment.subject:id,name',
            'attendees:id,live_session_id,user_id',
        ]);

        foreach (User::role('admin')->get() as $admin) {
            $admin->notify(new AdminLiveSessionStatusNotification($session, $status));
        }
    }

    private function notifyStudents(LiveSession $session, Notification $notification): void
    {
        $studentIds = SessionBooking::where('status', 'confirmed')
            ->when($session->teaching_group_id, fn ($query) => $query->where('teaching_group_id', $session->teaching_group_id))
            ->when($session->private_session_slot_id, fn ($query) => $query->where('private_session_slot_id', $session->private_session_slot_id))
            ->pluck('student_id');

        foreach (User::whereIn('id', $studentIds)->get() as $student) {
            $student->notify($notification);
        }
    }

    private function notifyParentsAboutSessionActivity(LiveSession $session, string $activity): void
    {
        $this->notifyParentsAboutStudents($session, $this->eligibleStudents($session), $activity);
    }

    private function notifyParentsAboutStudentIds(LiveSession $session, array $studentIds, string $activity): void
    {
        if ($studentIds === []) {
            return;
        }

        $this->notifyParentsAboutStudents(
            $session,
            User::query()->whereIn('id', $studentIds)->get(['id', 'name', 'email']),
            $activity,
        );
    }

    /** @param Collection<int, User> $students */
    private function notifyParentsAboutStudents(LiveSession $session, Collection $students, string $activity): void
    {
        foreach ($students as $student) {
            $this->parentStudentLinks->notifyLinkedParents(
                $student,
                new StudentLiveSessionActivityNotification($session, $student, $activity),
            );
        }
    }
}
