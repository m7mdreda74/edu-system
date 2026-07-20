<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Certificate\Services\CertificateService;
use App\Domain\Course\Models\LiveSession;
use App\Domain\Enrollment\Contracts\EnrollmentServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly EnrollmentServiceInterface $enrollmentService,
        private readonly CertificateService $certificateService,
    ) {}

    public function index(): Response
    {
        $user        = Auth::user();
        $enrollments = $this->enrollmentService->getStudentEnrollments($user->id);

        // Separate completed from in-progress
        $completed  = $enrollments->where('progress_percent', 100);
        $inProgress = $enrollments->where('progress_percent', '<', 100);

        // Add certificate eligibility flag
        $completedWithCert = $completed->map(function ($enrollment) {
            $enrollment->cert_number = $this->certificateService->generateCertificateNumber($enrollment);
            return $enrollment;
        });

        // Legacy course sessions follow course enrollment. Scheduled group/private
        // sessions are stricter: only the confirmed booking holder may see them.
        $enrolledCourseIds = $enrollments->pluck('course_id')->toArray();
        $upcomingSessions = LiveSession::with('course:id,title', 'teacher:id,name')
            ->where(function ($query) use ($enrolledCourseIds, $user) {
                $query->where(function ($legacy) use ($enrolledCourseIds) {
                    $legacy->whereNull('teaching_group_id')
                        ->whereNull('private_session_slot_id')
                        ->whereIn('course_id', $enrolledCourseIds);
                })->orWhereHas('teachingGroup.activeBookings', function ($booking) use ($user) {
                    $booking->where('student_id', $user->id);
                })->orWhereHas('privateSessionSlot.booking', function ($booking) use ($user) {
                    $booking->where('student_id', $user->id)->where('status', 'confirmed');
                });
            })
            ->whereIn('status', ['scheduled', 'live'])
            ->orderBy('scheduled_at', 'asc')
            ->get();

        // Fetch pending manual payments awaiting verification
        $pendingPayments = \App\Domain\Payment\Models\Payment::with('course:id,title,thumbnail')
            ->where('user_id', $user->id)
            ->where('status', \App\Domain\Payment\Models\Payment::STATUS_PENDING_VERIFICATION)
            ->get();

        return Inertia::render('Student/Dashboard', [
            'inProgress'       => $inProgress->values(),
            'completed'        => $completedWithCert->values(),
            'upcomingSessions' => $upcomingSessions,
            'pendingPayments'  => $pendingPayments,
            'stats'      => [
                'totalEnrolled'  => $enrollments->count(),
                'completed'      => $completed->count(),
                'inProgress'     => $inProgress->count(),
                'avgProgress'    => $enrollments->count() > 0
                    ? (int) round($enrollments->avg('progress_percent'))
                    : 0,
            ],
        ]);
    }
}
