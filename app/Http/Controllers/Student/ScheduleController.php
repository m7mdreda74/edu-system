<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Learning\Services\MissedLiveSessionService;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        app(MissedLiveSessionService::class)->cancelOverdueSessions();

        $student = Auth::user();
        $studentId = (int) $student->id;
        $bufferHours = (int) config(
            'learning.live_session_overdue_buffer_hours',
            MissedLiveSessionService::DEFAULT_BUFFER_HOURS,
        );
        $upcomingThreshold = now()->subHours($bufferHours);

        $allSessions = LiveSession::query()
            ->forStudent($studentId)
            ->with([
                'teacher:id,name,avatar',
                'teachingGroup:id,name,teaching_assignment_id,day_of_week,start_time,end_time,timezone,is_private,capacity',
                'teachingGroup.schedules:id,teaching_group_id,day_of_week,start_time,end_time,duration_minutes',
                'teachingGroup.assignment:id,subject_id,teacher_id',
                'teachingGroup.assignment.subject:id,name,icon',
                'teachingGroup.assignment.teacher:id,name,avatar',
                'privateSessionSlot:id,teaching_assignment_id,timezone',
                'privateSessionSlot.assignment:id,subject_id,teacher_id',
                'privateSessionSlot.assignment.subject:id,name,icon',
                'privateSessionSlot.assignment.teacher:id,name,avatar',
                'attendees' => fn ($query) => $query
                    ->where('user_id', $studentId)
                    ->whereNotNull('joined_at'),
                'apology:id,live_session_id,status,makeup_session_id,makeup_scheduled_at',
                'apology.makeupSession:id,title,scheduled_at,status',
            ])
            ->get();

        [$upcomingModels, $pastModels] = $allSessions->partition(
            fn (LiveSession $session): bool => $session->isLive()
                || (
                    $session->status === LiveSession::STATUS_SCHEDULED
                    && $session->scheduled_at !== null
                    && $session->scheduled_at->greaterThanOrEqualTo($upcomingThreshold)
                ),
        );

        $sessions = $upcomingModels
            ->sortBy(fn (LiveSession $session): int => $session->scheduled_at?->getTimestamp() ?? 0)
            ->values()
            ->map(fn (LiveSession $session): array => $this->presentSession($session));

        $pastSessions = $pastModels
            ->sortByDesc(fn (LiveSession $session): int => $session->scheduled_at?->getTimestamp() ?? 0)
            ->values()
            ->map(fn (LiveSession $session): array => $this->presentSession($session));

        [$subjects, $groups] = $this->collectSubjectsAndGroups($studentId, $allSessions);

        return Inertia::render('Student/Schedule', [
            'sessions' => $sessions,
            'pastSessions' => $pastSessions,
            'subjects' => $subjects,
            'groups' => $groups,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSession(LiveSession $session): array
    {
        $group = $session->teachingGroup;
        $subject = $group?->assignment?->subject
            ?? $session->privateSessionSlot?->assignment?->subject;

        return [
            'id' => $session->id,
            'item_type' => 'session',
            'title' => $session->title,
            'description' => $session->description,
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'status' => $session->status,
            'type' => $session->isPrivate() ? 'private' : 'group',
            'subject_id' => $subject?->id,
            'subject' => $subject?->name,
            'teacher' => $session->teacher?->only(['id', 'name', 'avatar']),
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
            ] : null,
            'timezone' => $group?->timezone
                ?? $session->privateSessionSlot?->timezone
                ?? config('app.timezone'),
            'attended' => $session->attendees->isNotEmpty(),
            'has_recording' => (bool) ($session->is_published_as_lesson && $session->lesson_id && $group),
            'lesson_id' => $session->is_published_as_lesson ? $session->lesson_id : null,
            'makeup_scheduled_at' => $session->apology?->makeupSession?->scheduled_at?->toIso8601String()
                ?? $session->apology?->makeup_scheduled_at?->toIso8601String(),
        ];
    }

    /**
     * Gather every subject and group the student holds an active subscription,
     * confirmed booking, or live-session history in.
     *
     * @param  \Illuminate\Support\Collection<int, LiveSession>  $allSessions
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function collectSubjectsAndGroups(int $studentId, $allSessions): array
    {
        $subjectsMap = [];
        $groupsMap = [];

        $registerSubject = function ($subject) use (&$subjectsMap): void {
            if (! $subject || ! $subject->id) {
                return;
            }

            $subjectId = (int) $subject->id;
            if (! isset($subjectsMap[$subjectId])) {
                $subjectsMap[$subjectId] = [
                    'id' => $subjectId,
                    'name' => (string) $subject->name,
                    'icon' => $subject->icon ?? null,
                ];
            }
        };

        $registerGroup = function (?TeachingGroup $group) use (&$groupsMap, $registerSubject): void {
            if (! $group || ! $group->id) {
                return;
            }

            $assignment = $group->assignment;
            $subject = $assignment?->subject;
            $registerSubject($subject);

            $groupId = (int) $group->id;
            if (isset($groupsMap[$groupId])) {
                return;
            }

            $schedules = $group->relationLoaded('schedules') && $group->schedules->isNotEmpty()
                ? $group->schedules->map(fn ($slot): array => [
                    'day' => (int) $slot->day_of_week,
                    'start' => substr((string) $slot->start_time, 0, 5),
                    'end' => substr((string) $slot->end_time, 0, 5),
                ])->values()->all()
                : ($group->day_of_week !== null ? [[
                    'day' => (int) $group->day_of_week,
                    'start' => substr((string) ($group->start_time ?? ''), 0, 5),
                    'end' => substr((string) ($group->end_time ?? ''), 0, 5),
                ]] : []);

            $groupsMap[$groupId] = [
                'id' => $groupId,
                'name' => (string) $group->name,
                'subject_id' => $subject?->id ? (int) $subject->id : null,
                'subject_name' => $subject?->name,
                'teacher_name' => $assignment?->teacher?->name,
                'is_private' => $group->isPrivate(),
                'timezone' => $group->timezone ?? config('app.timezone'),
                'schedules' => $schedules,
            ];
        };

        $activeSubscriptions = Subscription::query()
            ->where('student_id', $studentId)
            ->active()
            ->with([
                'assignment:id,subject_id,teacher_id',
                'assignment.subject:id,name,icon',
                'assignment.teacher:id,name,avatar',
                'group:id,name,teaching_assignment_id,day_of_week,start_time,end_time,timezone,is_private,capacity',
                'group.schedules:id,teaching_group_id,day_of_week,start_time,end_time,duration_minutes',
                'group.assignment:id,subject_id,teacher_id',
                'group.assignment.subject:id,name,icon',
                'group.assignment.teacher:id,name,avatar',
            ])
            ->get();

        foreach ($activeSubscriptions as $subscription) {
            $registerSubject($subscription->assignment?->subject);
            if ($subscription->group) {
                $registerGroup($subscription->group);
            }
        }

        $confirmedBookings = SessionBooking::query()
            ->where('student_id', $studentId)
            ->where('status', 'confirmed')
            ->with([
                'group:id,name,teaching_assignment_id,day_of_week,start_time,end_time,timezone,is_private,capacity',
                'group.schedules:id,teaching_group_id,day_of_week,start_time,end_time,duration_minutes',
                'group.assignment:id,subject_id,teacher_id',
                'group.assignment.subject:id,name,icon',
                'group.assignment.teacher:id,name,avatar',
                'privateSlot:id,teaching_assignment_id,timezone',
                'privateSlot.assignment:id,subject_id,teacher_id',
                'privateSlot.assignment.subject:id,name,icon',
            ])
            ->get();

        foreach ($confirmedBookings as $booking) {
            if ($booking->group) {
                $registerGroup($booking->group);
            }
            if ($booking->privateSlot?->assignment?->subject) {
                $registerSubject($booking->privateSlot->assignment->subject);
            }
        }

        foreach ($allSessions as $session) {
            if ($session->teachingGroup) {
                $registerGroup($session->teachingGroup);
            }
            if ($session->privateSessionSlot?->assignment?->subject) {
                $registerSubject($session->privateSessionSlot->assignment->subject);
            }
        }

        return [
            array_values($subjectsMap),
            array_values($groupsMap),
        ];
    }
}
