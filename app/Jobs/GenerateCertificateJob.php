<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Enrollment\Models\Enrollment;
use App\Notifications\CourseCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generates the student's certificate and sends completion notification.
 * ShouldBeUnique: one certificate per enrollment — never duplicated.
 */
class GenerateCertificateJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 120;

    public function __construct(
        private readonly Enrollment $enrollment,
    ) {}

    public function uniqueId(): string
    {
        return "certificate-enrollment-{$this->enrollment->id}";
    }

    public function handle(): void
    {
        $enrollment = $this->enrollment->load(['user', 'course']);

        // Guard: only generate for completed enrollments
        if (! $enrollment->isCompleted()) {
            return;
        }

        // TODO: Generate PDF with dompdf
        // $pdf = PDF::loadView('certificates.template', compact('enrollment'));
        // $path = "certificates/enrollment-{$enrollment->id}.pdf";
        // Storage::put($path, $pdf->output());
        // $enrollment->update(['certificate_path' => $path]);

        // Send completion notification (queued separately)
        $enrollment->user->notify(
            new CourseCompletedNotification($enrollment->course, $enrollment)
        );
    }
}
