<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domain\Enrollment\Models\Enrollment;

/**
 * Reacts to Enrollment model events.
 * When progress_percent reaches 100, triggers certificate generation.
 */
class EnrollmentObserver
{
    public function updated(Enrollment $enrollment): void
    {
        // Only react when progress_percent changes TO 100
        if (
            $enrollment->wasChanged('progress_percent')
            && $enrollment->progress_percent === 100
        ) {
            // Dispatch certificate generation job
            \App\Jobs\GenerateCertificateJob::dispatch($enrollment);
        }
    }
}
