<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Communication\Notifications\AdminLiveSessionStatusNotification;
use App\Domain\Communication\Notifications\LiveSessionStartedNotification;
use App\Domain\Communication\Notifications\SessionApologySubmittedNotification;
use App\Domain\Communication\Notifications\SessionScheduleChangedNotification;
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
            'privateSessionSlot:id,starts_at,ends_at,is_free_intro',
            'privateSessionSlot.booking.student:id,name,email',
            'attendees:id,live_session_id,user_id,joined_at,left_at',
            'apology:id,live_session_id,reason,status,makeup_session_id,makeup_scheduled_at,deduction_amount,admin_note,teacher_payout_id',
            'apology.makeupSession:id,title,scheduled_at,status',
        ])
            ->where('teacher_id', $teacherId)
            ->latest('scheduled_at')
            ->get();

        $sessions->each(function (LiveSession $session): void {
            $students = $this->eligibleStudents($session);
            $presentIds = $session->attendees->pluck('user_id')->unique();

            $session->setAttribute('attendance_students', $students->map(fn (User $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'present' => $presentIds->contains($student->id),
            ])->values());
            $session->setAttribute('attendees_count', $students->whereIn('id', $presentIds)->count());
            $session->setAttribute(
                'attendance_minutes',
                (int) round($session->attendees->sum(fn (LiveSessionAttendee $attendee) => $attendee->durationSeconds()) / 60),
            );
        });

        return Inertia::render('Teacher/LiveSessions', [
            'sessions' => $sessions,
            'assignments' => $assignments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_type' => ['required', 'in:group,private'],
            'teaching_group_id' => ['nullable', 'integer', 'exists:teaching_groups,id'],
            'private_session_slot_id' => ['nullable', 'integer', 'exists:private_session_slots,id'],
            'scheduled_date' => ['nullable', 'date', 'after:today'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'room_id' => ['required', 'url', 'max:2048'],
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
            ->where('scheduled_at', $scheduledAt)
            ->when($group, fn ($q) => $q->where('teaching_group_id', $group->id))
            ->when($privateSlot, fn ($q) => $q->where('private_session_slot_id', $privateSlot->id))
            ->exists();

        abort_if($duplicate, 422, 'يوجد بث مجدول بالفعل لهذا الموعد.');

        LiveSession::create([
            'teacher_id' => Auth::id(),
            'teaching_group_id' => $group?->id,
            'private_session_slot_id' => $privateSlot?->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'room_id' => $validated['room_id'],
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
                    if (filled($value) && ! YouTubeUrl::isValid((string) $value)) {
                        $fail('رابط التسجيل يجب أن يكون رابط فيديو صحيحًا من YouTube.');
                    }
                },
            ],
        ]);

        if ($validated['status'] === LiveSession::STATUS_LIVE && ! filter_var($session->room_id, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'meeting_url' => 'أضف رابط الاجتماع قبل بدء الحصة.',
            ]);
        }

        if ($validated['status'] === LiveSession::STATUS_LIVE && ! $session->isLive()) {
            $session->started_at = now();
            $this->notifyAttendees($session);
        } elseif ($validated['status'] === LiveSession::STATUS_ENDED && $session->status !== LiveSession::STATUS_ENDED) {
            $session->ended_at = now();
        }

        $session->status = $validated['status'];

        if (isset($validated['recording_url'])) {
            $session->recording_url = $validated['recording_url'];
        }

        DB::transaction(function () use ($session): void {
            $session->save();

            if ($session->status === LiveSession::STATUS_ENDED && filled($session->recording_url)) {
                $this->publishRecording($session);
            }
        });

        if (in_array($validated['status'], [LiveSession::STATUS_LIVE, LiveSession::STATUS_ENDED], true)) {
            $session->load([
                'teacher:id,name',
                'teachingGroup:id,name,teaching_assignment_id',
                'teachingGroup.assignment.subject:id,name',
                'attendees:id,live_session_id,user_id',
            ]);

            foreach (User::role('admin')->get() as $admin) {
                $admin->notify(new AdminLiveSessionStatusNotification($session, $validated['status']));
            }
        }

        return back()->with('success', 'تم تحديث حالة الحصة.');
    }

    public function updateMeetingLink(Request $request, int $id): RedirectResponse
    {
        $session = LiveSession::findOrFail($id);

        abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');
        abort_unless($session->status === LiveSession::STATUS_SCHEDULED, 422, 'يمكن تعديل رابط حصة مجدولة فقط.');

        $data = $request->validate([
            'meeting_url' => ['required', 'url', 'max:2048'],
        ]);

        $session->update(['room_id' => $data['meeting_url']]);

        return back()->with('success', 'تم حفظ رابط الاجتماع.');
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
            'student_ids' => ['present', 'array'],
            'student_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $eligibleIds = $this->eligibleStudents($session)->pluck('id');
        $presentIds = collect($data['student_ids'] ?? [])->map(fn ($studentId) => (int) $studentId)->unique();

        if ($presentIds->diff($eligibleIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'student_ids' => 'لا يمكن تسجيل حضور طالب غير مشترك في هذه الحصة.',
            ]);
        }

        DB::transaction(function () use ($session, $eligibleIds, $presentIds): void {
            LiveSessionAttendee::where('live_session_id', $session->id)
                ->whereIn('user_id', $eligibleIds)
                ->delete();

            $joinedAt = $session->started_at ?? $session->scheduled_at ?? now();
            $leftAt = $session->ended_at ?? now();

            foreach ($presentIds as $studentId) {
                LiveSessionAttendee::create([
                    'live_session_id' => $session->id,
                    'user_id' => $studentId,
                    'joined_at' => $joinedAt,
                    'left_at' => $leftAt,
                ]);
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
                'room_id' => $original->room_id,
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

    /** Publish the YouTube recording once and permanently link it to its live class. */
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

        $material = GroupMaterial::create([
            'curriculum_unit_id' => $unit->id,
            'academic_term_id' => $termId,
            'title' => $session->title,
            'description' => $session->description,
            'video_url' => $session->recording_url,
            'duration_seconds' => min($duration, 86400),
            'order' => ((int) $unit->lessons()->max('order')) + 1,
            'is_free_preview' => false,
        ]);

        $session->update([
            'lesson_id' => $material->id,
            'is_published_as_lesson' => true,
        ]);
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
}
