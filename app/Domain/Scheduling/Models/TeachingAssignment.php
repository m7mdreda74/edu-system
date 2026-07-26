<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "This teacher teaches this subject to this grade" — the unit a student
 * lands on after picking a grade and a subject, and the thing private tuition
 * is priced against.
 */
class TeachingAssignment extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Scheduling\TeachingAssignmentFactory
    {
        return new \Database\Factories\Domain\Scheduling\TeachingAssignmentFactory();
    }

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'grade_level_id',
        'private_monthly_price',
        'currency',
        'accepts_private',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'private_monthly_price' => 'integer',
            'accepts_private'       => 'boolean',
            'is_active'             => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TeachingGroup::class);
    }

    public function privateSlots(): HasMany
    {
        return $this->hasMany(PrivateSessionSlot::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    public function offersPrivate(): bool
    {
        return $this->accepts_private && $this->private_monthly_price > 0;
    }

    /** Cheapest monthly price across this teacher's active groups. */
    public function cheapestGroupPrice(): ?int
    {
        $price = $this->groups()->where('is_active', true)->min('monthly_price');

        return $price === null ? null : (int) $price;
    }
}
