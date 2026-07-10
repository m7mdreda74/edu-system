<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Certificate\Services\CertificateService;
use App\Domain\Course\Models\LiveSession;
use App\Domain\Enrollment\Contracts\EnrollmentServiceInterface;
use App\Http\Controllers\Controller;
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
        $user        = auth()->user();
        $enrollments = $this->enrollmentService->getStudentEnrollments($user->id);

        // Separate completed from in-progress
        $completed  = $enrollments->where('progress_percent', 100);
        $inProgress = $enrollments->where('progress_percent', '<', 100);

        // Add certificate eligibility flag
        $completedWithCert = $completed->map(function ($enrollment) {
            $enrollment->cert_number = $this->certificateService->generateCertificateNumber($enrollment);
            return $enrollment;
        });

        // Fetch upcoming live sessions for enrolled courses
        $enrolledCourseIds = $enrollments->pluck('course_id')->toArray();
        $upcomingSessions = LiveSession::with('course:id,title', 'teacher:id,name')
            ->whereIn('course_id', $enrolledCourseIds)
            ->whereIn('status', ['scheduled', 'live'])
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return Inertia::render('Student/Dashboard', [
            'inProgress'       => $inProgress->values(),
            'completed'        => $completedWithCert->values(),
            'upcomingSessions' => $upcomingSessions,
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
