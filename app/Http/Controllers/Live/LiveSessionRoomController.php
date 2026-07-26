<?php

declare(strict_types=1);

namespace App\Http\Controllers\Live;

use App\Domain\Learning\Models\LiveSession;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LiveSessionRoomController extends Controller
{
    public function show(int $id): Response
    {
        $session = LiveSession::with([
            'teacher:id,name',
            'teachingGroup',
            'teachingGroup.assignment.subject:id,name',
            'privateSessionSlot',
        ])->findOrFail($id);

        /** @var User $user */
        $user      = Auth::user();
        $isTeacher = $session->teacher_id === $user->id;

        // Students can only enter after the teacher starts the broadcast.
        abort_if(! $isTeacher && ! $session->isLive(), 403, 'الحصة لم تبدأ بعد.');

        if (! $isTeacher) {
            abort_unless($this->studentMayJoin($session, $user), 403, 'غير مصرح لك بدخول هذه الحصة.');
        }

        return Inertia::render('Live/LiveSessionRoom', [
            'session'  => $session,
            // Unique and unguessable, so a room id cannot be brute-forced.
            'roomName' => "Altafawwuq_Session_{$session->id}_" . md5((string) $session->created_at),
            'user'     => [
                'name'      => $user->name,
                'email'     => $user->email,
                'avatar'    => $user->avatar,
                'isTeacher' => $isTeacher,
            ],
        ]);
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

        // Unattached session — nobody but the teacher belongs in it.
        return false;
    }
}
