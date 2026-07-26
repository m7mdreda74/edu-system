<?php

declare(strict_types=1);

namespace App\Domain\Quiz\Models;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Scheduling\Models\TeachingGroup;
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
        'teaching_group_id',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(GroupMaterial::class, 'lesson_id');
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
