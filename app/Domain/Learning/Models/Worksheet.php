<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Scheduling\Models\TeachingGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A file the teacher publishes: a study sheet, the homework of a lesson, or the
 * paper form of a unit exam. Homework carries a `lesson_id`; a paper exam has
 * none and hangs off the unit directly.
 */
class Worksheet extends Model
{
    public const TYPE_STUDY      = 'study';
    public const TYPE_HOMEWORK   = 'homework';
    public const TYPE_PAPER_EXAM = 'paper_exam';

    protected $fillable = [
        'curriculum_unit_id',
        'lesson_id',
        'teaching_group_id',
        'title',
        'file_path',
        'type',
        'requires_submission',
        'available_from',
        'due_date',
        'allow_late_submission',
        'max_attempts',
        'max_score',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_submission'   => 'boolean',
            'available_from'        => 'datetime',
            'allow_late_submission' => 'boolean',
            'max_attempts'          => 'integer',
            'due_date'              => 'date',
            'max_score'             => 'integer',
            'is_active'             => 'boolean',
            'teaching_group_id'     => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class, 'curriculum_unit_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(GroupMaterial::class, 'lesson_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WorksheetSubmission::class);
    }

    public function isOpen(): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        if ($this->available_from !== null && $this->available_from->isFuture()) {
            return false;
        }

        if ($this->due_date !== null && now()->startOfDay()->isAfter($this->due_date)) {
            return (bool) $this->allow_late_submission;
        }

        return true;
    }
}

