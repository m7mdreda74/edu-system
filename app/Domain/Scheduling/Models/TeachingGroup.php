<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Subscription\Models\Subscription;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weekly class with a fixed capacity, sold as a monthly subscription — a
 * timetable slot. The syllabus itself lives on the assignment, so what hangs
 * off the group is only what is specific to the slot: bookings, the schedule
 * and its live sessions.
 */
class TeachingGroup extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Scheduling\TeachingGroupFactory
    {
        return new \Database\Factories\Domain\Scheduling\TeachingGroupFactory();
    }

    protected $fillable = [
        'teaching_assignment_id',
        'academic_term_id',
        'name',
        'capacity',
        'monthly_price',
        'currency',
        'day_of_week',
        'start_time',
        'end_time',
        'duration_minutes',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity'         => 'integer',
            'monthly_price'    => 'integer',
            'day_of_week'      => 'integer',
            'duration_minutes' => 'integer',
            'is_active'        => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    /** The semester this group runs in; null means it is not tied to one. */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(SessionBooking::class);
    }

    public function activeBookings(): HasMany
    {
        return $this->bookings()->where('status', 'confirmed');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(TeachingGroupSchedule::class)->orderBy('day_of_week')->orderBy('start_time');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(TeachingGroupLesson::class)->orderBy('position');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Content now belongs to the assignment's units, not to the slot, so these
     * three are plain queries rather than relationships. They cannot be eager
     * loaded — reach for `assignment.units` when you need the whole tree.
     */
    public function materials(): Builder
    {
        return GroupMaterial::whereHas('unit', $this->sameAssignment())->orderBy('order');
    }

    public function quizzes(): Builder
    {
        return Quiz::whereHas('unit', $this->sameAssignment());
    }

    public function worksheets(): Builder
    {
        return Worksheet::whereHas('unit', $this->sameAssignment());
    }

    /** Anything hanging off a unit of this slot's assignment. */
    private function sameAssignment(): Closure
    {
        return fn (Builder $query) => $query->where('teaching_assignment_id', $this->teaching_assignment_id);
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class, 'teaching_group_id');
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    public function hasCapacity(): bool
    {
        return $this->activeBookings()->count() < $this->capacity;
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->activeBookings()->count());
    }

    public function isFree(): bool
    {
        return $this->monthly_price === 0;
    }

    public function priceInMainUnit(): float
    {
        return $this->monthly_price / 100;
    }
}
