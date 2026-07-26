<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Payment\Models\Payment;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $teacher       = Auth::user();
        $assignmentIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('id');

        $stats = Cache::remember("teacher_stats:{$teacher->id}", 300, function () use ($assignmentIds) {
            $activeSubscriptions = Subscription::active()->whereIn('teaching_assignment_id', $assignmentIds);

            return [
                'total_groups'         => TeachingGroup::whereIn('teaching_assignment_id', $assignmentIds)->count(),
                'active_students'      => (clone $activeSubscriptions)->distinct('student_id')->count('student_id'),
                'private_students'     => (clone $activeSubscriptions)->where('type', Subscription::TYPE_PRIVATE)->count(),
                'total_revenue'        => Payment::whereHas('subscription', fn ($q) => $q->whereIn('teaching_assignment_id', $assignmentIds))
                    ->where('status', Payment::STATUS_PAID)
                    ->sum('teacher_earnings'), // in the smallest currency unit
            ];
        });

        $recentSubscriptions = Subscription::with([
            'student:id,name,avatar',
            'assignment.subject:id,name',
            'group:id,name',
        ])
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->latest()
            ->limit(10)
            ->get();

        $groups = TeachingGroup::with(['assignment.subject:id,name', 'assignment.gradeLevel:id,key,name'])
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->withCount(['activeBookings', 'materials'])
            ->latest()
            ->get()
            ->map(fn (TeachingGroup $group) => [
                'id'              => $group->id,
                'name'            => $group->name,
                'subject'         => $group->assignment?->subject?->only(['id', 'name']),
                'grade'           => $group->assignment?->gradeLevel?->only(['key', 'name']),
                'monthly_price'   => $group->monthly_price,
                'currency'        => $group->currency,
                'capacity'        => $group->capacity,
                'students_count'  => $group->active_bookings_count,
                'materials_count' => $group->materials_count,
                'is_active'       => $group->is_active,
            ]);

        return Inertia::render('Teacher/Dashboard', [
            'stats'               => $stats,
            'recentSubscriptions' => $recentSubscriptions,
            'groups'              => $groups,
        ]);
    }
}
