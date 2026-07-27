<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Communication\Notifications\LiveSessionStartedNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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
            'privateSessionSlot:id,starts_at,ends_at',
            'attendees:id,live_session_id,user_id,joined_at,left_at',
        ])
            ->where('teacher_id', $teacherId)
            ->latest('scheduled_at')
            ->get();

        $sessions->each(function (LiveSession $session): void {
            $session->setAttribute('attendees_count', $session->attendees->pluck('user_id')->unique()->count());
            $session->setAttribute(
                'attendance_minutes',
                (int) round($session->attendees->sum(fn (LiveSessionAttendee $attendee) => $attendee->durationSeconds()) / 60),
            );
        });

        return Inertia::render('Teacher/LiveSessions', [
            'sessions'    => $sessions,
            'assignments' => $assignments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_type'             => ['required', 'in:group,private'],
            'teaching_group_id'       => ['nullable', 'integer', 'exists:teaching_groups,id'],
            'private_session_slot_id' => ['nullable', 'integer', 'exists:private_session_slots,id'],
            'scheduled_date'          => ['nullable', 'date', 'after:today'],
            'title'                   => ['required', 'string', 'max:255'],
            'description'             => ['nullable', 'string'],
            'room_id'                 => ['nullable', 'string'],
        ]);

        $group       = null;
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

            $schedule    = $group->schedules->firstWhere('day_of_week', $date->dayOfWeek);
            $startTime   = $schedule->start_time ?? $group->start_time;
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
            'teacher_id'              => Auth::id(),
            'teaching_group_id'       => $group?->id,
            'private_session_slot_id' => $privateSlot?->id,
            'title'                   => $validated['title'],
            'description'             => $validated['description'] ?? null,
            'room_id'                 => $validated['room_id'] ?? null,
            'scheduled_at'            => $scheduledAt,
            'status'                  => LiveSession::STATUS_SCHEDULED,
        ]);

        return back()->with('success', 'تم إنشاء الحصة بنجاح.');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $session = LiveSession::findOrFail($id);

        abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');

        $validated = $request->validate([
            'status'        => ['required', 'in:scheduled,live,ended'],
            'recording_url' => ['nullable', 'url'],
        ]);

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

        $session->save();

        return back()->with('success', 'تم تحديث حالة الحصة.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $session = LiveSession::findOrFail($id);

        abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');

        $session->delete();

        return back()->with('success', 'تم حذف الحصة.');
    }

    // ─── Internals ────────────────────────────────────────────────

    private function assertOwnsAssignment(?TeachingAssignment $assignment): void
    {
        abort_unless($assignment && $assignment->teacher_id === Auth::id(), 403, 'غير مصرح.');
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
}
