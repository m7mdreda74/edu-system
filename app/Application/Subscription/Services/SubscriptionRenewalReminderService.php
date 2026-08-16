<?php

declare(strict_types=1);

namespace App\Application\Subscription\Services;

use App\Domain\Communication\Notifications\SubscriptionRenewalReminderNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Subscription\Models\Subscription;
use App\Application\User\Services\ParentStudentLinkService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionRenewalReminderService
{
    public const LESSONS_PER_MONTH = 8;

    public const REMINDER_AFTER_LESSONS = 7;

    public function __construct(
        private readonly ParentStudentLinkService $parentStudentLinks,
    ) {}

    /**
     * Notify active subscribers after the seventh completed class in their
     * current eight-class billing period.
     */
    public function sendDueReminders(): int
    {
        $sent = 0;

        Subscription::query()
            ->with([
                'student:id,name',
                'assignment.subject:id,name',
                'assignment.teacher:id,name',
                'group.schedules',
            ])
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNull('renewal_reminder_sent_at')
            ->whereDate('period_end', '>=', now()->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$sent): void {
                foreach ($subscriptions as $subscription) {
                    $completedLessons = $this->completedLessonCount($subscription);
                    $lastLessonAt = $this->lastCompletedLessonAt($subscription);

                    if (
                        $completedLessons >= self::REMINDER_AFTER_LESSONS
                        && $lastLessonAt
                        && $this->notify($subscription, $lastLessonAt, $completedLessons)
                    ) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Run the same check immediately after a teacher ends a live session.
     * The scheduled cron remains a safe fallback for sessions ended outside
     * the normal teacher room flow.
     */
    public function sendForEndedSession(LiveSession $session): int
    {
        if ($session->status !== LiveSession::STATUS_ENDED) {
            return 0;
        }

        $session->loadMissing(['privateSessionSlot']);

        if (! $session->teaching_group_id && ! $session->privateSessionSlot) {
            return 0;
        }

        $subscriptions = Subscription::query()
            ->with([
                'student:id,name',
                'assignment.subject:id,name',
                'assignment.teacher:id,name',
                'group.schedules',
            ])
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNull('renewal_reminder_sent_at')
            ->whereDate('period_end', '>=', now()->toDateString())
            ->where(function ($query) use ($session): void {
                if ($session->teaching_group_id) {
                    $query->where(function ($group) use ($session): void {
                        $group
                            ->where('type', Subscription::TYPE_GROUP)
                            ->where('teaching_group_id', $session->teaching_group_id);
                    });
                }

                if ($session->privateSessionSlot) {
                    $query->orWhere(function ($private) use ($session): void {
                        $private
                            ->where('type', Subscription::TYPE_PRIVATE)
                            ->where('teaching_assignment_id', $session->privateSessionSlot->teaching_assignment_id);
                    });
                }
            })
            ->get();

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            $completedLessons = $this->completedLessonCount($subscription);
            $lastLessonAt = $this->lastCompletedLessonAt($subscription);

            if (
                $completedLessons >= self::REMINDER_AFTER_LESSONS
                && $lastLessonAt
                && $this->notify($subscription, $lastLessonAt, $completedLessons)
            ) {
                $sent++;
            }
        }

        return $sent;
    }

    public function completedLessonCount(Subscription $subscription, ?Carbon $now = null): int
    {
        return $this->completedLessonsQuery($subscription, $now)->count();
    }

    public function lastCompletedLessonAt(Subscription $subscription, ?Carbon $now = null): ?Carbon
    {
        $lesson = $this->completedLessonsQuery($subscription, $now)
            ->latest('ended_at')
            ->first(['ended_at', 'scheduled_at']);

        if (! $lesson) {
            return null;
        }

        return ($lesson->ended_at ?? $lesson->scheduled_at)?->copy();
    }

    private function completedLessonsQuery(Subscription $subscription, ?Carbon $now = null)
    {
        $now ??= now();
        $periodStart = $subscription->period_start->copy()->startOfDay();
        $periodEnd = $subscription->period_end->copy()->endOfDay();

        return LiveSession::query()
            ->where('status', LiveSession::STATUS_ENDED)
            ->whereNotNull('ended_at')
            ->whereBetween('scheduled_at', [$periodStart, $periodEnd])
            ->where('ended_at', '<=', $now)
            ->when(
                $subscription->type === Subscription::TYPE_GROUP,
                fn ($query) => $query->where('teaching_group_id', $subscription->teaching_group_id),
                fn ($query) => $query
                    ->whereNull('teaching_group_id')
                    ->whereHas('privateSessionSlot', fn ($slot) => $slot
                        ->where('teaching_assignment_id', $subscription->teaching_assignment_id)
                        ->whereHas('booking', fn ($booking) => $booking
                            ->where('student_id', $subscription->student_id)
                            ->where('status', 'confirmed'))),
            );
    }

    private function notify(Subscription $subscription, Carbon $lastLessonAt, int $completedLessons): bool
    {
        return DB::transaction(function () use ($subscription, $lastLessonAt, $completedLessons): bool {
            $locked = Subscription::query()
                ->with([
                    'student:id,name',
                    'assignment.subject:id,name',
                    'assignment.teacher:id,name',
                    'group.schedules',
                ])
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            if (
                $locked->status !== Subscription::STATUS_ACTIVE
                || $locked->renewal_reminder_sent_at !== null
                || $this->hasRenewal($locked)
            ) {
                return false;
            }

            $notification = new SubscriptionRenewalReminderNotification(
                $locked,
                $lastLessonAt,
                $completedLessons,
                self::LESSONS_PER_MONTH,
            );

            if ($locked->student) {
                $locked->student->notify($notification);
                $this->parentStudentLinks->notifyLinkedParents($locked->student, $notification);
            }

            $locked->forceFill(['renewal_reminder_sent_at' => now()])->save();

            return true;
        });
    }

    private function hasRenewal(Subscription $subscription): bool
    {
        return Subscription::query()
            ->whereKeyNot($subscription->id)
            ->where('student_id', $subscription->student_id)
            ->where('type', $subscription->type)
            ->where('teaching_assignment_id', $subscription->teaching_assignment_id)
            ->when(
                $subscription->teaching_group_id,
                fn ($query, $groupId) => $query->where('teaching_group_id', $groupId),
                fn ($query) => $query->whereNull('teaching_group_id'),
            )
            ->whereDate('period_start', '>=', $subscription->period_end->toDateString())
            ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_ACTIVE])
            ->exists();
    }
}
