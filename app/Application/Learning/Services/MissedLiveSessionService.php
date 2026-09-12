<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Communication\Notifications\SessionApologySubmittedNotification;
use App\Domain\Communication\Notifications\SessionScheduleChangedNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionApology;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MissedLiveSessionService
{
    public const DEFAULT_BUFFER_HOURS = 2;

    public const UNEXCUSED_ABSENCE_REASON = 'لم تبدأ الحصة في موعدها المحدد ولم يقدّم المعلم اعتذاراً مسبقاً (غياب بدون عذر).';

    /**
     * Cancel any scheduled session whose start time has elapsed past the grace buffer
     * without the teacher starting it, creating a pending absence apology record.
     */
    public function cancelOverdueSessions(?int $teacherId = null, ?int $bufferHours = null, ?Carbon $now = null): int
    {
        $now ??= now();
        $bufferHours ??= (int) config('learning.live_session_overdue_buffer_hours', self::DEFAULT_BUFFER_HOURS);
        $threshold = $now->copy()->subHours($bufferHours);

        $query = LiveSession::query()
            ->where('status', LiveSession::STATUS_SCHEDULED)
            ->whereNull('started_at')
            ->where('scheduled_at', '<=', $threshold);

        if ($teacherId !== null) {
            $query->where('teacher_id', $teacherId);
        }

        $sessionIds = $query->pluck('id');
        $cancelledCount = 0;

        foreach ($sessionIds as $sessionId) {
            if ($this->processOverdueSession($sessionId, $threshold)) {
                $cancelledCount++;
            }
        }

        return $cancelledCount;
    }

    /**
     * Check and cancel a single session if it is overdue and unstarted.
     */
    public function cancelIfOverdue(LiveSession $session, ?int $bufferHours = null, ?Carbon $now = null): bool
    {
        $now ??= now();
        $bufferHours ??= (int) config('learning.live_session_overdue_buffer_hours', self::DEFAULT_BUFFER_HOURS);
        $threshold = $now->copy()->subHours($bufferHours);

        if (! $session->isOverdueUnstarted($bufferHours)) {
            return false;
        }

        return $this->processOverdueSession($session->id, $threshold);
    }

    /**
     * Calculate suggested deduction amount in cents for an uncompensated missed session.
     */
    public function suggestedDeductionAmount(LiveSessionApology $apology): int
    {
        $session = $apology->session ?? LiveSession::with(['teachingGroup', 'privateSessionSlot.assignment'])->find($apology->live_session_id);

        if ($session?->teachingGroup?->monthly_price) {
            // Typical monthly group subscription covers ~4 weekly sessions: deduct 25% of monthly group price
            return (int) round($session->teachingGroup->monthly_price / 4);
        }

        if ($session?->privateSessionSlot?->assignment?->private_monthly_price) {
            return (int) round($session->privateSessionSlot->assignment->private_monthly_price / 4);
        }

        return 10_000; // Default: 100 QAR (10,000 cents)
    }

    private function processOverdueSession(int $sessionId, Carbon $threshold): bool
    {
        $result = DB::transaction(function () use ($sessionId, $threshold): ?LiveSessionApology {
            $session = LiveSession::query()->lockForUpdate()->find($sessionId);

            if (! $session) {
                return null;
            }

            if ($session->status !== LiveSession::STATUS_SCHEDULED || $session->started_at !== null) {
                return null;
            }

            if ($session->scheduled_at === null || $session->scheduled_at->greaterThan($threshold)) {
                return null;
            }

            $session->update(['status' => LiveSession::STATUS_CANCELLED]);

            $apology = $session->apology;
            if (! $apology) {
                $apology = LiveSessionApology::create([
                    'live_session_id' => $session->id,
                    'teacher_id' => $session->teacher_id,
                    'reason' => self::UNEXCUSED_ABSENCE_REASON,
                    'status' => LiveSessionApology::STATUS_PENDING,
                ]);
            }

            return $apology;
        });

        if (! $result) {
            return false;
        }

        $result->load(['teacher:id,name', 'session']);

        // Notify Admins
        foreach (User::role('admin')->get() as $admin) {
            $admin->notify(new SessionApologySubmittedNotification($result));
        }

        // Notify Students
        if ($result->session) {
            $this->notifyStudents(
                $result->session,
                new SessionScheduleChangedNotification($result->session, false, $result->reason),
            );
        }

        return true;
    }

    private function notifyStudents(LiveSession $session, SessionScheduleChangedNotification $notification): void
    {
        $studentIds = SessionBooking::where('status', 'confirmed')
            ->when($session->teaching_group_id, fn ($query) => $query->where('teaching_group_id', $session->teaching_group_id))
            ->when($session->private_session_slot_id, fn ($query) => $query->where('private_session_slot_id', $session->private_session_slot_id))
            ->pluck('student_id');

        foreach (User::whereIn('id', $studentIds)->get() as $student) {
            $student->notify($notification);
        }
    }
}
