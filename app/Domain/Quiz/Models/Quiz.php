<?php

declare(strict_types=1);

namespace App\Domain\Quiz\Models;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Learning\Models\GroupMaterial;
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
        'curriculum_unit_id',
        'lesson_id',
        'title',
        'passing_score',
        'time_limit_minutes',
        'available_from',
        'available_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'passing_score'      => 'integer',
            'time_limit_minutes' => 'integer',
            'available_from'     => 'datetime',
            'available_until'    => 'datetime',
            'is_active'          => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class, 'curriculum_unit_id');
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

    /** Sitting the exam is allowed now. A missing bound means that side is open. */
    public function isOpen(): bool
    {
        return $this->is_active
            && ($this->available_from === null || $this->available_from->isPast())
            && ($this->available_until === null || $this->available_until->isFuture());
    }

    /** "متاح من 2026-01-12 08:00 إلى 2026-01-20 20:00" */
    public function windowLabel(): string
    {
        $from  = $this->available_from?->format('Y-m-d H:i');
        $until = $this->available_until?->format('Y-m-d H:i');

        return match (true) {
            $from !== null && $until !== null => "متاح من {$from} إلى {$until}",
            $from !== null                    => "متاح من {$from}",
            $until !== null                   => "متاح حتى {$until}",
            default                           => 'متاح في أي وقت',
        };
    }
}
