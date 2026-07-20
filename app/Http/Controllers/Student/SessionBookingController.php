<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Scheduling\Services\SessionBookingService;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class SessionBookingController extends Controller
{
    public function __construct(private readonly SessionBookingService $bookingService) {}

    public function index(): Response
    {
        $assignments = TeachingAssignment::with([
            'teacher:id,name,avatar', 'subject:id,name,name_en', 'gradeLevel:id,key,name,name_en',
            'groups' => fn ($query) => $query->where('is_active', true)->withCount('activeBookings')->orderBy('day_of_week')->orderBy('start_time'),
            'privateSlots' => fn ($query) => $query->where('status', 'available')->where('starts_at', '>=', now())->orderBy('starts_at'),
        ])->where('is_active', true)->get();

        $bookings = SessionBooking::with(['group.assignment.subject', 'group.assignment.gradeLevel', 'privateSlot.assignment.subject', 'privateSlot.assignment.gradeLevel'])
            ->where('student_id', Auth::id())->where('status', 'confirmed')->latest('booked_at')->get();

        return Inertia::render('Student/SessionBooking', ['assignments' => $assignments, 'bookings' => $bookings]);
    }

    public function bookGroup(Request $request, int $id): RedirectResponse
    {
        try {
            $this->bookingService->bookGroup(Auth::user(), $id, $request->input('notes'));
            return back()->with('success', 'تم حجز مكانك في المجموعة بنجاح.');
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function bookPrivate(Request $request, int $id): RedirectResponse
    {
        try {
            $this->bookingService->bookPrivate(Auth::user(), $id, $request->input('notes'));
            return back()->with('success', 'تم حجز جلسة البرايفيت بنجاح.');
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(int $id): RedirectResponse
    {
        $this->bookingService->cancel(Auth::user(), $id);
        return back()->with('success', 'تم إلغاء الحجز وإتاحة الموعد مرة أخرى.');
    }
}
