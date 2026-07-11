<?php

declare(strict_types=1);

namespace App\Http\Controllers\Course;

use App\Domain\Course\Models\LiveSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveSessionRoomController extends Controller
{
    public function show(int $id): Response
    {
        $session = LiveSession::with('course:id,title', 'teacher:id,name')->findOrFail($id);
        $user = auth()->user();

        // Check if teacher or enrolled student
        $isTeacher = $session->teacher_id === $user->id;

        if (!$isTeacher) {
            $isEnrolled = \App\Domain\Enrollment\Models\Enrollment::where('course_id', $session->course_id)
                ->where('user_id', $user->id)
                ->exists();
            abort_unless($isEnrolled, 403, 'غير مصرح.');
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
