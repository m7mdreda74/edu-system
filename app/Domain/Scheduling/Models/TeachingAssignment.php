<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Course\Models\GradeLevel;
use App\Domain\Course\Models\Subject;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['teacher_id', 'subject_id', 'grade_level_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function gradeLevel(): BelongsTo { return $this->belongsTo(GradeLevel::class); }
    public function groups(): HasMany { return $this->hasMany(TeachingGroup::class); }
    public function privateSlots(): HasMany { return $this->hasMany(PrivateSessionSlot::class); }
}
