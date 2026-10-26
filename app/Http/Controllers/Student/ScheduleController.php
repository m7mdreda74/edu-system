<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Learning\Services\MissedLiveSessionService;
use App\Application\Learning\Services\StudentAssignmentService;
use App\Application\Quiz\Services\StudentAssessmentService;
use App\Domain\Learning\Models\LiveSession;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(
        StudentAssignmentService $assignmentService,
        StudentAssessmentService $assessmentService,
    ): Response {
        app(MissedLiveSessionService::class)->cancelOverdueSessions();

        $student = Auth::user();

        $sessions = LiveSession::query()
            ->forStudent((int) $student->id)
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
                'item_type' => 'session',
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

        $homework = $assignmentService->forStudent($student)['items']
            ->filter(fn ($item) => ! empty($item['due_date']))
            ->map(fn ($item) => [
                'id' => $item['id'],
                'item_type' => 'homework',
                'title' => $item['title'],
                'subject' => $item['subject']['name'] ?? '',
                'teacher' => $item['teacher']['name'] ?? '',
                'due_date' => $item['due_date'],
                'max_score' => $item['max_score'],
                'status' => $item['status'],
                'is_late' => $item['is_late'],
                'lesson_id' => $item['lesson_id'],
            ])
            ->values();

        $assessments = $assessmentService->forStudent($student)['items']
            ->map(fn ($item) => [
                'id' => $item['id'],
                'item_type' => 'assessment',
                'mode' => $item['mode'],
                'title' => $item['title'],
                'subject' => $item['subject']['name'] ?? '',
                'teacher' => $item['teacher']['name'] ?? '',
                'available_from' => $item['available_from'] ?? null,
                'available_until' => $item['available_until'] ?? null,
                'time_limit_minutes' => $item['time_limit_minutes'] ?? null,
                'total_points' => $item['total_points'] ?? 100,
                'status' => $item['status'],
                'can_start' => $item['can_start'] ?? false,
                'lesson_id' => $item['lesson_id'] ?? null,
            ])
            ->values();

        return Inertia::render('Student/Schedule', [
            'sessions' => $sessions,
            'homework' => $homework,
            'assessments' => $assessments,
        ]);
    }
}
