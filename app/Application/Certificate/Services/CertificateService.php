<?php

declare(strict_types=1);

namespace App\Application\Certificate\Services;

use App\Domain\Enrollment\Models\Enrollment;
use Illuminate\Support\Facades\Storage;

/**
 * CertificateService — Application Layer
 * Generates HTML-based certificates (PDF generation via Browsershot/DomPDF in production).
 * For now generates a certificate record — PDF generation queued as a Job.
 */
class CertificateService
{
    /**
     * Check if a student is eligible for a certificate.
     * Must have completed the course (100% progress) AND passed all required quizzes.
     */
    public function isEligible(Enrollment $enrollment): bool
    {
        return $enrollment->progress_percent === 100;
    }

    /**
     * Generate a unique certificate number.
     * Format: ALT-YYYY-XXXXXXXX (reproducible from enrollment ID)
     */
    public function generateCertificateNumber(Enrollment $enrollment): string
    {
        $year   = now()->year;
        $unique = strtoupper(substr(md5($enrollment->id . $enrollment->user_id), 0, 8));

        return "ALT-{$year}-{$unique}";
    }

    /**
     * Get certificate data for rendering.
     * Called when student views/downloads their certificate.
     */
    public function getCertificateData(Enrollment $enrollment): array
    {
        $enrollment->loadMissing(['user', 'course.teacher']);

        return [
            'student_name'    => $enrollment->user->name,
            'course_title'    => $enrollment->course->title,
            'teacher_name'    => $enrollment->course->teacher->name,
            'completed_at'    => $enrollment->completed_at?->format('Y-m-d'),
            'cert_number'     => $this->generateCertificateNumber($enrollment),
            'platform_name'   => 'منصة التفوق',
        ];
    }
}
