<?php

declare(strict_types=1);

namespace App\Http\Controllers\Live;

use App\Application\Learning\Services\JitsiMeetingTokenService;
use App\Application\User\Services\ParentStudentLinkService;
use App\Domain\Communication\Notifications\StudentLiveSessionActivityNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LiveSessionRoomController extends Controller
{
    public function __construct(
        private readonly JitsiMeetingTokenService $jitsiTokens,
        private readonly ParentStudentLinkService $parentStudentLinks,
    ) {}

    public function show(int $id): Response
    {
        $session = LiveSession::with([
            'teacher:id,name',
            'teachingGroup',
            'teachingGroup.assignment.subject:id,name',
            'privateSessionSlot',
        ])->findOrFail($id);

        /** @var User $user */
        $user = Auth::user();
        $isTeacher = $this->authorizeRoom($session, $user);
        $roomName = $this->roomName($session);
        $domain = $this->jitsiDomain();
        $host = strtolower(rtrim(explode(':', $domain, 2)[0], '.'));
        $whiteboard = $this->whiteboardConfig($domain);

        abort_unless(
            ! (bool) config('services.jitsi.require_auth', false)
                || ($host !== 'meet.jit.si' && $this->jitsiTokens->isConfigured()),
            503,
            'Jitsi must use a private token-authenticated deployment.',
        );

        return Inertia::render('Live/LiveSessionRoom', [
            'session' => $session,
            'startedAt' => $session->started_at?->toIso8601String(),
            'roomName' => $roomName,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'isTeacher' => $isTeacher,
            ],
            'jitsi' => [
                'domain' => $domain,
                'jwt' => $this->jitsiTokens->issue($user, $isTeacher, $roomName, $domain),
                'whiteboard' => $whiteboard,
                'recording' => [
                    'enabled' => (bool) config('services.jitsi.recording.enabled', true),
                    'mode' => 'file',
                ],
            ],
        ]);
    }

    public function joinAttendance(int $id): JsonResponse
    {
        $session = LiveSession::with([
            'teachingGroup',
            'privateSessionSlot',
        ])->findOrFail($id);

        /** @var User $user */
        $user = Auth::user();
        $this->authorizeStudentAttendance($session, $user, true);

        $attendee = LiveSessionAttendee::query()
            ->where('live_session_id', $session->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->latest('joined_at')
            ->first();

        if (! $attendee) {
            $attendee = LiveSessionAttendee::create([
                'live_session_id' => $session->id,
                'user_id' => $user->id,
                'joined_at' => now(),
            ]);

            $this->parentStudentLinks->notifyLinkedParents(
                $user,
                new StudentLiveSessionActivityNotification(
                    $session,
                    $user,
                    StudentLiveSessionActivityNotification::ACTIVITY_JOINED,
                ),
            );
        }

        return response()->json([
            'joined' => true,
            'attendee_id' => $attendee->id,
        ]);
    }

    public function leaveAttendance(int $id): JsonResponse
    {
        $session = LiveSession::with([
            'teachingGroup',
            'privateSessionSlot',
        ])->findOrFail($id);

        /** @var User $user */
        $user = Auth::user();
        $this->authorizeStudentAttendance($session, $user, false);

        $attendee = LiveSessionAttendee::query()
            ->where('live_session_id', $session->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->latest('joined_at')
            ->first();

        if ($attendee) {
            $attendee->update(['left_at' => now()]);

            $this->parentStudentLinks->notifyLinkedParents(
                $user,
                new StudentLiveSessionActivityNotification(
                    $session,
                    $user,
                    StudentLiveSessionActivityNotification::ACTIVITY_LEFT,
                ),
            );
        }

        return response()->json(['left' => true]);
    }

    /**
     * Jitsi needs a collaboration backend in addition to the feature flag.
     * The public meet.jit.si deployment exposes that backend on its own host;
     * self-hosted deployments must provide their public proxy URL explicitly.
     *
     * @return array{enabled: bool, collabServerBaseUrl: string|null, userLimit: int}
     */
    private function whiteboardConfig(string $domain): array
    {
        $collabServerBaseUrl = trim((string) config('services.jitsi.whiteboard.collab_server_base_url'));
        $host = strtolower(rtrim(explode(':', $domain, 2)[0], '.'));

        if ($collabServerBaseUrl === '' && $host === 'meet.jit.si') {
            $collabServerBaseUrl = 'https://meet.jit.si';
        }

        return [
            'enabled' => (bool) config('services.jitsi.whiteboard.enabled', true)
                && $collabServerBaseUrl !== '',
            'collabServerBaseUrl' => $collabServerBaseUrl !== ''
                ? rtrim($collabServerBaseUrl, '/')
                : null,
            'userLimit' => max(1, (int) config('services.jitsi.whiteboard.user_limit', 30)),
        ];
    }

    /**
     * @return bool True when the current user is the session teacher.
     */
    private function authorizeRoom(LiveSession $session, User $user): bool
    {
        abort_if($session->status === LiveSession::STATUS_CANCELLED, 403, 'تم إلغاء هذه الحصة بعد اعتذار المدرس.');

        $isTeacher = $session->teacher_id === $user->id;

        if ($isTeacher) {
            abort_unless(
                in_array($session->status, [LiveSession::STATUS_SCHEDULED, LiveSession::STATUS_LIVE], true),
                403,
                'لا يمكن فتح حصة انتهت.',
            );

            return true;
        }

        abort_if(! $session->isLive(), 403, 'الحصة لم تبدأ بعد.');
        abort_unless($this->studentMayJoin($session, $user), 403, 'غير مصرح لك بدخول هذه الحصة.');

        return false;
    }

    private function authorizeStudentAttendance(LiveSession $session, User $user, bool $joining): void
    {
        abort_if($session->teacher_id === $user->id || ! $user->hasRole('student'), 403, 'غير مصرح لك بتسجيل حضور هذه الحصة.');
        abort_if($session->status === LiveSession::STATUS_CANCELLED, 403, 'تم إلغاء هذه الحصة.');
        abort_unless(
            $joining
                ? $session->isLive()
                : in_array($session->status, [LiveSession::STATUS_LIVE, LiveSession::STATUS_ENDED], true),
            403,
            $joining ? 'الحصة لم تبدأ بعد.' : 'لا يمكن تسجيل الخروج من هذه الحصة الآن.',
        );
        abort_unless($this->studentMayJoin($session, $user), 403, 'غير مصرح لك بهذه الحصة.');
    }

    /**
     * Entry requires a confirmed booking for the session's group or private
     * slot — and, for groups, a live subscription behind it.
     */
    private function studentMayJoin(LiveSession $session, User $user): bool
    {
        if ($session->teaching_group_id) {
            $hasSeat = $session->teachingGroup?->activeBookings()
                ->where('student_id', $user->id)
                ->exists() ?? false;

            return $hasSeat && $user->hasActiveSubscriptionTo($session->teachingGroup);
        }

        if ($session->private_session_slot_id) {
            return $session->privateSessionSlot?->booking()
                ->where('student_id', $user->id)
                ->where('status', 'confirmed')
                ->exists() ?? false;
        }

        return false;
    }

    private function roomName(LiveSession $session): string
    {
        $fingerprint = substr(hash_hmac('sha256', (string) $session->id, (string) config('app.key')), 0, 24);

        return "altafawwuq-{$session->id}-{$fingerprint}";
    }

    private function jitsiDomain(): string
    {
        $domain = trim((string) config('services.jitsi.domain', 'meet.jit.si'));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? '';
        $domain = trim($domain, '/');

        abort_unless(
            preg_match('/^[a-z0-9.-]+(?::[0-9]+)?$/i', $domain) === 1,
            500,
            'إعداد Jitsi غير صالح.',
        );

        return $domain;
    }
}
