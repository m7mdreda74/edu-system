<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school grade — the first step of the browse flow: grade → subject → teacher.
 *
 * Secondary grades carry a track: grade 10 is common, and grades 11–12 split
 * into the science and literary tracks that Qatari general secondary schools
 * run. Grades below that have no track.
 */
class GradeLevel extends Model
{
    use HasFactory;

    public const TRACK_SCIENCE  = 'science';
    public const TRACK_LITERARY = 'literary';

    public const STAGE_PRIMARY     = 'primary';
    public const STAGE_PREPARATORY = 'preparatory';
    public const STAGE_SECONDARY   = 'secondary';

    protected $table = 'grade_levels';

    protected static function newFactory(): \Database\Factories\Domain\Academic\GradeLevelFactory
    {
        return new \Database\Factories\Domain\Academic\GradeLevelFactory();
    }

    protected $fillable = [
        'key',
        'name',
        'name_en',
        'stage',
        'track',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    /** The curriculum: which subjects are taught at this grade. */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'grade_level_subject')->withTimestamps();
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'grade_level_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class, 'grade_level', 'key')
            ->whereHas('roles', fn ($q) => $q->where('name', 'student'));
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    /** Subjects that actually have an active teacher assigned to this grade. */
    public function subjectsWithTeachers(): Collection
    {
        return Subject::where('is_active', true)
            ->whereIn('id', TeachingAssignment::where('grade_level_id', $this->id)
                ->where('is_active', true)
                ->select('subject_id'))
            ->get();
    }

    public function trackLabel(): ?string
    {
        return match ($this->track) {
            self::TRACK_SCIENCE  => 'المسار العلمي',
            self::TRACK_LITERARY => 'المسار الأدبي',
            default              => null,
        };
    }

    public function stageLabel(): string
    {
        return match ($this->stage) {
            self::STAGE_PRIMARY     => 'المرحلة الابتدائية',
            self::STAGE_PREPARATORY => 'المرحلة الإعدادية',
            self::STAGE_SECONDARY   => 'المرحلة الثانوية',
            default                 => 'عام',
        };
    }
}
