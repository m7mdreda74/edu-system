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
        $session = LiveSession::with('course:id,title', 'teacher:id,name')->findOrFail($id);
        /** @var \App\Domain\User\Models\User $user */
        $user = Auth::user();

        // Authorization check using Policies
        Gate::authorize('joinLive', $session->course);
        $isTeacher = $session->teacher_id === $user->id;

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
