<?php

declare(strict_types=1);

namespace App\Domain\Quiz\Models;

use App\Domain\User\Models\User;
use Database\Factories\Domain\Quiz\QuizAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    use HasFactory;

    protected static function newFactory(): QuizAttemptFactory
    {
        return new QuizAttemptFactory;
    }

    protected $fillable = [
        'user_id',
        'quiz_id',
        'score',
        'earned_points',
        'total_points',
        'passed',
        'started_at',
        'submitted_at',
        'violations',
        'teacher_feedback',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer', // computed server-side ONLY
            'earned_points' => 'integer',
            'total_points' => 'integer',
            'passed' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'violations' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function isTimedOut(): bool
    {
        if (! $this->quiz->hasTimeLimit() || $this->started_at === null) {
            return false;
        }

        // Server-side time limit enforcement — never trust the client
        return $this->started_at
            ->addMinutes($this->quiz->time_limit_minutes)
            ->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
