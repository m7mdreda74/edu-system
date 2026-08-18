<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use App\Domain\Scheduling\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject taught at one or more grades. Opening a subject shows the teachers
 * who teach it, which is where a student picks who to study with.
 *
 * Arabic and maths run the length of the curriculum, so the grades a subject
 * belongs to are a many-to-many relationship, not a column.
 */
class Subject extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Academic\SubjectFactory
    {
        return new \Database\Factories\Domain\Academic\SubjectFactory();
    }

    protected $fillable = [
        'name',
        'name_en',
        'icon',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    /** The grades this subject is on the curriculum for. */
    public function gradeLevels(): BelongsToMany
    {
        return $this->belongsToMany(GradeLevel::class, 'grade_level_subject')->withTimestamps();
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}
