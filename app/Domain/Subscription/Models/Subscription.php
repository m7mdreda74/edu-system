<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use App\Domain\Payment\Models\Payment;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One month of access, either to a weekly group or to a teacher's private
 * tuition. This is what replaced buying a course: access is a window in time,
 * renewed month by month, not a permanent unlock.
 */
class Subscription extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Subscription\SubscriptionFactory
    {
        return new \Database\Factories\Domain\Subscription\SubscriptionFactory();
    }

    public const TYPE_GROUP   = 'group';
    public const TYPE_PRIVATE = 'private';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'student_id',
        'type',
        'teaching_assignment_id',
        'teaching_group_id',
        'monthly_price',
        'currency',
        'period_start',
        'period_end',
        'status',
        'auto_renew',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'period_start'  => 'date',
            'period_end'    => 'date',
            'auto_renew'    => 'boolean',
            'cancelled_at'  => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────

    /** Paid for, and today falls inside the billing period. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereDate('period_end', '>=', now()->toDateString());
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->period_end !== null
            && ! $this->period_end->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPrivate(): bool
    {
        return $this->type === self::TYPE_PRIVATE;
    }

    public function daysRemaining(): int
    {
        if ($this->period_end === null) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->period_end, false));
    }

    /** Price in the main unit (QAR) rather than the stored dirham. */
    public function priceInMainUnit(): float
    {
        return $this->monthly_price / 100;
    }

    /**
     * Human label used in notifications and invoices, e.g.
     * "رياضيات — أ. محمد رضا — مجموعة الأحد".
     */
    public function label(): string
    {
        $this->loadMissing(['assignment.subject', 'assignment.teacher', 'group']);

        $parts = array_filter([
            $this->assignment?->subject?->name,
            $this->assignment?->teacher?->name,
            $this->isPrivate() ? 'حصص خاصة' : $this->group?->name,
        ]);

        return $parts === [] ? 'اشتراك' : implode(' — ', $parts);
    }
}
