<?php

declare(strict_types=1);

namespace App\Domain\Course\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

class GradeLevel extends Model
{
    use HasFactory;

    protected $table = 'grade_levels';

    protected $fillable = [
        'key',
        'name',
        'name_en',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'grade_level', 'key');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'grade_level', 'key');
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class, 'grade_level', 'key')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'student');
            });
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    /**
     * Get teachers teaching courses in this grade level.
     */
    public function getTeachersAttribute(): Collection
    {
        return User::whereHas('roles', function ($q) {
            $q->where('name', 'teacher');
        })
        ->whereHas('coursesAsTeacher', function ($q) {
            $q->where('grade_level', $this->key);
        })
        ->get();
    }
}
