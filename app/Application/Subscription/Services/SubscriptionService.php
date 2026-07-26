<?php

declare(strict_types=1);

namespace App\Application\Subscription\Services;

use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * SubscriptionService — Application Layer
 *
 * Owns the rules around a student's monthly access: opening a subscription,
 * activating it once paid, renewing it, and closing it. Knows nothing about
 * HTTP — controllers and the payment service call in.
 */
class SubscriptionService
{
    /**
     * Open a pending subscription to a weekly group.
     *
     * Pending means "seat held, awaiting payment". The seat is only really
     * reserved once payment lands, so capacity is re-checked at activation.
     *
     * @throws LogicException
     */
    public function openForGroup(User $student, TeachingGroup $group, ?Carbon $startsOn = null): Subscription
    {
        return DB::transaction(function () use ($student, $group, $startsOn): Subscription {
            $group->loadMissing('assignment');

            if (! $group->is_active || ! $group->assignment->is_active) {
                throw new LogicException('هذه المجموعة غير متاحة للاشتراك حاليًا.');
            }

            if ($student->hasActiveSubscriptionTo($group)) {
                throw new LogicException('أنت مشترك بالفعل في هذه المجموعة.');
            }

            if (! $group->hasCapacity()) {
                throw new LogicException('اكتمل عدد طلاب هذه المجموعة.');
            }

            [$periodStart, $periodEnd] = $this->resolvePeriod($startsOn);

            $existing = Subscription::where('student_id', $student->id)
                ->where('teaching_group_id', $group->id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->first();

            if ($existing) {
                return $existing; // Re-entering checkout for the same month.
            }

            return Subscription::create([
                'student_id'             => $student->id,
                'type'                   => Subscription::TYPE_GROUP,
                'teaching_assignment_id' => $group->teaching_assignment_id,
                'teaching_group_id'      => $group->id,
                'monthly_price'          => $group->monthly_price,
                'currency'               => $group->currency ?? 'QAR',
                'period_start'           => $periodStart,
                'period_end'             => $periodEnd,
                'status'                 => Subscription::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Open a pending subscription to a teacher's private tuition.
     *
     * Private slots are scheduled individually but sold monthly, so the
     * subscription hangs off the assignment, not off one slot.
     *
     * @throws LogicException
     */
    public function openForPrivate(User $student, TeachingAssignment $assignment, ?Carbon $startsOn = null): Subscription
    {
        return DB::transaction(function () use ($student, $assignment, $startsOn): Subscription {
            if (! $assignment->is_active || ! $assignment->offersPrivate()) {
                throw new LogicException('الحصص الخاصة غير متاحة مع هذا المعلم حاليًا.');
            }

            $alreadyActive = Subscription::active()
                ->where('student_id', $student->id)
                ->where('teaching_assignment_id', $assignment->id)
                ->where('type', Subscription::TYPE_PRIVATE)
                ->exists();

            if ($alreadyActive) {
                throw new LogicException('لديك اشتراك حصص خاصة نشط بالفعل مع هذا المعلم.');
            }

            [$periodStart, $periodEnd] = $this->resolvePeriod($startsOn);

            return Subscription::create([
                'student_id'             => $student->id,
                'type'                   => Subscription::TYPE_PRIVATE,
                'teaching_assignment_id' => $assignment->id,
                'teaching_group_id'      => null,
                'monthly_price'          => $assignment->private_monthly_price,
                'currency'               => $assignment->currency ?? 'QAR',
                'period_start'           => $periodStart,
                'period_end'             => $periodEnd,
                'status'                 => Subscription::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Mark a subscription paid and reserve the student's seat.
     * Idempotent — safe to call from a webhook that fires twice.
     */
    public function activate(Subscription $subscription): Subscription
    {
        if ($subscription->status === Subscription::STATUS_ACTIVE) {
            return $subscription;
        }

        return DB::transaction(function () use ($subscription): Subscription {
            $subscription->update([
                'status'       => Subscription::STATUS_ACTIVE,
                'cancelled_at' => null,
            ]);

            if ($subscription->teaching_group_id) {
                $this->reserveSeat($subscription);
            }

            return $subscription->fresh();
        });
    }

    /**
     * Extend an active subscription by one month from where it currently ends,
     * so renewing early does not cost the student any days.
     */
    public function renew(Subscription $subscription): Subscription
    {
        $currentEnd = $subscription->period_end ?? now();
        $newStart   = $currentEnd->isFuture() ? $currentEnd->copy() : now()->startOfDay();

        return Subscription::create([
            'student_id'             => $subscription->student_id,
            'type'                   => $subscription->type,
            'teaching_assignment_id' => $subscription->teaching_assignment_id,
            'teaching_group_id'      => $subscription->teaching_group_id,
            'monthly_price'          => $this->currentPriceFor($subscription),
            'currency'               => $subscription->currency,
            'period_start'           => $newStart,
            'period_end'             => $newStart->copy()->addMonth(),
            'status'                 => Subscription::STATUS_PENDING,
            'auto_renew'             => $subscription->auto_renew,
        ]);
    }

    /** End a subscription early and release the group seat. */
    public function cancel(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $subscription->update([
                'status'       => Subscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            if ($subscription->teaching_group_id) {
                \App\Domain\Scheduling\Models\SessionBooking::where('student_id', $subscription->student_id)
                    ->where('teaching_group_id', $subscription->teaching_group_id)
                    ->where('status', 'confirmed')
                    ->update(['status' => 'cancelled']);
            }
        });
    }

    /**
     * Flip lapsed subscriptions to expired and free their seats.
     * Called from the scheduler.
     */
    public function expireLapsed(): int
    {
        $lapsed = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereDate('period_end', '<', now()->toDateString())
            ->get();

        foreach ($lapsed as $subscription) {
            $subscription->update(['status' => Subscription::STATUS_EXPIRED]);

            if ($subscription->teaching_group_id) {
                \App\Domain\Scheduling\Models\SessionBooking::where('student_id', $subscription->student_id)
                    ->where('teaching_group_id', $subscription->teaching_group_id)
                    ->where('status', 'confirmed')
                    ->update(['status' => 'cancelled']);
            }
        }

        return $lapsed->count();
    }

    /** Slots a private student can still claim this month. */
    public function availablePrivateSlots(TeachingAssignment $assignment)
    {
        return PrivateSessionSlot::where('teaching_assignment_id', $assignment->id)
            ->where('status', 'available')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->get();
    }

    // ─── Internals ────────────────────────────────────────────────

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolvePeriod(?Carbon $startsOn): array
    {
        $start = ($startsOn ?? now())->copy()->startOfDay();

        return [$start, $start->copy()->addMonth()];
    }

    /** Prices can change between months; always bill the current one. */
    private function currentPriceFor(Subscription $subscription): int
    {
        if ($subscription->teaching_group_id) {
            return (int) (TeachingGroup::where('id', $subscription->teaching_group_id)->value('monthly_price')
                ?? $subscription->monthly_price);
        }

        return (int) (TeachingAssignment::where('id', $subscription->teaching_assignment_id)->value('private_monthly_price')
            ?? $subscription->monthly_price);
    }

    /** A confirmed booking is what holds the seat in the group. */
    private function reserveSeat(Subscription $subscription): void
    {
        \App\Domain\Scheduling\Models\SessionBooking::updateOrCreate(
            [
                'student_id'        => $subscription->student_id,
                'teaching_group_id' => $subscription->teaching_group_id,
            ],
            [
                'status'    => 'confirmed',
                'booked_at' => now(),
            ],
        );
    }
}
