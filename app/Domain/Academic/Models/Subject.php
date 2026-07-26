<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use App\Domain\Scheduling\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject taught at one or more grades. Opening a subject shows the teachers
 * who teach it, which is where a student picks who to study with.
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
        'grade_level',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'grade_level', 'key');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}
