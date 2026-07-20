<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Course\Models\GradeLevel;
use App\Domain\Course\Models\Subject;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TeachingScheduleController extends Controller
{
    public function index(): Response
    {
        $assignments = TeachingAssignment::with([
            'subject:id,name,name_en', 'gradeLevel:id,key,name,name_en',
            'groups' => fn ($query) => $query->withCount('activeBookings')->orderBy('day_of_week')->orderBy('start_time'),
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
        ]);

        if (TeachingAssignment::where('teacher_id', Auth::id())->where($data)->exists()) {
            return back()->with('error', 'هذا الربط موجود بالفعل.');
        }

        TeachingAssignment::create([...$data, 'teacher_id' => Auth::id(), 'is_active' => true]);

        return back()->with('success', 'تم ربط المدرس بالمادة والمرحلة الدراسية.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teaching_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $assignment = $this->ownedAssignment((int) $data['teaching_assignment_id']);
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

        TeachingGroup::create([
            ...$data,
            'teaching_assignment_id' => $assignment->id,
            'duration_minutes' => $duration,
            'timezone' => $data['timezone'] ?? 'Asia/Qatar',
            'is_active' => true,
        ]);

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

    private function duration(string $start, string $end): int
    {
        $startAt = Carbon::createFromFormat('H:i', $start);
        $endAt = Carbon::createFromFormat('H:i', $end);
        if ($endAt->lessThanOrEqualTo($startAt)) return 0;
        return (int) $startAt->diffInMinutes($endAt);
    }
}
