<?php

declare(strict_types=1);

namespace App\Domain\Course\Models;

use App\Domain\Enrollment\Models\LessonProgress;
use App\Infrastructure\Observers\CourseLessonObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseLesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'title',
        'video_url',
        'duration_seconds',
        'order',
        'is_free_preview',
        'attachment_path',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'order'            => 'integer',
            'is_free_preview'  => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::observe(CourseLessonObserver::class);
    }

    // ─── Relationships ────────────────────────────────────────────

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'lesson_id');
    }

    public function worksheets(): HasMany
    {
        return $this->hasMany(Worksheet::class, 'lesson_id');
    }
}
