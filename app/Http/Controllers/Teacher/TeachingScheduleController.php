<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupLesson;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeachingScheduleController extends Controller
{
    public function index(): Response
    {
        $assignments = TeachingAssignment::with([
            'subject:id,name,name_en',
            'gradeLevel:id,key,name,name_en',
            'groups' => fn ($query) => $query
                ->with(['schedules', 'lessons.liveSession:id,scheduled_at,status'])
                ->withCount('activeBookings')
                ->orderBy('day_of_week')
                ->orderBy('start_time'),
            'privateSlots' => fn ($query) => $query
                ->where('status', '!=', 'cancelled')
                ->where('starts_at', '>=', now())
                ->with('booking.student:id,name')
                ->orderBy('starts_at'),
        ])
            ->where('teacher_id', Auth::id())
            ->where('is_active', true)
            ->latest()
            ->get();

        return Inertia::render('Teacher/TeachingSchedule', [
            'assignments' => $assignments,
        ]);
    }

    public function storeGroupLesson(Request $request, int $id): RedirectResponse
    {
        $group = TeachingGroup::with('assignment')->findOrFail($id);
        abort_if($group->assignment->teacher_id !== Auth::id(), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $position = ((int) $group->lessons()->max('position')) + 1;
        $group->lessons()->create([...$data, 'position' => $position, 'status' => 'pending']);

        return back()->with('success', 'تمت إضافة الحصة إلى خطة المجموعة.');
    }

    public function storeGroupSchedule(Request $request, int $id): RedirectResponse
    {
        $group = TeachingGroup::with('assignment')->findOrFail($id);
        abort_if($group->assignment->teacher_id !== Auth::id(), 403);

        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $duration = (int) Carbon::createFromFormat('H:i', $data['start_time'])
            ->diffInMinutes(Carbon::createFromFormat('H:i', $data['end_time']));

        if ($duration < 15 || $duration > 480) {
            return back()->withErrors(['end_time' => 'مدة الموعد يجب أن تكون بين 15 دقيقة و8 ساعات.']);
        }

        $conflict = TeachingGroupSchedule::whereHas(
            'group.assignment',
            fn ($query) => $query->where('teacher_id', Auth::id()),
        )
            ->where('day_of_week', $data['day_of_week'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();

        if ($conflict) {
            return back()->withErrors(['start_time' => 'الموعد يتعارض مع مجموعة أخرى في جدولك.']);
        }

        $schedule = $group->schedules()->create([
            ...$data,
            'duration_minutes' => $duration,
        ]);

        if ($group->schedules()->count() === 1) {
            $group->update([
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'duration_minutes' => $schedule->duration_minutes,
            ]);
        }

        return back()->with('success', 'تمت إضافة موعد المجموعة.');
    }

    public function destroyGroupSchedule(int $id): RedirectResponse
    {
        $schedule = TeachingGroupSchedule::with('group.assignment')->findOrFail($id);
        abort_if($schedule->group?->assignment?->teacher_id !== Auth::id(), 403);

        $group = $schedule->group;
        $schedule->delete();

        $first = $group->schedules()->first();
        $group->update($first ? [
            'day_of_week' => $first->day_of_week,
            'start_time' => $first->start_time,
            'end_time' => $first->end_time,
            'duration_minutes' => $first->duration_minutes,
        ] : [
            'day_of_week' => 0,
            'start_time' => '00:00',
            'end_time' => '00:00',
            'duration_minutes' => 0,
        ]);

        return back()->with('success', 'تم حذف موعد المجموعة.');
    }

    public function scheduleGroupLesson(int $id): RedirectResponse
    {
        return DB::transaction(function () use ($id): RedirectResponse {
            $lesson = TeachingGroupLesson::with(['group.assignment.gradeLevel', 'group.schedules'])
                ->lockForUpdate()
                ->findOrFail($id);
            $group = $lesson->group;

            abort_if($group->assignment->teacher_id !== Auth::id(), 403);
            abort_if($lesson->status !== 'pending', 422, 'هذه الحصة مجدولة بالفعل.');

            $nextLesson = $group->lessons()->where('status', 'pending')->orderBy('position')->first();
            abort_if(! $nextLesson || $nextLesson->id !== $lesson->id, 422, 'يجب جدولة الحصة الموجودة عليها الدور أولاً.');
            abort_if($group->schedules->isEmpty(), 422, 'لا يوجد موعد للمجموعة. أضف موعدًا من جدولك أولًا.');

            $session = LiveSession::create([
                'teacher_id' => Auth::id(),
                'teaching_group_id' => $group->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'scheduled_at' => $this->nextGroupOccurrence($group),
                'status' => LiveSession::STATUS_SCHEDULED,
            ]);

            $lesson->update(['live_session_id' => $session->id, 'status' => 'scheduled']);

            return back()->with('success', 'تمت جدولة الحصة المباشرة تلقائيًا في موعد المجموعة التالي.');
        });
    }

    public function redirectGroupLessonSchedule(int $id): RedirectResponse
    {
        $lesson = TeachingGroupLesson::with('group.assignment')->findOrFail($id);
        abort_if($lesson->group->assignment->teacher_id !== Auth::id(), 403);

        return redirect()->route('teacher.teaching-schedule')
            ->with('error', 'الجدولة عملية تأكيد. اضغط «جدولة حصة مباشرة» من خطة المجموعة.');
    }

    private function nextGroupOccurrence(TeachingGroup $group): Carbon
    {
        $latest = LiveSession::where('teaching_group_id', $group->id)->max('scheduled_at');
        $cursor = $latest
            ? Carbon::parse($latest, $group->timezone)->addMinute()
            : Carbon::now($group->timezone);
        $candidate = null;

        for ($offset = 0; $offset <= 14; $offset++) {
            $date = $cursor->copy()->startOfDay()->addDays($offset);

            foreach ($group->schedules as $schedule) {
                if ($date->dayOfWeek !== $schedule->day_of_week) {
                    continue;
                }

                $slot = $date->copy()->setTimeFromTimeString($schedule->start_time);

                if (! $slot->greaterThan($cursor)) {
                    continue;
                }

                $hasConflict = LiveSession::query()
                    ->where('teacher_id', $group->assignment->teacher_id)
                    ->whereIn('status', [LiveSession::STATUS_SCHEDULED, LiveSession::STATUS_LIVE])
                    ->where('scheduled_at', $slot->copy()->utc())
                    ->exists();

                if (! $hasConflict && (! $candidate || $slot->lessThan($candidate))) {
                    $candidate = $slot;
                }
            }

            if ($candidate) {
                break;
            }
        }

        abort_if(! $candidate, 422, 'تعذر تحديد الموعد التالي للمجموعة.');

        return $candidate->utc();
    }
}
