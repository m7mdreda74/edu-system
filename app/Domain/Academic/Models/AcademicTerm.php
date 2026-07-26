<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use App\Domain\Scheduling\Models\TeachingGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One semester of the Qatari school year, which runs in two.
 *
 * Groups and material hang off a term, so a student sees the syllabus for the
 * semester they are actually in rather than everything ever published.
 */
class AcademicTerm extends Model
{
    protected $fillable = [
        'year_label',
        'term_number',
        'name',
        'starts_on',
        'ends_on',
        'is_provisional',
    ];

    protected function casts(): array
    {
        return [
            'term_number'    => 'integer',
            'starts_on'      => 'date',
            'ends_on'        => 'date',
            'is_provisional' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function groups(): HasMany
    {
        return $this->hasMany(TeachingGroup::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────

    /** The term today falls inside. */
    public function scopeCurrent(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today);
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    /**
     * The term in progress, or — during a holiday — the next one due to start.
     * Never null once the calendar is seeded, so callers can rely on it.
     */
    public static function currentOrNext(): ?self
    {
        return self::current()->first()
            ?? self::whereDate('starts_on', '>=', now()->toDateString())->orderBy('starts_on')->first()
            ?? self::orderByDesc('starts_on')->first();
    }

    public function isCurrent(): bool
    {
        return $this->starts_on?->isPast() && $this->ends_on?->isFuture();
    }

    /** "الفصل الدراسي الأول 2025/2026" */
    public function fullName(): string
    {
        return "{$this->name} {$this->year_label}";
    }

    public function weeksRemaining(): int
    {
        if (! $this->ends_on || $this->ends_on->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInWeeks($this->ends_on, false));
    }
}
