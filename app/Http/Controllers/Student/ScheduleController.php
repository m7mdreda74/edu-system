<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Learning\Models\LiveSession;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        $sessions = LiveSession::query()
            ->forStudent((int) Auth::id())
            ->upcoming()
            ->with([
                'teacher:id,name,avatar',
                'teachingGroup:id,name,teaching_assignment_id,timezone',
                'teachingGroup.assignment.subject:id,name',
                'privateSessionSlot:id,teaching_assignment_id,timezone',
                'privateSessionSlot.assignment.subject:id,name',
            ])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (LiveSession $session): array => [
                'id' => $session->id,
                'title' => $session->title,
                'description' => $session->description,
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'status' => $session->status,
                'type' => $session->isPrivate() ? 'private' : 'group',
                'subject' => $session->teachingGroup?->assignment?->subject?->name
                    ?? $session->privateSessionSlot?->assignment?->subject?->name,
                'teacher' => $session->teacher?->only(['id', 'name', 'avatar']),
                'group' => $session->teachingGroup?->only(['id', 'name']),
                'timezone' => $session->teachingGroup?->timezone
                    ?? $session->privateSessionSlot?->timezone
                    ?? config('app.timezone'),
            ]);

        return Inertia::render('Student/Schedule', [
            'sessions' => $sessions,
        ]);
    }
}
