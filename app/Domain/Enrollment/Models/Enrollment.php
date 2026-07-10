<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;
use App\Domain\User\Models\User;
use App\Infrastructure\Observers\EnrollmentObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Enrollment\EnrollmentFactory
    {
        return new \Database\Factories\Domain\Enrollment\EnrollmentFactory();
    }

    protected $fillable = [
        'user_id',
        'course_id',
        'progress_percent',
        'enrolled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'enrolled_at'      => 'datetime',
            'completed_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::observe(EnrollmentObserver::class);
    }

    // ─── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Recalculate progress_percent server-side from lesson_progress records.
     * This is the ONLY source of truth — never accept from client.
     */
    public function recalculateProgress(): void
    {
        $totalLessons = $this->course->total_lessons;

        if ($totalLessons === 0) {
            return;
        }

        $completedLessons = $this->lessonProgress()
            ->where('is_completed', true)
            ->count();

        $percent = (int) round(($completedLessons / $totalLessons) * 100);

        $this->update([
            'progress_percent' => $percent,
            'completed_at'     => $percent === 100 ? now() : null,
        ]);
    }

    public function hasCompletedLesson(CourseLesson $lesson): bool
    {
        return $this->lessonProgress()
            ->where('lesson_id', $lesson->id)
            ->where('is_completed', true)
            ->exists();
    }
}
