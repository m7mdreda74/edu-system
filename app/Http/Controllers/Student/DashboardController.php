<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Communication\Services\GroupAnnouncementService;
use App\Application\Learning\Services\StudentAchievementService;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Payment\Models\Payment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Renewal reminders start this many days before a subscription lapses. */
    private const EXPIRY_WARNING_DAYS = 7;

    public function index(GroupAnnouncementService $announcements, StudentAchievementService $achievements): Response
    {
        $user = Auth::user();

        $subscriptions = Subscription::with([
            'assignment.subject:id,name,icon',
            'assignment.teacher:id,name,avatar',
            'group:id,name',
        ])
            ->where('student_id', $user->id)
            ->get();

        $active = $subscriptions->filter(fn (Subscription $s) => $s->isActive());

        // Only sessions the student actually holds a confirmed seat in.
        $upcomingSessions = LiveSession::query()
            ->forStudent($user->id)
            ->upcoming()
            ->with([
                'teacher:id,name,avatar',
                'teachingGroup:id,name,teaching_assignment_id,timezone',
                'teachingGroup.assignment.subject:id,name',
                'privateSessionSlot:id,teaching_assignment_id,timezone',
                'privateSessionSlot.assignment.subject:id,name',
            ])
            ->orderBy('scheduled_at')
            ->take(10)
            ->get();

        $pendingPayments = Payment::with('subscription.assignment.subject:id,name')
            ->where('user_id', $user->id)
            ->where('status', Payment::STATUS_PENDING_VERIFICATION)
            ->get();

        $pendingParentLinks = ParentStudentLink::query()
            ->where('student_user_id', $user->id)
            ->whereNull('verified_at')
            ->where('status', ParentStudentLink::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('parent:id,name')
            ->latest()
            ->get()
            ->map(fn (ParentStudentLink $link): array => [
                'id' => $link->id,
                'relationship' => $link->relationship,
                'parent' => $link->parent?->only(['id', 'name']),
                'expires_at' => $link->expires_at?->toIso8601String(),
            ])->values();

        $continueLearning = $this->continueLearning($user, $active);

        return Inertia::render('Student/Dashboard', [
            'activeSubscriptions' => $active->map(fn (Subscription $s) => [
                'id' => $s->id,
                'type' => $s->type,
                'subject' => $s->assignment?->subject?->only(['id', 'name', 'icon']),
                'teacher' => $s->assignment?->teacher?->only(['id', 'name', 'avatar']),
                'group' => $s->group?->only(['id', 'name']),
                'period_end' => $s->period_end?->toDateString(),
                'days_remaining' => $s->daysRemaining(),
            ])->values(),

            'expiringSoon' => $active
                ->filter(fn (Subscription $s) => $s->daysRemaining() <= self::EXPIRY_WARNING_DAYS)
                ->map(fn (Subscription $s) => [
                    'id' => $s->id,
                    'label' => $s->label(),
                    'days_remaining' => $s->daysRemaining(),
                ])->values(),

            'upcomingSessions' => $upcomingSessions->map(fn (LiveSession $session): array => [
                'id' => $session->id,
                'title' => $session->title,
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'status' => $session->status,
                'type' => $session->isPrivate() ? 'private' : 'group',
                'subject' => $session->teachingGroup?->assignment?->subject?->name
                    ?? $session->privateSessionSlot?->assignment?->subject?->name,
                'teacher' => $session->teacher?->only(['id', 'name', 'avatar']),
                'group' => $session->teachingGroup?->only(['id', 'name']),
            ]),
            'pendingPayments' => $pendingPayments,
            'pendingParentLinks' => $pendingParentLinks,
            'continueLearning' => $continueLearning,
            'announcements' => $announcements->forStudent($user),
            'gamification' => $achievements->forStudent($user),

            'stats' => [
                'activeSubscriptions' => $active->count(),
                'teachers' => $active->pluck('teaching_assignment_id')->unique()->count(),
                'subjects' => $active->map(fn (Subscription $s) => $s->assignment?->subject_id)->filter()->unique()->count(),
                'upcomingSessions' => $upcomingSessions->count(),
            ],
        ]);
    }

    /**
     * Return the first incomplete, published lesson in an active group.
     * Playback position is included only when an actual progress row exists;
     * the dashboard links to the lesson-level study room rather than claiming
     * to support precise cross-device video resume.
     *
     * @param  Collection<int, Subscription>  $activeSubscriptions
     * @return array<string, mixed>|null
     */
    private function continueLearning($student, Collection $activeSubscriptions): ?array
    {
        $groupSubscriptions = $activeSubscriptions
            ->filter(fn (Subscription $subscription) => $subscription->group !== null)
            ->unique('teaching_assignment_id')
            ->values();

        if ($groupSubscriptions->isEmpty()) {
            return null;
        }

        $term = AcademicTerm::currentOrNext();

        $assignmentIds = $groupSubscriptions->pluck('teaching_assignment_id')->filter()->unique();
        $unitsQuery = CurriculumUnit::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where(function ($q) {
                $q->where('is_published', true)->orWhereNull('is_published');
            })
            ->with(['lessons' => fn ($query) => $query->orderBy('order')])
            ->orderBy('order');

        $units = $term
            ? (clone $unitsQuery)->where('academic_term_id', $term->id)->get()->groupBy('teaching_assignment_id')
            : collect();

        if ($units->isEmpty()) {
            $units = $unitsQuery->get()->groupBy('teaching_assignment_id');
        }

        $lessons = $units->flatten(1)->flatMap(fn (CurriculumUnit $unit) => $unit->lessons);
        $progress = LessonProgress::query()
            ->where('student_id', $student->id)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->get()
            ->keyBy('lesson_id');

        foreach ($groupSubscriptions as $subscription) {
            foreach ($units->get($subscription->teaching_assignment_id, collect()) as $unit) {
                foreach ($unit->lessons as $lesson) {
                    $seen = $progress->get($lesson->id);

                    if ($seen?->is_completed) {
                        continue;
                    }

                    return [
                        'group_id' => $subscription->group->id,
                        'group_name' => $subscription->group->name,
                        'lesson_id' => $lesson->id,
                        'lesson_title' => $lesson->title,
                        'watched_seconds' => (int) ($seen?->watched_seconds ?? 0),
                        'subject' => $subscription->assignment?->subject?->only(['id', 'name']),
                        'teacher' => $subscription->assignment?->teacher?->only(['id', 'name']),
                    ];
                }
            }
        }

        return null;
    }
}
