<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Course\Models\CourseLesson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use HasFactory;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'enrollment_id',
        'lesson_id',
        'watched_seconds',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'watched_seconds' => 'integer',
            'is_completed'    => 'boolean',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }
}
