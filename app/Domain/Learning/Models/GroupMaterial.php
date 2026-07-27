<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Academic\Models\CurriculumUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * "الدرس" — one lesson of a unit: the explanation video, the booklet the
 * teacher uploads, and the homework hanging off it. Subscribed students see it;
 * everyone else only sees the ones flagged as a free preview.
 */
class GroupMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): \Database\Factories\Domain\Learning\GroupMaterialFactory
    {
        return new \Database\Factories\Domain\Learning\GroupMaterialFactory();
    }

    protected $table = 'group_materials';

    protected $fillable = [
        'curriculum_unit_id',
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class, 'curriculum_unit_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'lesson_id');
    }

    public function worksheets(): HasMany
    {
        return $this->hasMany(Worksheet::class, 'lesson_id');
    }

    /** "الواجب" — a lesson carries at most one. */
    public function homework(): HasOne
    {
        return $this->hasOne(Worksheet::class, 'lesson_id')
            ->where('type', Worksheet::TYPE_HOMEWORK);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(LessonQuestion::class, 'lesson_id');
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    /**
     * Lesson counts keyed by teaching assignment. A group can no longer
     * `withCount('materials')` — the lessons hang off the assignment's units —
     * so screens that list groups resolve the number in one query here.
     *
     * @param  iterable<int>  $assignmentIds
     * @return Collection<int, int>
     */
    public static function countsByAssignment(iterable $assignmentIds): Collection
    {
        return self::query()
            ->join('curriculum_units', 'curriculum_units.id', '=', 'group_materials.curriculum_unit_id')
            ->whereIn('curriculum_units.teaching_assignment_id', Collection::wrap($assignmentIds)->all())
            ->groupBy('curriculum_units.teaching_assignment_id')
            ->selectRaw('curriculum_units.teaching_assignment_id as assignment_id, count(*) as total')
            ->pluck('total', 'assignment_id')
            ->mapWithKeys(fn ($total, $assignmentId) => [(int) $assignmentId => (int) $total]);
    }
}
