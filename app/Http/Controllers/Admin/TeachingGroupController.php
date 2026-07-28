<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administrative ownership of teaching assignments, groups, private
 * availability, capacity and pricing. Teachers only consume this configuration
 * while managing their academic content and live classes.
 */
class TeachingGroupController extends Controller
{
    private const DAY_NAMES = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'term']);

        $groups = TeachingGroup::with([
            'assignment.teacher:id,name,avatar',
            'assignment.subject:id,name',
            'assignment.gradeLevel:id,key,name,track',
            'schedules',
            'term:id,year_label,name',
        ])
            ->withCount('activeBookings')
            ->when(! empty($filters['search']), fn ($query) => $query->where(function ($nested) use ($filters): void {
                $nested->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('assignment.teacher', fn ($teacher) => $teacher->where('name', 'like', '%'.$filters['search'].'%'));
            }))
            ->when(($filters['status'] ?? '') === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(($filters['status'] ?? '') === 'full', fn ($query) => $query
                ->whereRaw('capacity <= (select count(*) from session_bookings where session_bookings.teaching_group_id = teaching_groups.id and status = ?)', ['confirmed']))
            ->when(! empty($filters['term']), fn ($query) => $query->where('academic_term_id', $filters['term']))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $materialCounts = GroupMaterial::countsByAssignment(
            collect($groups->items())->pluck('teaching_assignment_id'),
        );

        $groups->through(fn (TeachingGroup $group) => [
            'id' => $group->id,
            'assignment_id' => $group->teaching_assignment_id,
            'academic_term_id' => $group->academic_term_id,
            'name' => $group->name,
            'teacher' => $group->assignment?->teacher?->only(['id', 'name', 'avatar']),
            'subject' => $group->assignment?->subject?->name,
            'grade' => $group->assignment?->gradeLevel?->name,
            'term' => trim(($group->term?->name ?? '').' '.($group->term?->year_label ?? '')),
            'monthly_price' => $group->monthly_price,
            'capacity' => $group->capacity,
            'students_count' => $group->active_bookings_count,
            'materials_count' => $materialCounts[$group->teaching_assignment_id] ?? 0,
            'is_active' => $group->is_active,
            'is_full' => $group->active_bookings_count >= $group->capacity,
            'schedule' => $group->schedules
                ->map(fn ($schedule) => (self::DAY_NAMES[(int) $schedule->day_of_week] ?? '').' '.substr((string) $schedule->start_time, 0, 5))
                ->implode('، '),
        ]);

        $assignments = TeachingAssignment::with([
            'teacher:id,name,subject_id',
            'subject:id,name',
            'gradeLevel:id,key,name',
        ])
            ->withCount('groups')
            ->latest()
            ->get()
            ->map(fn (TeachingAssignment $assignment) => [
                'id' => $assignment->id,
                'teacher' => $assignment->teacher?->only(['id', 'name']),
                'subject' => $assignment->subject?->only(['id', 'name']),
                'grade' => $assignment->gradeLevel?->only(['id', 'key', 'name']),
                'private_monthly_price' => $assignment->private_monthly_price,
                'accepts_private' => $assignment->accepts_private,
                'is_active' => $assignment->is_active,
                'groups_count' => $assignment->groups_count,
            ])
            ->values();

        $privateSlots = PrivateSessionSlot::with([
            'assignment.teacher:id,name',
            'assignment.subject:id,name',
            'assignment.gradeLevel:id,name',
        ])
            ->where('starts_at', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')
            ->limit(30)
            ->get()
            ->map(fn (PrivateSessionSlot $slot) => [
                'id' => $slot->id,
                'teacher' => $slot->assignment?->teacher?->name,
                'subject' => $slot->assignment?->subject?->name,
                'grade' => $slot->assignment?->gradeLevel?->name,
                'starts_at' => $slot->starts_at?->toIso8601String(),
                'ends_at' => $slot->ends_at?->toIso8601String(),
                'status' => $slot->status,
            ]);

        return Inertia::render('Admin/TeachingGroups', [
            'groups' => $groups,
            'assignments' => $assignments,
            'privateSlots' => $privateSlots,
            'teachers' => User::role('teacher')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'subject_id']),
            'subjects' => Subject::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'gradeLevels' => GradeLevel::where('is_active', true)->orderBy('id')->get(['id', 'key', 'name']),
            'filters' => $filters,
            'terms' => AcademicTerm::orderByDesc('starts_on')->get(['id', 'year_label', 'name']),
            'stats' => [
                'total' => TeachingGroup::count(),
                'active' => TeachingGroup::where('is_active', true)->count(),
                'inactive' => TeachingGroup::where('is_active', false)->count(),
                'empty' => TeachingGroup::doesntHave('activeBookings')->count(),
            ],
        ]);
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'accepts_private' => ['nullable', 'boolean'],
            'private_monthly_price_qar' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $teacher = User::findOrFail($data['teacher_id']);
        abort_unless($teacher->hasRole('teacher'), 422, 'المستخدم المختار ليس معلماً.');

        $hasOtherSubject = TeachingAssignment::where('teacher_id', $teacher->id)
            ->where('subject_id', '!=', $data['subject_id'])
            ->exists();

        if ($hasOtherSubject) {
            throw ValidationException::withMessages([
                'subject_id' => 'المعلم مسند بالفعل إلى مادة أخرى. أوقف الإسناد القديم أو اختر مادته الحالية.',
            ]);
        }

        if (TeachingAssignment::where([
            'teacher_id' => $teacher->id,
            'subject_id' => $data['subject_id'],
            'grade_level_id' => $data['grade_level_id'],
        ])->exists()) {
            throw ValidationException::withMessages([
                'grade_level_id' => 'هذا الإسناد موجود بالفعل.',
            ]);
        }

        $acceptsPrivate = (bool) ($data['accepts_private'] ?? false);
        $price = $this->priceInMinorUnits($data['private_monthly_price_qar'] ?? 0);
        $this->assertPrivatePrice($acceptsPrivate, $price);

        DB::transaction(function () use ($data, $teacher, $acceptsPrivate, $price): void {
            $teacher->update(['subject_id' => $data['subject_id']]);

            TeachingAssignment::create([
                'teacher_id' => $teacher->id,
                'subject_id' => $data['subject_id'],
                'grade_level_id' => $data['grade_level_id'],
                'private_monthly_price' => $price,
                'accepts_private' => $acceptsPrivate,
                'currency' => 'QAR',
                'is_active' => true,
            ]);
        });

        return back()->with('success', 'تم إسناد المادة والصف للمعلم بواسطة الإدارة.');
    }

    public function updateAssignment(Request $request, int $id): RedirectResponse
    {
        $assignment = TeachingAssignment::findOrFail($id);
        $data = $request->validate([
            'accepts_private' => ['required', 'boolean'],
            'private_monthly_price_qar' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $price = $this->priceInMinorUnits($data['private_monthly_price_qar'] ?? 0);
        $this->assertPrivatePrice((bool) $data['accepts_private'], $price);

        $assignment->update([
            'accepts_private' => (bool) $data['accepts_private'],
            'private_monthly_price' => $price,
            'is_active' => (bool) $data['is_active'],
        ]);

        return back()->with('success', 'تم تحديث سعر البرايفيت وحالة الإسناد.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teaching_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'monthly_price_qar' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $assignment = TeachingAssignment::with('teacher')->where('is_active', true)->findOrFail($data['teaching_assignment_id']);
        $duration = $this->duration($data['start_time'], $data['end_time']);

        if ($duration < 15 || $duration > 480) {
            throw ValidationException::withMessages([
                'end_time' => 'مدة المجموعة يجب أن تكون بين 15 دقيقة و8 ساعات.',
            ]);
        }

        $conflict = TeachingGroup::whereHas('assignment', fn ($query) => $query->where('teacher_id', $assignment->teacher_id))
            ->where('is_active', true)
            ->where(function ($query) use ($data): void {
                $query->whereHas('schedules', fn ($schedule) => $schedule
                    ->where('day_of_week', $data['day_of_week'])
                    ->where('start_time', '<', $data['end_time'])
                    ->where('end_time', '>', $data['start_time']))
                    ->orWhere(function ($legacy) use ($data): void {
                        $legacy->doesntHave('schedules')
                            ->where('day_of_week', $data['day_of_week'])
                            ->where('start_time', '<', $data['end_time'])
                            ->where('end_time', '>', $data['start_time']);
                    });
            })
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'start_time' => 'الموعد يتعارض مع مجموعة أخرى للمعلم.',
            ]);
        }

        DB::transaction(function () use ($data, $assignment, $duration): void {
            $group = TeachingGroup::create([
                'teaching_assignment_id' => $assignment->id,
                'academic_term_id' => $data['academic_term_id'] ?? null,
                'name' => $data['name'],
                'capacity' => $data['capacity'],
                'monthly_price' => $this->priceInMinorUnits($data['monthly_price_qar']),
                'currency' => 'QAR',
                'day_of_week' => $data['day_of_week'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'duration_minutes' => $duration,
                'timezone' => $data['timezone'] ?? 'Asia/Qatar',
                'is_active' => true,
            ]);

            $group->schedules()->create([
                'day_of_week' => $data['day_of_week'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'duration_minutes' => $duration,
            ]);
        });

        return back()->with('success', 'تم إنشاء المجموعة وتسعيرها بواسطة الإدارة.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $group = TeachingGroup::withCount('activeBookings')->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'monthly_price_qar' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ((int) $data['capacity'] < $group->active_bookings_count) {
            throw ValidationException::withMessages([
                'capacity' => 'السعة لا يمكن أن تقل عن عدد الطلاب المحجوزين حاليًا.',
            ]);
        }

        $group->update([
            'name' => $data['name'],
            'capacity' => $data['capacity'],
            'monthly_price' => $this->priceInMinorUnits($data['monthly_price_qar']),
            'academic_term_id' => $data['academic_term_id'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ]);

        return back()->with('success', 'تم تحديث بيانات المجموعة وسعرها.');
    }

    public function storePrivateSlot(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teaching_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $assignment = TeachingAssignment::where('is_active', true)->findOrFail($data['teaching_assignment_id']);
        abort_unless($assignment->offersPrivate(), 422, 'فعّل البرايفيت وحدد سعره أولاً.');

        $starts = Carbon::parse($data['starts_at']);
        $ends = Carbon::parse($data['ends_at']);
        $duration = $starts->diffInMinutes($ends);

        if ($duration < 15 || $duration > 480) {
            throw ValidationException::withMessages([
                'ends_at' => 'مدة البرايفيت يجب أن تكون بين 15 دقيقة و8 ساعات.',
            ]);
        }

        $overlap = PrivateSessionSlot::where('teaching_assignment_id', $assignment->id)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => 'هذا الموعد يتعارض مع موعد برايفيت آخر.',
            ]);
        }

        PrivateSessionSlot::create([
            'teaching_assignment_id' => $assignment->id,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'timezone' => $data['timezone'] ?? 'Asia/Qatar',
            'status' => 'available',
        ]);

        return back()->with('success', 'تمت إتاحة موعد البرايفيت بواسطة الإدارة.');
    }

    public function destroyPrivateSlot(int $id): RedirectResponse
    {
        $slot = PrivateSessionSlot::with('booking')->findOrFail($id);

        if ($slot->booking?->status === 'confirmed') {
            return back()->with('error', 'لا يمكن إلغاء موعد برايفيت محجوز.');
        }

        $slot->update(['status' => 'cancelled']);

        return back()->with('success', 'تم إلغاء موعد البرايفيت.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $group = TeachingGroup::findOrFail($id);

        if ($group->activeBookings()->exists()) {
            return back()->with('error', 'لا يمكن حذف مجموعة عليها حجوزات مؤكدة. أوقفها بدلاً من ذلك.');
        }

        $group->delete();

        return back()->with('success', 'تم حذف المجموعة.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $group = TeachingGroup::findOrFail($id);
        $group->update(['is_active' => ! $group->is_active]);

        return back()->with('success', $group->is_active
            ? 'تم تفعيل المجموعة وأصبحت متاحة للحجز.'
            : 'تم إيقاف المجموعة — لن تظهر للطلاب الجدد.');
    }

    public function show(int $id): Response
    {
        $group = TeachingGroup::with([
            'assignment.teacher:id,name,email,avatar',
            'assignment.subject:id,name',
            'assignment.gradeLevel:id,name',
            'schedules',
        ])->findOrFail($id);

        $subscriptions = Subscription::with('student:id,name,email,avatar')
            ->where('teaching_group_id', $group->id)
            ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
            ->get()
            ->map(fn (Subscription $subscription) => [
                'id' => $subscription->id,
                'student' => $subscription->student?->only(['id', 'name', 'email', 'avatar']),
                'status' => $subscription->status,
                'monthly_price' => $subscription->monthly_price,
                'period_end' => $subscription->period_end?->toDateString(),
                'days_remaining' => $subscription->daysRemaining(),
            ]);

        return Inertia::render('Admin/TeachingGroupShow', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'teacher' => $group->assignment?->teacher?->only(['id', 'name', 'email', 'avatar']),
                'subject' => $group->assignment?->subject?->name,
                'grade' => $group->assignment?->gradeLevel?->name,
                'monthly_price' => $group->monthly_price,
                'capacity' => $group->capacity,
                'seats_left' => $group->seatsLeft(),
                'is_active' => $group->is_active,
                'schedule' => $group->schedules->map(fn ($schedule) => [
                    'day' => self::DAY_NAMES[(int) $schedule->day_of_week] ?? '',
                    'start' => substr((string) $schedule->start_time, 0, 5),
                    'end' => substr((string) $schedule->end_time, 0, 5),
                ])->values(),
                'materials' => $group->materials()->get()->map->only(['id', 'title', 'order', 'is_free_preview'])->values(),
            ],
            'subscriptions' => $subscriptions,
        ]);
    }

    private function priceInMinorUnits(int|float|string $price): int
    {
        return (int) round((float) $price * 100);
    }

    private function assertPrivatePrice(bool $acceptsPrivate, int $price): void
    {
        if ($acceptsPrivate && $price <= 0) {
            throw ValidationException::withMessages([
                'private_monthly_price_qar' => 'حدد سعرًا أكبر من صفر عند تفعيل الحصص الخاصة.',
            ]);
        }
    }

    private function duration(string $start, string $end): int
    {
        return (int) Carbon::createFromFormat('H:i', $start)
            ->diffInMinutes(Carbon::createFromFormat('H:i', $end));
    }
}

