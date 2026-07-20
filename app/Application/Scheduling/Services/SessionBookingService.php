<?php

declare(strict_types=1);

namespace App\Application\Scheduling\Services;

use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class SessionBookingService
{
    public function bookGroup(User $student, int $groupId, ?string $notes = null): SessionBooking
    {
        return DB::transaction(function () use ($student, $groupId, $notes): SessionBooking {
            $group = TeachingGroup::with('assignment')->lockForUpdate()->findOrFail($groupId);

            if (! $group->is_active || ! $group->assignment->is_active) {
                throw new LogicException('هذه المجموعة غير متاحة للحجز حاليًا.');
            }

            if (SessionBooking::where('student_id', $student->id)
                ->where('teaching_group_id', $group->id)
                ->where('status', 'confirmed')
                ->exists()) {
                throw new LogicException('أنت محجوز بالفعل في هذه المجموعة.');
            }

            if (! $group->hasCapacity()) {
                throw new LogicException('اكتمل عدد طلاب هذه المجموعة.');
            }

            $overlap = SessionBooking::where('student_id', $student->id)
                ->where('status', 'confirmed')
                ->whereHas('group', function ($query) use ($group) {
                    $query->where('day_of_week', $group->day_of_week)
                        ->where('start_time', '<', $group->end_time)
                        ->where('end_time', '>', $group->start_time);
                })->exists();

            if ($overlap) {
                throw new LogicException('الموعد يتعارض مع مجموعة أخرى محجوزة لك.');
            }

            $group->loadMissing('schedules');
            foreach ($group->schedules as $schedule) {
                $scheduleOverlap = SessionBooking::where('student_id', $student->id)
                    ->where('status', 'confirmed')
                    ->whereHas('group.schedules', function ($query) use ($schedule) {
                        $query->where('day_of_week', $schedule->day_of_week)
                            ->where('start_time', '<', $schedule->end_time)
                            ->where('end_time', '>', $schedule->start_time);
                    })->exists();
                if ($scheduleOverlap) {
                    throw new LogicException('أحد مواعيد المجموعة يتعارض مع مجموعة أخرى محجوزة لك.');
                }
            }

            return SessionBooking::create([
                'student_id' => $student->id,
                'teaching_group_id' => $group->id,
                'status' => 'confirmed',
                'notes' => $notes,
            ]);
        });
    }

    public function bookPrivate(User $student, int $slotId, ?string $notes = null): SessionBooking
    {
        return DB::transaction(function () use ($student, $slotId, $notes): SessionBooking {
            $slot = PrivateSessionSlot::with('assignment')->lockForUpdate()->findOrFail($slotId);

            if (! $slot->isAvailable() || ! $slot->assignment->is_active) {
                throw new LogicException('هذا الموعد الخاص لم يعد متاحًا.');
            }

            $overlap = SessionBooking::where('student_id', $student->id)
                ->where('status', 'confirmed')
                ->whereHas('privateSlot', function ($query) use ($slot) {
                    $query->where('starts_at', '<', $slot->ends_at)
                        ->where('ends_at', '>', $slot->starts_at);
                })->exists();

            if ($overlap) {
                throw new LogicException('الموعد يتعارض مع حجز برايفيت آخر.');
            }

            $booking = SessionBooking::create([
                'student_id' => $student->id,
                'private_session_slot_id' => $slot->id,
                'status' => 'confirmed',
                'notes' => $notes,
            ]);

            $slot->update(['status' => 'booked']);

            return $booking;
        });
    }

    public function cancel(User $student, int $bookingId): void
    {
        DB::transaction(function () use ($student, $bookingId): void {
            $booking = SessionBooking::with('privateSlot')
                ->where('student_id', $student->id)
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->findOrFail($bookingId);

            $booking->update(['status' => 'cancelled']);
            if ($booking->privateSlot) {
                $booking->privateSlot->update(['status' => 'available']);
            }
        });
    }
}
