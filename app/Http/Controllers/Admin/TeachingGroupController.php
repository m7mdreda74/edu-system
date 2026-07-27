<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Oversight of every teaching group on the platform.
 *
 * Teachers create and run their own groups; this is the admin's view across all
 * of them — who is full, who is empty, what they charge, and the ability to
 * pull one offline without touching the teacher's account.
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
            ->when(! empty($filters['search']), fn ($q) => $q
                ->where('name', 'like', '%' . $filters['search'] . '%')
                ->orWhereHas('assignment.teacher', fn ($t) => $t->where('name', 'like', '%' . $filters['search'] . '%')))
            ->when(($filters['status'] ?? '') === 'active',   fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(($filters['status'] ?? '') === 'full',     fn ($q) => $q->whereRaw('capacity <= (select count(*) from session_bookings where session_bookings.teaching_group_id = teaching_groups.id and status = ?)', ['confirmed']))
            ->when(! empty($filters['term']), fn ($q) => $q->where('academic_term_id', $filters['term']))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Lessons belong to the assignment's units, so the count cannot ride
        // along on the group query — it is resolved for the page in one go.
        $materialCounts = GroupMaterial::countsByAssignment(
            collect($groups->items())->pluck('teaching_assignment_id'),
        );

        $groups->through(fn (TeachingGroup $group) => [
                'id'              => $group->id,
                'name'            => $group->name,
                'teacher'         => $group->assignment?->teacher?->only(['id', 'name', 'avatar']),
                'subject'         => $group->assignment?->subject?->name,
                'grade'           => $group->assignment?->gradeLevel?->name,
                'term'            => $group->term?->name . ' ' . $group->term?->year_label,
                'monthly_price'   => $group->monthly_price,
                'capacity'        => $group->capacity,
                'students_count'  => $group->active_bookings_count,
                'materials_count' => $materialCounts[$group->teaching_assignment_id] ?? 0,
                'is_active'       => $group->is_active,
                'is_full'         => $group->active_bookings_count >= $group->capacity,
                'schedule'        => $group->schedules
                    ->map(fn ($s) => (self::DAY_NAMES[(int) $s->day_of_week] ?? '') . ' ' . substr((string) $s->start_time, 0, 5))
                    ->implode('، '),
            ]);

        return Inertia::render('Admin/TeachingGroups', [
            'groups'  => $groups,
            'filters' => $filters,
            'terms'   => AcademicTerm::orderByDesc('starts_on')->get(['id', 'year_label', 'name']),
            'stats'   => [
                'total'    => TeachingGroup::count(),
                'active'   => TeachingGroup::where('is_active', true)->count(),
                'inactive' => TeachingGroup::where('is_active', false)->count(),
                'empty'    => TeachingGroup::doesntHave('activeBookings')->count(),
            ],
        ]);
    }

    public function toggle(int $id): RedirectResponse
    {
        $group = TeachingGroup::findOrFail($id);
        $group->update(['is_active' => ! $group->is_active]);

        return back()->with('success', $group->is_active
            ? 'تم تفعيل المجموعة وأصبحت متاحة للحجز.'
            : 'تم إيقاف المجموعة — لن تظهر للطلاب الجدد.');
    }

    /** Who is in this group and whether their month is still paid for. */
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
            ->map(fn (Subscription $s) => [
                'id'             => $s->id,
                'student'        => $s->student?->only(['id', 'name', 'email', 'avatar']),
                'status'         => $s->status,
                'monthly_price'  => $s->monthly_price,
                'period_end'     => $s->period_end?->toDateString(),
                'days_remaining' => $s->daysRemaining(),
            ]);

        return Inertia::render('Admin/TeachingGroupShow', [
            'group' => [
                'id'            => $group->id,
                'name'          => $group->name,
                'teacher'       => $group->assignment?->teacher?->only(['id', 'name', 'email', 'avatar']),
                'subject'       => $group->assignment?->subject?->name,
                'grade'         => $group->assignment?->gradeLevel?->name,
                'monthly_price' => $group->monthly_price,
                'capacity'      => $group->capacity,
                'seats_left'    => $group->seatsLeft(),
                'is_active'     => $group->is_active,
                'schedule'      => $group->schedules->map(fn ($s) => [
                    'day'   => self::DAY_NAMES[(int) $s->day_of_week] ?? '',
                    'start' => substr((string) $s->start_time, 0, 5),
                    'end'   => substr((string) $s->end_time, 0, 5),
                ])->values(),
                'materials' => $group->materials()->get()->map->only(['id', 'title', 'order', 'is_free_preview'])->values(),
            ],
            'subscriptions' => $subscriptions,
        ]);
    }
}
