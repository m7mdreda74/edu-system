<?php

declare(strict_types=1);

namespace App\Application\Certificate\Services;

use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Support\Carbon;

/**
 * CertificateService — Application Layer
 *
 * Certificates are earned per teaching group: a student who has worked through
 * every published material in a group has finished it, regardless of how many
 * months they subscribed for.
 */
class CertificateService
{
    /** Has the student completed every material published to this group? */
    public function isEligible(User $student, TeachingGroup $group): bool
    {
        return $this->progressSummary($student, $group)['certificate_ready'];
    }

    /** Completion as a 0-100 percentage, always computed server-side. */
    public function progressPercent(User $student, TeachingGroup $group): int
    {
        return $this->progressSummary($student, $group)['percent'];
    }

    /**
     * Read the two values together so a page does not count the same syllabus
     * twice when it needs both the progress bar and certificate state.
     *
     * @return array{percent: int, certificate_ready: bool}
     */
    public function progressSummary(User $student, TeachingGroup $group): array
    {
        $totalMaterials = $group->materials()->count();

        if ($totalMaterials === 0) {
            return ['percent' => 0, 'certificate_ready' => false];
        }

        $completed = $this->completedCount($student, $group);

        return [
            'percent' => (int) round(($completed / $totalMaterials) * 100),
            'certificate_ready' => $completed >= $totalMaterials,
        ];
    }

    /**
     * Certificate number, reproducible from the student/group pair so the same
     * achievement always yields the same number.
     * Format: ALT-YYYY-XXXXXXXX
     */
    public function generateCertificateNumber(User $student, TeachingGroup $group): string
    {
        $year = now()->year;
        $unique = strtoupper(substr(md5("{$student->id}-{$group->id}"), 0, 8));

        return "ALT-{$year}-{$unique}";
    }

    /** Everything the certificate view needs to render. */
    public function getCertificateData(User $student, TeachingGroup $group): array
    {
        $group->loadMissing(['assignment.teacher', 'assignment.subject', 'assignment.gradeLevel']);

        return [
            'student_name' => $student->name,
            'subject_title' => $group->assignment?->subject?->name ?? '—',
            'group_name' => $group->name,
            'grade_level' => $group->assignment?->gradeLevel?->name,
            'teacher_name' => $group->assignment?->teacher?->name ?? '—',
            'completed_at' => $this->completedAt($student, $group)?->format('Y-m-d'),
            'cert_number' => $this->generateCertificateNumber($student, $group),
            'platform_name' => 'منصة التفوق',
        ];
    }

    // ─── Internals ────────────────────────────────────────────────

    private function completedCount(User $student, TeachingGroup $group): int
    {
        return LessonProgress::where('student_id', $student->id)
            ->where('is_completed', true)
            ->whereIn('lesson_id', $group->materials()->select('group_materials.id'))
            ->count();
    }

    private function completedAt(User $student, TeachingGroup $group): ?Carbon
    {
        $timestamp = LessonProgress::where('student_id', $student->id)
            ->where('is_completed', true)
            ->whereIn('lesson_id', $group->materials()->select('group_materials.id'))
            ->max('updated_at');

        return $timestamp ? Carbon::parse($timestamp) : null;
    }
}
