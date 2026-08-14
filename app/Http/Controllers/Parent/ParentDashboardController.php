<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parent;

use App\Application\Scheduling\Services\SessionBookingService;
use App\Application\Subscription\Services\SubscriptionService;
use App\Application\User\Services\ParentStudentLinkService;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\PrivateLessonRequest;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\PurchaseRequest;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ParentDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $parent = Auth::user();

        $links = ParentStudentLink::where('parent_user_id', $parent->id)
            ->whereNotNull('verified_at')
            ->with(['student:id,name,email,grade_level'])
            ->get();

        $selectedStudentId = $request->input('student_id')
            ? (int) $request->input('student_id')
            : ($links->first()?->student_user_id ?? null);

        $studentData = null;

        // Only expose a student this parent is actually linked to.
        if ($selectedStudentId && $links->contains('student_user_id', $selectedStudentId)) {
            $student = User::findOrFail($selectedStudentId);
            $subscriptions = Subscription::where('student_id', $selectedStudentId)
                ->with([
                    'assignment.subject:id,name,icon',
                    'assignment.gradeLevel:id,key,name',
                    'assignment.teacher:id,name,avatar',
                    'group:id,name,capacity,teaching_assignment_id',
                    'group.schedules',
                ])
                ->latest('period_end')
                ->get();

            $groupIds = $subscriptions->pluck('teaching_group_id')->filter();
            $privateSlotIds = SessionBooking::where('student_id', $selectedStudentId)
                ->where('status', 'confirmed')
                ->whereNotNull('private_session_slot_id')
                ->pluck('private_session_slot_id');

            $attendanceSessions = LiveSession::with([
                'teacher:id,name',
                'teachingGroup:id,name,teaching_assignment_id',
                'teachingGroup.assignment.subject:id,name',
                'privateSessionSlot.assignment.subject:id,name',
                'attendees' => fn ($query) => $query->where('user_id', $selectedStudentId),
            ])
                ->where('status', LiveSession::STATUS_ENDED)
                ->where(function ($query) use ($groupIds, $privateSlotIds): void {
                    $query->whereIn('teaching_group_id', $groupIds)
                        ->orWhereIn('private_session_slot_id', $privateSlotIds);
                })
                ->latest('scheduled_at')
                ->limit(100)
                ->get();

            $attendance = $attendanceSessions->map(function (LiveSession $session): array {
                $visits = $session->attendees->sortBy('joined_at')->values();
                $minutes = (int) round($session->attendees->sum(
                    fn (LiveSessionAttendee $attendee) => $attendee->durationSeconds(),
                ) / 60);
                $firstVisit = $visits->first();
                $lastVisit = $visits->last();

                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                    'teacher' => $session->teacher?->name,
                    'subject' => $session->teachingGroup?->assignment?->subject?->name
                        ?? $session->privateSessionSlot?->assignment?->subject?->name,
                    'joined_at' => $firstVisit?->joined_at?->toIso8601String(),
                    'left_at' => $lastVisit?->left_at?->toIso8601String(),
                    'minutes' => $minutes,
                    'attended' => $minutes > 0,
                ];
            })->values();

            $submissions = WorksheetSubmission::where('student_id', $selectedStudentId)
                ->with([
                    'worksheet:id,curriculum_unit_id,title,type,max_score',
                    'worksheet.unit.assignment.subject:id,name',
                ])
                ->latest('submitted_at')
                ->limit(100)
                ->get()
                ->map(fn (WorksheetSubmission $submission): array => [
                    'id' => $submission->id,
                    'title' => $submission->worksheet?->title,
                    'type' => $submission->worksheet?->type,
                    'subject' => $submission->worksheet?->unit?->assignment?->subject?->name,
                    'score' => $submission->score,
                    'max_score' => $submission->worksheet?->max_score,
                    'teacher_feedback' => $submission->teacher_feedback,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                    'graded_at' => $submission->graded_at?->toIso8601String(),
                ])->values();

            $quizAttempts = QuizAttempt::where('user_id', $selectedStudentId)
                ->with(['quiz:id,title,passing_score,curriculum_unit_id', 'quiz.unit.assignment.subject:id,name'])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (QuizAttempt $attempt): array => [
                    'id' => $attempt->id,
                    'title' => $attempt->quiz?->title,
                    'subject' => $attempt->quiz?->unit?->assignment?->subject?->name,
                    'score' => $attempt->score,
                    'earned_points' => $attempt->earned_points,
                    'total_points' => $attempt->total_points,
                    'passing_score' => $attempt->quiz?->passing_score,
                    'passed' => $attempt->passed,
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                ])->values();

            $eligibleGroups = TeachingGroup::with([
                'assignment.subject:id,name,icon',
                'assignment.teacher:id,name,avatar,is_active',
                'assignment.gradeLevel:id,key,name',
                'schedules',
            ])
                ->withCount('activeBookings')
                ->where('is_active', true)
                ->whereHas('assignment', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('teacher', fn ($teacherQuery) => $teacherQuery->where('is_active', true))
                    ->whereHas('gradeLevel', fn ($gradeQuery) => $gradeQuery->where('key', $student->grade_level)))
                ->latest()
                ->get()
                ->filter(fn (TeachingGroup $group) => $group->active_bookings_count < $group->capacity
                    || $subscriptions->contains('teaching_group_id', $group->id))
                ->map(fn (TeachingGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'monthly_price' => $group->monthly_price,
                    'currency' => $group->currency,
                    'capacity' => $group->capacity,
                    'seats_left' => max(0, $group->capacity - $group->active_bookings_count),
                    'teacher' => $group->assignment?->teacher?->only(['id', 'name', 'avatar']),
                    'subject' => $group->assignment?->subject?->only(['id', 'name', 'icon']),
                    'grade' => $group->assignment?->gradeLevel?->only(['key', 'name']),
                    'schedules' => $group->schedules->map(fn ($schedule) => [
                        'day' => (int) $schedule->day_of_week,
                        'start' => substr((string) $schedule->start_time, 0, 5),
                        'end' => substr((string) $schedule->end_time, 0, 5),
                    ])->values(),
                    'already_subscribed' => $subscriptions->contains('teaching_group_id', $group->id),
                ])->values();

            $freeIntroSlots = PrivateSessionSlot::with([
                'assignment.subject:id,name',
                'assignment.teacher:id,name,avatar,is_active',
                'assignment.gradeLevel:id,key,name',
            ])
                ->where('is_free_intro', true)
                ->where('status', 'available')
                ->where('starts_at', '>=', now())
                ->whereHas('assignment', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('teacher', fn ($teacherQuery) => $teacherQuery->where('is_active', true))
                    ->whereHas('gradeLevel', fn ($gradeQuery) => $gradeQuery->where('key', $student->grade_level)))
                ->orderBy('starts_at')
                ->limit(50)
                ->get()
                ->map(fn (PrivateSessionSlot $slot): array => [
                    'id' => $slot->id,
                    'starts_at' => $slot->starts_at?->toIso8601String(),
                    'ends_at' => $slot->ends_at?->toIso8601String(),
                    'teacher' => $slot->assignment?->teacher?->only(['id', 'name', 'avatar']),
                    'subject' => $slot->assignment?->subject?->name,
                ])->values();

            $pendingPrivateAssignmentIds = PrivateLessonRequest::query()
                ->where('student_id', $student->id)
                ->where('status', 'pending')
                ->pluck('teaching_assignment_id')
                ->mapWithKeys(fn ($assignmentId): array => [(int) $assignmentId => true]);

            $privateAssignments = TeachingAssignment::with([
                'subject:id,name',
                'teacher:id,name,avatar,is_active',
                'gradeLevel:id,key,name',
            ])
                ->where('is_active', true)
                ->where('accepts_private', true)
                ->where('private_monthly_price', '>', 0)
                ->whereHas('teacher', fn ($query) => $query->where('is_active', true))
                ->whereHas('gradeLevel', fn ($query) => $query->where('key', $student->grade_level))
                ->get()
                ->map(fn (TeachingAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'subject' => $assignment->subject?->name,
                    'teacher' => $assignment->teacher?->only(['id', 'name', 'avatar']),
                    'monthly_price' => $assignment->private_monthly_price,
                    'has_active_subscription' => $subscriptions
                        ->where('teaching_assignment_id', $assignment->id)
                        ->where('type', Subscription::TYPE_PRIVATE)
                        ->contains(fn (Subscription $subscription) => $subscription->isActive()),
                    'has_pending_request' => isset($pendingPrivateAssignmentIds[$assignment->id]),
                ])->values();

            $studentData = [
                'student' => $student->only(['id', 'name', 'email', 'grade_level']),
                'subscriptions' => $subscriptions->map(fn (Subscription $s) => [
                    'id' => $s->id,
                    'assignment_id' => $s->teaching_assignment_id,
                    'type' => $s->type,
                    'status' => $s->status,
                    'label' => $s->label(),
                    'monthly_price' => $s->monthly_price,
                    'currency' => $s->currency,
                    'period_end' => $s->period_end?->toDateString(),
                    'days_remaining' => $s->daysRemaining(),
                    'subject' => $s->assignment?->subject?->only(['id', 'name', 'icon']),
                    'teacher' => $s->assignment?->teacher?->only(['id', 'name', 'avatar']),
                    'group' => $s->group?->only(['id', 'name']),
                    'schedules' => $s->group?->schedules?->map(fn ($schedule) => [
                        'day' => (int) $schedule->day_of_week,
                        'start' => substr((string) $schedule->start_time, 0, 5),
                        'end' => substr((string) $schedule->end_time, 0, 5),
                    ])->values(),
                ])->values(),
                'payments' => Payment::where('user_id', $selectedStudentId)
                    ->with('subscription.assignment.subject:id,name')
                    ->latest()
                    ->limit(100)
                    ->get(),
                'quizAttempts' => $quizAttempts,
                'attendance' => $attendance,
                'attendanceSummary' => [
                    'total' => $attendance->count(),
                    'present' => $attendance->where('attended', true)->count(),
                    'rate' => $attendance->count()
                        ? (int) round($attendance->where('attended', true)->count() / $attendance->count() * 100)
                        : 0,
                ],
                'submissions' => $submissions,
                'eligibleGroups' => $eligibleGroups,
                'freeIntroSlots' => $freeIntroSlots,
                'privateAssignments' => $privateAssignments,
            ];
        }

        $pendingRequests = PurchaseRequest::whereIn('student_user_id', $links->pluck('student_user_id'))
            ->where('status', PurchaseRequest::STATUS_PENDING)
            ->with([
                'student:id,name,email',
                'group:id,name,monthly_price,currency,teaching_assignment_id',
                'group.assignment.subject:id,name',
                'group.assignment.teacher:id,name',
            ])
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('Parent/Dashboard', [
            'links' => $links,
            'selectedStudent' => $studentData,
            'pendingRequests' => $pendingRequests,
        ]);
    }

    public function unlinkStudent(int $linkId): RedirectResponse
    {
        ParentStudentLink::where('parent_user_id', Auth::id())->findOrFail($linkId)->delete();

        return back()->with('success', 'تم إلغاء ربط الحساب بنجاح.');
    }

    public function linkStudent(Request $request, ParentStudentLinkService $parentStudentLinks): RedirectResponse
    {
        $validated = $request->validate([
            'student_phone' => ['required', 'string', 'max:20'],
            'relationship' => ['required', 'string', 'in:father,mother,guardian'],
        ]);

        /** @var User $parent */
        $parent = Auth::user();
        $parentStudentLinks->linkExistingStudent(
            $parent,
            $validated['student_phone'],
            $validated['relationship'],
        );

        return back()->with('success', 'تم ربط حساب الطالب بحسابك بنجاح.');
    }

    /**
     * A parent paying for their child: open the subscription in the child's
     * name, then hand off to checkout.
     */
    public function payForRequest(int $requestId, SubscriptionService $subscriptions): RedirectResponse
    {
        $purchaseRequest = PurchaseRequest::with('group')->findOrFail($requestId);

        $isLinked = ParentStudentLink::where('parent_user_id', Auth::id())
            ->where('student_user_id', $purchaseRequest->student_user_id)
            ->whereNotNull('verified_at')
            ->exists();

        abort_unless($isLinked, 403, 'غير مصرح لك بالدفع لهذا الطلب.');
        abort_unless($purchaseRequest->group, 404, 'المجموعة المطلوبة لم تعد متاحة.');

        try {
            $subscription = $subscriptions->openForGroup(
                User::findOrFail($purchaseRequest->student_user_id),
                $purchaseRequest->group,
            );
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('checkout.show', $subscription->id);
    }

    public function subscribeToGroup(Request $request, int $groupId, SubscriptionService $subscriptions): RedirectResponse
    {
        $studentId = (int) $request->validate(['student_id' => ['required', 'integer', 'exists:users,id']])['student_id'];
        $this->assertLinkedStudent($studentId);
        $student = User::findOrFail($studentId);
        $group = TeachingGroup::with(['assignment.gradeLevel'])->findOrFail($groupId);
        abort_unless(
            $group->assignment?->gradeLevel?->key === $student->grade_level,
            422,
            'هذه المجموعة ليست مخصصة للصف الدراسي للطالب.',
        );

        try {
            $subscription = $subscriptions->openForGroup(
                $student,
                $group,
            );
        } catch (\LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('checkout.show', $subscription->id);
    }

    public function bookFreeIntro(Request $request, int $slotId, SessionBookingService $bookings): RedirectResponse
    {
        $studentId = (int) $request->validate(['student_id' => ['required', 'integer', 'exists:users,id']])['student_id'];
        $this->assertLinkedStudent($studentId);

        try {
            $bookings->bookFreeIntro(User::findOrFail($studentId), $slotId);
        } catch (\LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم حجز الحصة المجانية للطالب بدون رسوم.');
    }

    private function assertLinkedStudent(int $studentId): void
    {
        abort_unless(
            ParentStudentLink::where('parent_user_id', Auth::id())
                ->where('student_user_id', $studentId)
                ->whereNotNull('verified_at')
                ->exists(),
            403,
            'هذا الطالب غير مرتبط بحسابك.',
        );
    }
}
