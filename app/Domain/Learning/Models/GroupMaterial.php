<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Scheduling\Models\TeachingGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Recorded material a teacher publishes to one of their groups: a video, a
 * file, or both. Subscribed students see it; everyone else only sees the ones
 * flagged as a free preview.
 */
class GroupMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'group_materials';

    protected $fillable = [
        'teaching_group_id',
        'academic_term_id',
        'title',
        'video_url',
        'duration_seconds',
        'order',
        'is_free_preview',
        'attachment_path',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'order'            => 'integer',
            'is_free_preview'  => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'lesson_id');
    }

    public function worksheets(): HasMany
    {
        return $this->hasMany(Worksheet::class, 'lesson_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(LessonQuestion::class, 'lesson_id');
    }
}
