<?php

declare(strict_types=1);

namespace App\Domain\Quiz\Models;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Quiz\QuizFactory
    {
        return new \Database\Factories\Domain\Quiz\QuizFactory();
    }

    const MAX_QUIZ_ATTEMPTS = 3;

    protected $fillable = [
        'course_id',
        'lesson_id',
        'title',
        'passing_score',
        'time_limit_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'passing_score'      => 'integer',
            'time_limit_minutes' => 'integer',
            'is_active'          => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function hasTimeLimit(): bool
    {
        return $this->time_limit_minutes !== null;
    }
}
