<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Scheduling\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningOutcome extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'curriculum_unit_id',
        'group_material_id',
        'quiz_id',
        'title',
        'description',
        'order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class, 'curriculum_unit_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(GroupMaterial::class, 'group_material_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }
}
