<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Academic\Models\CurriculumUnit;
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
        'title',
        'file_path',
        'type',
        'requires_submission',
        'due_date',
        'max_score',
    ];

    protected function casts(): array
    {
        return [
            'requires_submission' => 'boolean',
            'due_date'            => 'date',
            'max_score'           => 'integer',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(WorksheetSubmission::class);
    }
}
