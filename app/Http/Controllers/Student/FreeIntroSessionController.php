<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Scheduling\Services\SessionBookingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use LogicException;

class FreeIntroSessionController extends Controller
{
    public function __construct(private readonly SessionBookingService $bookings) {}

    public function store(int $slotId): RedirectResponse
    {
        try {
            $this->bookings->bookFreeIntro(Auth::user(), $slotId);
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم حجز الحصة التجريبية المجانية بدون أي رسوم.');
    }
}
