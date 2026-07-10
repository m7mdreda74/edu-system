<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Certificate\Services\CertificateService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificateService,
    ) {}

    public function show(int $enrollmentId): Response
    {
        $user       = auth()->user();
        $enrollment = Enrollment::with(['user', 'course.teacher'])
            ->where('id', $enrollmentId)
            ->where('user_id', $user->id) // Ownership check — can't view others' certs
            ->firstOrFail();

        if (! $this->certificateService->isEligible($enrollment)) {
            abort(403, 'لم تكمل هذا الكورس بعد.');
        }

        $certData = $this->certificateService->getCertificateData($enrollment);

        return Inertia::render('Student/Certificate', [
            'certificate' => $certData,
            'enrollment'  => [
                'id'           => $enrollment->id,
                'completed_at' => $enrollment->completed_at,
                'course'       => [
                    'title' => $enrollment->course->title,
                    'slug'  => $enrollment->course->slug,
                ],
            ],
        ]);
    }
}
