<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Scheduling\Services\SessionBookingService;
use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * The student's own subscriptions: what they are studying, what is expiring,
 * and the entry points to subscribe, renew or cancel.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** "حصصي" — every class this student has access to. */
    public function index(): Response
    {
        $student = Auth::user();
        $materialCounts = collect();
        $completedCounts = collect();

        $subscriptions = Subscription::with([
            'assignment.subject:id,name,icon',
            'assignment.gradeLevel:id,key,name',
            'assignment.teacher:id,name,avatar',
            'group.schedules',
        ])
            ->where('student_id', $student->id)
            // Live subscriptions first, then ones awaiting payment. CASE keeps
            // this portable — MySQL's FIELD() does not exist on SQLite.
            ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'pending' THEN 2 WHEN 'expired' THEN 3 ELSE 4 END")
            ->orderByDesc('period_end')
            ->get()
            ->tap(function ($subscriptions) use ($student, &$materialCounts, &$completedCounts): void {
                $assignmentIds = $subscriptions->pluck('teaching_assignment_id')->filter()->unique()->values();

                $materialCounts = $assignmentIds->isEmpty()
                    ? collect()
                    : GroupMaterial::query()
                        ->join('curriculum_units', 'curriculum_units.id', '=', 'group_materials.curriculum_unit_id')
                        ->whereIn('curriculum_units.teaching_assignment_id', $assignmentIds)
                        ->groupBy('curriculum_units.teaching_assignment_id')
                        ->selectRaw('curriculum_units.teaching_assignment_id as assignment_id, COUNT(*) as total')
                        ->pluck('total', 'assignment_id');

                $completedCounts = $assignmentIds->isEmpty()
                    ? collect()
                    : LessonProgress::query()
                        ->join('group_materials', 'group_materials.id', '=', 'lesson_progress.lesson_id')
                        ->join('curriculum_units', 'curriculum_units.id', '=', 'group_materials.curriculum_unit_id')
                        ->where('lesson_progress.student_id', $student->id)
                        ->where('lesson_progress.is_completed', true)
                        ->whereNull('group_materials.deleted_at')
                        ->whereIn('curriculum_units.teaching_assignment_id', $assignmentIds)
                        ->groupBy('curriculum_units.teaching_assignment_id')
                        ->selectRaw('curriculum_units.teaching_assignment_id as assignment_id, COUNT(DISTINCT lesson_progress.lesson_id) as total')
                        ->pluck('total', 'assignment_id');
            })
            ->map(function (Subscription $subscription) use (&$materialCounts, &$completedCounts) {
                $group = $subscription->group;
                $assignmentId = (int) $subscription->teaching_assignment_id;
                $totalMaterials = (int) ($materialCounts[$assignmentId] ?? 0);
                $completedMaterials = (int) ($completedCounts[$assignmentId] ?? 0);

                return [
                    'id' => $subscription->id,
                    'type' => $subscription->type,
                    'status' => $subscription->effectiveStatus(),
                    'monthly_price' => $subscription->monthly_price,
                    'currency' => $subscription->currency,
                    'period_start' => $subscription->period_start?->toDateString(),
                    'period_end' => $subscription->period_end?->toDateString(),
                    'days_remaining' => $subscription->daysRemaining(),
                    'is_active' => $subscription->isActive(),
                    'subject' => $subscription->assignment?->subject?->only(['id', 'name', 'icon']),
                    'grade' => $subscription->assignment?->gradeLevel?->only(['key', 'name']),
                    'teacher' => $subscription->assignment?->teacher?->only(['id', 'name', 'avatar']),
                    'group' => $group ? [
                        'id' => $group->id,
                        'name' => $group->name,
                        'schedules' => $group->schedules->map(fn ($s) => [
                            'day' => (int) $s->day_of_week,
                            'start' => substr((string) $s->start_time, 0, 5),
                            'end' => substr((string) $s->end_time, 0, 5),
                        ])->values(),
                    ] : null,
                    'progress' => $group && $totalMaterials > 0
                        ? (int) round(($completedMaterials / $totalMaterials) * 100)
                        : ($group ? 0 : null),
                    'certificate_ready' => $group && $totalMaterials > 0
                        && $completedMaterials >= $totalMaterials,
                ];
            })
            ->values();

        return Inertia::render('Student/MyClasses', [
            'subscriptions' => $subscriptions,
        ]);
    }

    /** Open a pending subscription to a group, then send the student to pay. */
    public function subscribeToGroup(int $groupId): RedirectResponse
    {
        try {
            $group = TeachingGroup::findOrFail($groupId);
            $subscription = $this->subscriptions->openForGroup(Auth::user(), $group);
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('checkout.show', $subscription->id);
    }

    /** Open a private subscription, then send the student to pay. */
    public function subscribeToPrivate(int $assignmentId): RedirectResponse
    {
        try {
            $assignment = TeachingAssignment::with('teacher')->findOrFail($assignmentId);
            $subscription = $this->subscriptions->openForPrivate(Auth::user(), $assignment);
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('checkout.show', $subscription->id);
    }

    public function bookPrivateSlot(int $slotId, SessionBookingService $bookings): RedirectResponse
    {
        $slot = PrivateSessionSlot::with('assignment')->findOrFail($slotId);
        $student = Auth::user();

        $hasPrivateSubscription = Subscription::active()
            ->where('student_id', $student->id)
            ->where('teaching_assignment_id', $slot->teaching_assignment_id)
            ->where('type', Subscription::TYPE_PRIVATE)
            ->exists();

        if (! $hasPrivateSubscription || $slot->is_free_intro) {
            abort(403, 'يلزم اشتراك برايفيت فعّال لحجز هذا الموعد.');
        }

        try {
            $bookings->bookPrivate($student, $slot->id);
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم حجز موعد البرايفيت وإغلاقه أمام باقي الطلاب.');
    }

    /** Buy the next month of an existing subscription. */
    public function renew(int $id): RedirectResponse
    {
        $subscription = Subscription::where('student_id', Auth::id())->findOrFail($id);
        $renewal = $this->subscriptions->renew($subscription);

        return redirect()->route('checkout.show', $renewal->id);
    }

    public function cancel(int $id): RedirectResponse
    {
        $subscription = Subscription::where('student_id', Auth::id())->findOrFail($id);

        $this->subscriptions->cancel($subscription);

        return back()->with('success', 'تم إلغاء الاشتراك.');
    }
}
