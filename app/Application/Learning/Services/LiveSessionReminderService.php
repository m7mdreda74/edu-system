<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Application\User\Services\ParentStudentLinkService;
use App\Domain\Communication\Notifications\LiveSessionReminderNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LiveSessionReminderService
{
    public function __construct(
        private readonly ?ParentStudentLinkService $parentStudentLinks = null,
    ) {}

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

    /**
     * Send 30-minute advance reminders to teacher, students, and parents.
     */
    public function sendThirtyMinuteReminders(?Carbon $now = null): int
    {
        $now ??= now();
        $sent = 0;

        LiveSession::query()
            ->with([
                'teacher:id,name,email',
                'teachingGroup.assignment.subject:id,name',
                'privateSessionSlot.assignment.subject:id,name',
            ])
            ->where('status', LiveSession::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $now->copy()->addMinutes(30))
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$sent, $now): void {
                foreach ($sessions as $session) {
                    // 1. Notify Teacher
                    if ($teacher = $session->teacher) {
                        $teacherCacheKey = 'live_session_30m:teacher:'.$session->id.':'.$teacher->id;
                        $teacherKey = 'live_session_30m:teacher:'.$session->id.':'.$teacher->id;
                        $alreadySent = ! Cache::add($teacherCacheKey, 1, 86400)
                            || $teacher->notifications()->where('data->alert_key', $teacherKey)->exists();

                        if (! $alreadySent) {
                            $teacher->notify(new LiveSessionReminderNotification(
                                $session,
                                'teacher',
                                null,
                                30,
                            ));
                            $sent++;
                        }
                    }

                    // 2. Notify Eligible Students
                    $students = User::query()
                        ->whereIn('id', $this->eligibleStudentIds($session))
                        ->get();

                    foreach ($students as $student) {
                        $studentCacheKey = 'live_session_30m:student:'.$session->id.':'.$student->id;
                        $studentKey = 'live_session_30m:student:'.$session->id.':'.$student->id;
                        $alreadySent = ! Cache::add($studentCacheKey, 1, 86400)
                            || $student->notifications()->where('data->alert_key', $studentKey)->exists();

                        if (! $alreadySent) {
                            $student->notify(new LiveSessionReminderNotification(
                                $session,
                                'student',
                                $student,
                                30,
                            ));
                            $sent++;
                        }

                        // 3. Notify Linked Parents
                        $parents = ParentStudentLink::query()
                            ->with('parent:id,name')
                            ->where('student_user_id', $student->id)
                            ->whereNotNull('verified_at')
                            ->get()
                            ->pluck('parent')
                            ->filter();

                        foreach ($parents as $parent) {
                            $parentCacheKey = 'live_session_30m:parent:'.$session->id.':'.$parent->id.':'.$student->id;
                            $parentKey = 'live_session_30m:parent:'.$session->id.':'.$parent->id.':'.$student->id;
                            $parentAlreadySent = ! Cache::add($parentCacheKey, 1, 86400)
                                || $parent->notifications()->where('data->alert_key', $parentKey)->exists();

                            if (! $parentAlreadySent) {
                                $parent->notify(new LiveSessionReminderNotification(
                                    $session,
                                    'parent',
                                    $student,
                                    30,
                                ));
                                $sent++;
                            }
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

            // AcademicAlertService may have delivered the same class alert to
            // this student already. Keep the durable reminder marker, but do
            // not create a second database notification.
            if ($student->notifications()
                ->where('data->alert_key', AcademicAlertService::upcomingClassAlertKey($session->id, $student->id))
                ->exists()) {
                return false;
            }

            $student->notify(new LiveSessionReminderNotification($session));

            return true;
        });
    }
}
