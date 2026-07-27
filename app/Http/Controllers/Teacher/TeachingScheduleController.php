<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupLesson;
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
            abort_if($group->schedules->isEmpty(), 422, 'لا يوجد موعد للمجموعة. تواصل مع الإدارة.');

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

                if ($slot->greaterThan($cursor) && (! $candidate || $slot->lessThan($candidate))) {
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
