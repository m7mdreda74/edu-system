<?php

declare(strict_types=1);

namespace App\Http\Controllers\Live;

use App\Application\Learning\Services\JitsiMeetingTokenService;
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
    public function __construct(private readonly JitsiMeetingTokenService $jitsiTokens)
    {
    }

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

        return Inertia::render('Live/LiveSessionRoom', [
            'session' => $session,
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
                'whiteboard' => [
                    'enabled' => (bool) config('services.jitsi.whiteboard.enabled', true),
                    'collabServerBaseUrl' => filled(config('services.jitsi.whiteboard.collab_server_base_url'))
                        ? config('services.jitsi.whiteboard.collab_server_base_url')
                        : null,
                    'userLimit' => max(1, (int) config('services.jitsi.whiteboard.user_limit', 30)),
                ],
            ],
        ]);
    }

    /** Mark the real Jitsi join event, rather than merely opening the page. */
    public function join(int $id): JsonResponse
    {
        $session = LiveSession::with(['teachingGroup', 'privateSessionSlot'])->findOrFail($id);
        /** @var User $user */
        $user = Auth::user();

        if (! $this->authorizeRoom($session, $user)) {
            $this->startAttendance($session, $user);
        }

        return response()->json(['ok' => true]);
    }

    /** Close the current attendance window when Jitsi reports a departure. */
    public function leave(int $id): JsonResponse
    {
        $session = LiveSession::with(['teachingGroup', 'privateSessionSlot'])->findOrFail($id);
        /** @var User $user */
        $user = Auth::user();

        if (! $this->authorizeRoom($session, $user)) {
            LiveSessionAttendee::where('live_session_id', $session->id)
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->update(['left_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['ok' => true]);
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

    private function startAttendance(LiveSession $session, User $user): void
    {
        LiveSessionAttendee::firstOrCreate(
            [
                'live_session_id' => $session->id,
                'user_id' => $user->id,
                'left_at' => null,
            ],
            ['joined_at' => now()],
        );
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
