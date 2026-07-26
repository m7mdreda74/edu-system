<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Scheduling\Models\TeachingGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A study sheet or homework assignment published to a group.
 */
class Worksheet extends Model
{
    public const TYPE_STUDY    = 'study';
    public const TYPE_HOMEWORK = 'homework';

    protected $fillable = [
        'teaching_group_id',
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
}
