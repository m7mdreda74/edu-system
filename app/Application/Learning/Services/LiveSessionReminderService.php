<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Communication\Notifications\LiveSessionReminderNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LiveSessionReminderService
{
    /** Notify every confirmed student once when their class enters the next 24 hours. */
    public function sendDueReminders(?Carbon $now = null): int
    {
        $now ??= now();
        $sent = 0;

        LiveSession::query()
            ->with([
                'teachingGroup.assignment.subject:id,name',
                'privateSessionSlot.assignment.subject:id,name',
            ])
            ->where('status', LiveSession::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $now->copy()->addHours(24))
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$sent, $now): void {
                foreach ($sessions as $session) {
                    $students = User::query()
                        ->whereIn('id', $this->eligibleStudentIds($session))
                        ->get();

                    foreach ($students as $student) {
                        if ($this->notifyOnce($session, $student, $now)) {
                            $sent++;
                        }
                    }
                }
            });

        return $sent;
    }

    private function eligibleStudentIds(LiveSession $session): Builder
    {
        return SessionBooking::query()
            ->where('status', 'confirmed')
            ->when(
                $session->teaching_group_id,
                fn ($query, $groupId) => $query->where('teaching_group_id', $groupId),
            )
            ->when(
                $session->private_session_slot_id,
                fn ($query, $slotId) => $query->where('private_session_slot_id', $slotId),
            )
            ->when(
                ! $session->teaching_group_id && ! $session->private_session_slot_id,
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->select('student_id');
    }

    private function notifyOnce(LiveSession $session, User $student, Carbon $now): bool
    {
        return DB::transaction(function () use ($session, $student, $now): bool {
            $inserted = DB::table('live_session_reminders')->insertOrIgnore([
                'live_session_id' => $session->id,
                'student_id' => $student->id,
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted !== 1) {
                return false;
            }

            $student->notify(new LiveSessionReminderNotification($session));

            return true;
        });
    }
}
