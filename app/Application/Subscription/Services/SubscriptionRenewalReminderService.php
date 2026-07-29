<?php

declare(strict_types=1);

namespace App\Application\Subscription\Services;

use App\Domain\Communication\Notifications\SubscriptionRenewalReminderNotification;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionRenewalReminderService
{
    /**
     * Notify active subscribers when only one class occurrence remains inside
     * their current billing period.
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
                    $lastLessonAt = $this->soleRemainingLessonAt($subscription);

                    if ($lastLessonAt && $this->notify($subscription, $lastLessonAt)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    public function soleRemainingLessonAt(Subscription $subscription, ?Carbon $now = null): ?Carbon
    {
        $now ??= now();

        if ($subscription->type === Subscription::TYPE_GROUP && $subscription->group) {
            return $this->soleRemainingGroupLessonAt($subscription, $now);
        }

        if ($subscription->type === Subscription::TYPE_PRIVATE) {
            return $this->soleRemainingPrivateLessonAt($subscription, $now);
        }

        return null;
    }

    private function soleRemainingGroupLessonAt(Subscription $subscription, Carbon $now): ?Carbon
    {
        $group = $subscription->group;
        $timezone = $group->timezone ?: config('app.timezone');
        $localNow = $now->copy()->setTimezone($timezone);
        $periodEnd = Carbon::parse($subscription->period_end->toDateString(), $timezone)->endOfDay();

        if ($localNow->greaterThan($periodEnd)) {
            return null;
        }

        $schedules = $group->schedules
            ->map(fn ($schedule): array => [
                'day' => (int) $schedule->day_of_week,
                'time' => (string) $schedule->start_time,
            ])
            ->values()
            ->all();

        if ($schedules === []) {
            $schedules[] = [
                'day' => (int) $group->day_of_week,
                'time' => (string) $group->start_time,
            ];
        }

        $remaining = [];
        $date = $localNow->copy()->startOfDay();
        $guardEnd = $localNow->copy()->addDays(62)->endOfDay();

        while ($date->lessThanOrEqualTo($periodEnd) && $date->lessThanOrEqualTo($guardEnd)) {
            foreach ($schedules as $schedule) {
                if ($date->dayOfWeek !== $schedule['day']) {
                    continue;
                }

                $occurrence = $date->copy()->setTimeFromTimeString($schedule['time']);

                if ($occurrence->greaterThan($localNow) && $occurrence->lessThanOrEqualTo($periodEnd)) {
                    $remaining[] = $occurrence;

                    if (count($remaining) > 1) {
                        return null;
                    }
                }
            }

            $date->addDay();
        }

        return isset($remaining[0]) ? $remaining[0]->copy() : null;
    }

    private function soleRemainingPrivateLessonAt(Subscription $subscription, Carbon $now): ?Carbon
    {
        $periodEnd = $subscription->period_end->copy()->endOfDay();

        $remaining = PrivateSessionSlot::query()
            ->where('teaching_assignment_id', $subscription->teaching_assignment_id)
            ->where('status', 'booked')
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $periodEnd)
            ->whereHas('booking', fn ($query) => $query
                ->where('student_id', $subscription->student_id)
                ->where('status', 'confirmed'))
            ->orderBy('starts_at')
            ->limit(2)
            ->get(['starts_at', 'timezone']);

        if ($remaining->count() !== 1) {
            return null;
        }

        $slot = $remaining->first();

        return $slot->starts_at->copy()->setTimezone($slot->timezone ?: config('app.timezone'));
    }

    private function notify(Subscription $subscription, Carbon $lastLessonAt): bool
    {
        return DB::transaction(function () use ($subscription, $lastLessonAt): bool {
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

            $locked->student?->notify(
                new SubscriptionRenewalReminderNotification($locked, $lastLessonAt),
            );

            $parents = ParentStudentLink::query()
                ->with('parent:id,name')
                ->where('student_user_id', $locked->student_id)
                ->whereNotNull('verified_at')
                ->get()
                ->pluck('parent')
                ->filter();

            foreach ($parents as $parent) {
                $parent->notify(new SubscriptionRenewalReminderNotification($locked, $lastLessonAt));
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
