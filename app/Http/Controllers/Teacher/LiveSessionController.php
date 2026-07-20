<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\LiveSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use Illuminate\Support\Carbon;
use App\Domain\Communication\Notifications\LiveSessionStartedNotification;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LiveSessionController extends Controller
{
    public function index(): Response
    {
        $teacherId = Auth::id();
        
        $courses = Course::where('teacher_id', $teacherId)
            ->with('subject:id,name', 'gradeLevel:id,key,name')
            ->get(['id', 'title', 'subject_id', 'grade_level']);
        $assignments = TeachingAssignment::with([
            'subject:id,name', 'gradeLevel:id,key,name',
            'groups' => fn ($query) => $query->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time'),
            // A private live class is created only after a student confirms the slot.
            'privateSlots' => fn ($query) => $query->where('status', 'booked')->where('starts_at', '>=', now())->orderBy('starts_at'),
        ])->where('teacher_id', $teacherId)->where('is_active', true)->get();

        $sessions = LiveSession::with(['course:id,title', 'teachingGroup:id,name', 'privateSessionSlot:id,starts_at,ends_at'])
            ->where('teacher_id', $teacherId)
            ->latest('scheduled_at')
            ->get();

        return Inertia::render('Teacher/LiveSessions', [
            'sessions' => $sessions,
            'courses'  => $courses,
            'assignments' => $assignments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_id'    => ['required', 'exists:courses,id'],
            'source_type'  => ['required', 'in:group,private'],
            'teaching_group_id' => ['nullable', 'integer', 'exists:teaching_groups,id'],
            'private_session_slot_id' => ['nullable', 'integer', 'exists:private_session_slots,id'],
            'scheduled_date' => ['nullable', 'date', 'after:today'],
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            // The server derives this from the selected group/private slot.
            'scheduled_at' => ['nullable', 'date'],
            'room_id'      => ['nullable', 'string'], // Link to Zoom/Meet
        ]);

        $course = Course::with('gradeLevel:id,key,name')->findOrFail($validated['course_id']);
        
        abort_if($course->teacher_id !== Auth::id(), 403, 'غير مصرح.');

        $group = null;
        $privateSlot = null;
        if ($validated['source_type'] === 'group') {
            abort_if(empty($validated['teaching_group_id']) || empty($validated['scheduled_date']), 422, 'اختر المجموعة وتاريخ الحصة.');
            $group = TeachingGroup::with('assignment.gradeLevel')->findOrFail($validated['teaching_group_id']);
            $this->assertAssignmentMatchesCourse($group->assignment, $course);
            $date = Carbon::parse($validated['scheduled_date'], $group->timezone);
            abort_if($date->dayOfWeek !== (int) $group->day_of_week, 422, 'التاريخ لا يوافق يوم المجموعة المحدد.');
            $scheduledAt = $date->setTimeFromTimeString($group->start_time)->utc();
        } else {
            abort_if(empty($validated['private_session_slot_id']), 422, 'اختر موعد البرايفيت.');
            $privateSlot = PrivateSessionSlot::with(['assignment.gradeLevel', 'booking'])->findOrFail($validated['private_session_slot_id']);
            $this->assertAssignmentMatchesCourse($privateSlot->assignment, $course);
            abort_if($privateSlot->status !== 'booked' || ! $privateSlot->booking || $privateSlot->booking->status !== 'confirmed', 422, 'موعد البرايفيت لازم يكون محجوزًا لطالب أولًا.');
            $scheduledAt = $privateSlot->starts_at;
        }

        abort_if($scheduledAt->isPast(), 422, 'موعد الحصة يجب أن يكون في المستقبل.');
        abort_if(LiveSession::where('teacher_id', Auth::id())->where('scheduled_at', $scheduledAt)->where(function ($query) use ($group, $privateSlot) {
            $query->when($group, fn ($q) => $q->where('teaching_group_id', $group->id))
                ->when($privateSlot, fn ($q) => $q->where('private_session_slot_id', $privateSlot->id));
        })->exists(), 422, 'يوجد بث مجدول بالفعل لهذا الموعد.');

        $validated['teacher_id'] = Auth::id();
        $validated['scheduled_at'] = $scheduledAt;
        $validated['teaching_group_id'] = $group?->id;
        $validated['private_session_slot_id'] = $privateSlot?->id;
        unset($validated['source_type'], $validated['scheduled_date']);
        $validated['status']     = 'scheduled';

        LiveSession::create($validated);

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

        if ($validated['status'] === 'live' && $session->status !== 'live') {
            $session->started_at = now();
            
            // Notify enrolled students
            if ($session->teaching_group_id) {
                $studentIds = SessionBooking::where('teaching_group_id', $session->teaching_group_id)->where('status', 'confirmed')->pluck('student_id');
            } elseif ($session->private_session_slot_id) {
                $studentIds = SessionBooking::where('private_session_slot_id', $session->private_session_slot_id)->where('status', 'confirmed')->pluck('student_id');
            } else {
                $studentIds = \App\Domain\Enrollment\Models\Enrollment::where('course_id', $session->course_id)->pluck('user_id');
            }
            $students = User::whereIn('id', $studentIds)->get();
            foreach ($students as $student) {
                /** @var User $student */
                $student->notify(new LiveSessionStartedNotification($session));
            }
        } elseif ($validated['status'] === 'ended' && $session->status !== 'ended') {
            $session->ended_at = now();
        }

        $session->status = $validated['status'];
        if (isset($validated['recording_url'])) {
            $session->recording_url = $validated['recording_url'];
        }

        $session->save();

        return back()->with('success', 'تم تحديث حالة الحصة وبدء البث المباشر.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $session = LiveSession::findOrFail($id);
        abort_if($session->teacher_id !== Auth::id(), 403, 'غير مصرح.');

        $session->delete();

        return back()->with('success', 'تم حذف الحصة.');
    }

    private function assertAssignmentMatchesCourse(TeachingAssignment $assignment, Course $course): void
    {
        abort_if($assignment->teacher_id !== Auth::id(), 403, 'غير مصرح.');
        abort_if($assignment->subject_id !== $course->subject_id || $assignment->grade_level_id !== optional($course->gradeLevel)->id, 422, 'المادة أو السنة الدراسية لا تطابق الجدول المختار.');
    }
}
