<?php

declare(strict_types=1);

namespace App\Domain\Quiz\Models;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionBankQuestion extends Model
{
    use HasFactory;

    public const TYPE_SINGLE = 'single';

    public const TYPE_MULTIPLE = 'multiple';

    public const TYPE_TRUE_FALSE = 'true_false';

    public const TYPE_ESSAY = 'essay';

    public const DIFFICULTY_EASY = 'easy';

    public const DIFFICULTY_MEDIUM = 'medium';

    public const DIFFICULTY_HARD = 'hard';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_SHARED = 'shared';

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'curriculum_unit_id',
        'lesson_id',
        'question_text',
        'type',
        'points',
        'allow_file_upload',
        'topic',
        'difficulty',
        'explanation',
        'model_answer',
        'tags',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'allow_file_upload' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class, 'curriculum_unit_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(GroupMaterial::class, 'lesson_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionBankOption::class)->orderBy('id');
    }

    public function scopeVisibleTo(Builder $query, User $teacher): Builder
    {
        return $query->where(function (Builder $visible) use ($teacher): void {
            $visible
                ->where('teacher_id', $teacher->id)
                ->orWhere('visibility', self::VISIBILITY_SHARED);
        });
    }

    public function isOwnedBy(User $teacher): bool
    {
        return (int) $this->teacher_id === (int) $teacher->id;
    }

    public function isVisibleTo(User $teacher): bool
    {
        return $this->isOwnedBy($teacher) || $this->visibility === self::VISIBILITY_SHARED;
    }
}
