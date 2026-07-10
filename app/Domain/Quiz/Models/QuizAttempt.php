<?php

declare(strict_types=1);

namespace App\Domain\Quiz\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Quiz\QuizAttemptFactory
    {
        return new \Database\Factories\Domain\Quiz\QuizAttemptFactory();
    }

    protected $fillable = [
        'user_id',
        'quiz_id',
        'score',
        'passed',
        'started_at',
        'submitted_at',
        'violations',
    ];

    protected function casts(): array
    {
        return [
            'score'        => 'integer', // computed server-side ONLY
            'passed'       => 'boolean',
            'started_at'   => 'datetime',
            'submitted_at' => 'datetime',
            'violations'   => 'integer',
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
