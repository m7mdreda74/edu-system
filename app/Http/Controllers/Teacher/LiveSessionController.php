<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\LiveSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Domain\Communication\Notifications\LiveSessionStartedNotification;
use App\Domain\User\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class LiveSessionController extends Controller
{
    public function index(): Response
    {
        $teacherId = auth()->id();
        
        $courses = Course::where('teacher_id', $teacherId)->get(['id', 'title']);
        $sessions = LiveSession::with('course:id,title')
            ->where('teacher_id', $teacherId)
            ->latest('scheduled_at')
            ->get();

        return Inertia::render('Teacher/LiveSessions', [
            'sessions' => $sessions,
            'courses'  => $courses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_id'    => ['required', 'exists:courses,id'],
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'room_id'      => ['nullable', 'string'], // Link to Zoom/Meet
        ]);

        $course = Course::findOrFail($validated['course_id']);
        
        abort_if($course->teacher_id !== auth()->id(), 403, 'غير مصرح.');

        $validated['teacher_id'] = auth()->id();
        $validated['status']     = 'scheduled';

        LiveSession::create($validated);

        return back()->with('success', 'تم إنشاء الحصة بنجاح.');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $session = LiveSession::findOrFail($id);
        abort_if($session->teacher_id !== auth()->id(), 403, 'غير مصرح.');

        $validated = $request->validate([
            'status'        => ['required', 'in:scheduled,live,ended'],
            'recording_url' => ['nullable', 'url'],
        ]);

        if ($validated['status'] === 'live' && $session->status !== 'live') {
            $session->started_at = now();
            
            // Notify enrolled students
            $studentIds = \App\Domain\Enrollment\Models\Enrollment::where('course_id', $session->course_id)
                ->pluck('student_id');
            $students = User::whereIn('id', $studentIds)->get();
            foreach ($students as $student) {
                $student->notify(new LiveSessionStartedNotification($session));
            }
        } elseif ($validated['status'] === 'ended' && $session->status !== 'ended') {
            $session->ended_at = now();
        }

        $session->status = $validated['status'];
        if (isset($validated['recording_url'])) {
            $session->recording_url = $validated['recording_url'];
        }

        $session->save();

        return back()->with('success', 'تم تحديث حالة الحصة وبدء البث المباشر.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $session = LiveSession::findOrFail($id);
        abort_if($session->teacher_id !== auth()->id(), 403, 'غير مصرح.');

        $session->delete();

        return back()->with('success', 'تم حذف الحصة.');
    }
}
