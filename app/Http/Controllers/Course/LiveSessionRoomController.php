<?php

declare(strict_types=1);

namespace App\Http\Controllers\Course;

use App\Domain\Course\Models\LiveSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LiveSessionRoomController extends Controller
{
    public function show(int $id): Response
    {
        $session = LiveSession::with(['course:id,title', 'teacher:id,name', 'teachingGroup', 'privateSessionSlot'])->findOrFail($id);
        /** @var \App\Domain\User\Models\User $user */
        $user = Auth::user();

        $isTeacher = $session->teacher_id === $user->id;

        // Students can only enter after the teacher starts the broadcast.
        abort_if(! $isTeacher && $session->status !== 'live', 403, 'الحصة لم تبدأ بعد.');

        if (! $isTeacher) {
            if ($session->teaching_group_id) {
                $allowed = $session->teachingGroup?->activeBookings()
                    ->where('student_id', $user->id)->exists();
            } elseif ($session->private_session_slot_id) {
                $allowed = $session->privateSessionSlot?->booking()
                    ->where('student_id', $user->id)->where('status', 'confirmed')->exists();
            } else {
                Gate::authorize('joinLive', $session->course);
                $allowed = true;
            }

            abort_unless($allowed, 403, 'غير مصرح لك بدخول هذه الحصة.');
        }

        // Room name should be unique and safe
        $roomName = "Altafawwuq_Session_{$session->id}_" . md5((string)$session->created_at);

        return Inertia::render('Course/LiveSessionRoom', [
            'session'   => $session,
            'roomName'  => $roomName,
            'user'      => [
                'name'      => $user->name,
                'email'     => $user->email,
                'avatar'    => $user->avatar,
                'isTeacher' => $isTeacher,
            ],
        ]);
    }
}
