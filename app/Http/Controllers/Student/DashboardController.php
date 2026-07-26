<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Learning\Models\LiveSession;
use App\Domain\Payment\Models\Payment;
use App\Domain\Subscription\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Renewal reminders start this many days before a subscription lapses. */
    private const EXPIRY_WARNING_DAYS = 7;

    public function index(): Response
    {
        $user = Auth::user();

        $subscriptions = Subscription::with([
            'assignment.subject:id,name,icon',
            'assignment.teacher:id,name,avatar',
            'group:id,name',
        ])
            ->where('student_id', $user->id)
            ->get();

        $active = $subscriptions->filter(fn (Subscription $s) => $s->isActive());

        // Only sessions the student actually holds a confirmed seat in.
        $upcomingSessions = LiveSession::with([
            'teacher:id,name',
            'teachingGroup:id,name,teaching_assignment_id',
            'teachingGroup.assignment.subject:id,name',
        ])
            ->where(function ($query) use ($user) {
                $query->whereHas('teachingGroup.activeBookings', fn ($b) => $b->where('student_id', $user->id))
                    ->orWhereHas('privateSessionSlot.booking', fn ($b) => $b->where('student_id', $user->id)->where('status', 'confirmed'));
            })
            ->whereIn('status', [LiveSession::STATUS_SCHEDULED, LiveSession::STATUS_LIVE])
            ->orderBy('scheduled_at')
            ->take(10)
            ->get();

        $pendingPayments = Payment::with('subscription.assignment.subject:id,name')
            ->where('user_id', $user->id)
            ->where('status', Payment::STATUS_PENDING_VERIFICATION)
            ->get();

        return Inertia::render('Student/Dashboard', [
            'activeSubscriptions' => $active->map(fn (Subscription $s) => [
                'id'             => $s->id,
                'type'           => $s->type,
                'subject'        => $s->assignment?->subject?->only(['id', 'name', 'icon']),
                'teacher'        => $s->assignment?->teacher?->only(['id', 'name', 'avatar']),
                'group'          => $s->group?->only(['id', 'name']),
                'period_end'     => $s->period_end?->toDateString(),
                'days_remaining' => $s->daysRemaining(),
            ])->values(),

            'expiringSoon' => $active
                ->filter(fn (Subscription $s) => $s->daysRemaining() <= self::EXPIRY_WARNING_DAYS)
                ->map(fn (Subscription $s) => [
                    'id'             => $s->id,
                    'label'          => $s->label(),
                    'days_remaining' => $s->daysRemaining(),
                ])->values(),

            'upcomingSessions' => $upcomingSessions,
            'pendingPayments'  => $pendingPayments,

            'stats' => [
                'activeSubscriptions' => $active->count(),
                'teachers'            => $active->pluck('teaching_assignment_id')->unique()->count(),
                'subjects'            => $active->map(fn (Subscription $s) => $s->assignment?->subject_id)->filter()->unique()->count(),
                'upcomingSessions'    => $upcomingSessions->count(),
            ],
        ]);
    }
}
