<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school grade — the first step of the browse flow: grade → subject → teacher.
 */
class GradeLevel extends Model
{
    use HasFactory;

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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    /** Subjects are matched by the grade key ("grade_12"), not the row id. */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'grade_level', 'key');
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
    public function subjectsWithTeachers()
    {
        return Subject::where('is_active', true)
            ->whereIn('id', TeachingAssignment::where('grade_level_id', $this->id)
                ->where('is_active', true)
                ->select('subject_id'))
            ->get();
    }
}
