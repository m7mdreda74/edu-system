<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
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
use Throwable;

class TeachingScheduleController extends Controller
{
    public function index(): Response
    {
        $assignments = TeachingAssignment::with([
            'subject:id,name,name_en', 'gradeLevel:id,key,name,name_en',
            'groups' => fn ($query) => $query->with(['schedules', 'lessons.liveSession:id,scheduled_at,status'])->withCount('activeBookings')->orderBy('day_of_week')->orderBy('start_time'),
            'privateSlots' => fn ($query) => $query->where('starts_at', '>=', now())->orderBy('starts_at'),
        ])->where('teacher_id', Auth::id())->latest()->get();

        return Inertia::render('Teacher/TeachingSchedule', [
            'assignments' => $assignments,
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'name', 'name_en']),
            'gradeLevels' => GradeLevel::where('is_active', true)->orderBy('id')->get(['id', 'key', 'name', 'name_en']),
        ]);
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'private_monthly_price' => ['nullable', 'integer', 'min:0'],
            'accepts_private' => ['nullable', 'boolean'],
        ]);

        $pricing = [
            'private_monthly_price' => (int) ($data['private_monthly_price'] ?? 0),
            'accepts_private' => (bool) ($data['accepts_private'] ?? false),
        ];
        unset($data['private_monthly_price'], $data['accepts_private']);

        /** @var \App\Domain\User\Models\User $teacher */
        $teacher = Auth::user();

        // A teacher specialises in one subject. The first one they add becomes
        // that specialty; after which they add grades, not subjects.
        if ($teacher->subject_id === null) {
            $teacher->update(['subject_id' => $data['subject_id']]);
        } elseif ((int) $data['subject_id'] !== (int) $teacher->subject_id) {
            $teacher->loadMissing('subject');

            return back()->with('error', "أنت مسجّل كمعلم {$teacher->subject?->name}. لتغيير تخصصك تواصل مع الإدارة.");
        }

        if (TeachingAssignment::where('teacher_id', $teacher->id)->where($data)->exists()) {
            return back()->with('error', 'هذا الربط موجود بالفعل.');
        }

        TeachingAssignment::create([...$data, ...$pricing, 'teacher_id' => Auth::id(), 'currency' => 'QAR', 'is_active' => true]);

        return back()->with('success', 'تم ربط المدرس بالمادة والمرحلة الدراسية.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teaching_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'monthly_price' => ['required', 'integer', 'min:0'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
            'schedules' => ['nullable', 'array', 'min:1', 'max:7'],
            'schedules.*.day_of_week' => ['required_with:schedules', 'integer', 'between:0,6', 'distinct'],
            'schedules.*.start_time' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.end_time' => ['required_with:schedules', 'date_format:H:i'],
        ]);

        $scheduleRows = $data['schedules'] ?? [[
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]];
        $data['day_of_week'] = $scheduleRows[0]['day_of_week'];
        $data['start_time'] = $scheduleRows[0]['start_time'];
        $data['end_time'] = $scheduleRows[0]['end_time'];

        $assignment = $this->ownedAssignment((int) $data['teaching_assignment_id']);
        foreach ($scheduleRows as $schedule) {
            $rowDuration = $this->duration($schedule['start_time'], $schedule['end_time']);
            if ($rowDuration < 15 || $rowDuration > 480) {
                return back()->with('error', 'مدة كل موعد يجب أن تكون بين 15 دقيقة و8 ساعات.');
            }
            $conflict = TeachingGroup::whereHas('assignment', fn ($query) => $query->where('teacher_id', Auth::id()))
                ->where('is_active', true)
                ->whereHas('schedules', fn ($query) => $query
                    ->where('day_of_week', $schedule['day_of_week'])
                    ->where('start_time', '<', $schedule['end_time'])
                    ->where('end_time', '>', $schedule['start_time']))
                ->exists();
            if ($conflict) {
                return back()->with('error', 'أحد مواعيد المجموعة يتعارض مع مجموعة أخرى للمدرس.');
            }
        }
        $duration = $this->duration($data['start_time'], $data['end_time']);
        if ($duration < 15 || $duration > 480) {
            return back()->with('error', 'مدة المجموعة يجب أن تكون بين 15 دقيقة و8 ساعات.');
        }

        $overlap = TeachingGroup::where('teaching_assignment_id', $assignment->id)
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();

        if ($overlap) {
            return back()->with('error', 'موعد المجموعة يتعارض مع مجموعة أخرى في نفس اليوم.');
        }

        $group = TeachingGroup::create([
            ...$data,
            'teaching_assignment_id' => $assignment->id,
            'duration_minutes' => $duration,
            'timezone' => $data['timezone'] ?? 'Asia/Qatar',
            'is_active' => true,
        ]);

        foreach ($scheduleRows as $schedule) {
            $scheduleDuration = $this->duration($schedule['start_time'], $schedule['end_time']);
            if ($scheduleDuration < 15 || $scheduleDuration > 480) {
                $group->delete();
                return back()->with('error', 'مدة كل موعد يجب أن تكون بين 15 دقيقة و8 ساعات.');
            }
            $group->schedules()->create([...$schedule, 'duration_minutes' => $scheduleDuration]);
        }

        return back()->with('success', 'تم إنشاء المجموعة ومواعيدها وسعتها.');
    }

    public function storePrivateSlot(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teaching_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $assignment = $this->ownedAssignment((int) $data['teaching_assignment_id']);
        try {
            $starts = Carbon::parse($data['starts_at']);
            $ends = Carbon::parse($data['ends_at']);
        } catch (Throwable) {
            return back()->with('error', 'صيغة موعد البرايفيت غير صحيحة.');
        }

        if ($ends->lessThanOrEqualTo($starts) || $starts->diffInMinutes($ends) < 15 || $starts->diffInMinutes($ends) > 480) {
            return back()->with('error', 'مدة البرايفيت يجب أن تكون بين 15 دقيقة و8 ساعات.');
        }

        $overlap = PrivateSessionSlot::where('teaching_assignment_id', $assignment->id)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts)
            ->exists();

        if ($overlap) {
            return back()->with('error', 'هذا الموعد يتعارض مع موعد برايفيت آخر.');
        }

        PrivateSessionSlot::create([
            'teaching_assignment_id' => $assignment->id,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'timezone' => $data['timezone'] ?? 'Asia/Qatar',
            'status' => 'available',
        ]);

        return back()->with('success', 'تم إضافة موعد برايفيت متاح للحجز.');
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
                ->lockForUpdate()->findOrFail($id);
            $group = $lesson->group;
            abort_if($group->assignment->teacher_id !== Auth::id(), 403);
            abort_if($lesson->status !== 'pending', 422, 'هذه الحصة مجدولة بالفعل.');

            $nextLesson = $group->lessons()->where('status', 'pending')->orderBy('position')->first();
            abort_if(! $nextLesson || $nextLesson->id !== $lesson->id, 422, 'يجب جدولة الحصة الموجودة عليها الدور أولاً.');
            abort_if($group->schedules->isEmpty(), 422, 'أضف موعدًا واحدًا على الأقل للمجموعة.');

            $scheduledAt = $this->nextGroupOccurrence($group);
            $session = LiveSession::create([
                'teacher_id' => Auth::id(),
                'teaching_group_id' => $group->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
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
            ->with('error', 'الجدولة عملية تأكيد وليست رابطًا مباشرًا. اضغط «جدولة حصة مباشرة» من خطة المجموعة.');
    }

    public function destroyGroup(int $id): RedirectResponse
    {
        $group = TeachingGroup::with('assignment')->findOrFail($id);
        abort_if($group->assignment->teacher_id !== Auth::id(), 403);
        if ($group->activeBookings()->exists()) return back()->with('error', 'لا يمكن حذف مجموعة عليها حجوزات مؤكدة.');
        $group->delete();
        return back()->with('success', 'تم حذف المجموعة.');
    }

    public function destroyPrivateSlot(int $id): RedirectResponse
    {
        $slot = PrivateSessionSlot::with('assignment')->findOrFail($id);
        abort_if($slot->assignment->teacher_id !== Auth::id(), 403);
        if ($slot->booking()->where('status', 'confirmed')->exists()) return back()->with('error', 'لا يمكن حذف موعد محجوز.');
        $slot->update(['status' => 'cancelled']);
        return back()->with('success', 'تم إلغاء موعد البرايفيت.');
    }

    private function ownedAssignment(int $id): TeachingAssignment
    {
        return TeachingAssignment::where('id', $id)->where('teacher_id', Auth::id())->where('is_active', true)->firstOrFail();
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
                if ($date->dayOfWeek !== $schedule->day_of_week) continue;
                $slot = $date->copy()->setTimeFromTimeString($schedule->start_time);
                if ($slot->greaterThan($cursor) && (! $candidate || $slot->lessThan($candidate))) {
                    $candidate = $slot;
                }
            }
            if ($candidate) break;
        }

        abort_if(! $candidate, 422, 'تعذر تحديد الموعد التالي للمجموعة.');
        return $candidate->utc();
    }

    private function duration(string $start, string $end): int
    {
        $startAt = Carbon::createFromFormat('H:i', $start);
        $endAt = Carbon::createFromFormat('H:i', $end);
        if ($endAt->lessThanOrEqualTo($startAt)) return 0;
        return (int) $startAt->diffInMinutes($endAt);
    }
}
