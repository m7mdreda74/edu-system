<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A live Jitsi classroom. It belongs either to a weekly group or to a booked
 * private slot — never to both.
 */
class LiveSession extends Model
{
    /** A live room is stale after the longest supported class plus a buffer. */
    private const LIVE_WINDOW_HOURS = 12;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LIVE = 'live';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'teacher_id',
        'teaching_group_id',
        'private_session_slot_id',
        'title',
        'description',
        'scheduled_at',
        'started_at',
        'ended_at',
        'status',
        'recording_url',
        'is_published_as_lesson',
        'lesson_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_published_as_lesson' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function privateSessionSlot(): BelongsTo
    {
        return $this->belongsTo(PrivateSessionSlot::class, 'private_session_slot_id');
    }

    /** The recording, once published as material. */
    public function material(): BelongsTo
    {
        return $this->belongsTo(GroupMaterial::class, 'lesson_id');
    }

    /** Permanent attendance rows used by the teacher's post-class report. */
    public function attendees(): HasMany
    {
        return $this->hasMany(LiveSessionAttendee::class, 'live_session_id');
    }

    public function apology(): HasOne
    {
        return $this->hasOne(LiveSessionApology::class, 'live_session_id');
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE
            && $this->started_at !== null
            && $this->started_at->greaterThanOrEqualTo(now()->subHours(self::LIVE_WINDOW_HOURS));
    }

    public function isPrivate(): bool
    {
        return $this->private_session_slot_id !== null;
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where(function (Builder $query) use ($studentId): void {
            $query->whereHas(
                'teachingGroup.activeBookings',
                fn (Builder $bookings) => $bookings->where('student_id', $studentId),
            )->orWhereHas(
                'privateSessionSlot.booking',
                fn (Builder $booking) => $booking
                    ->where('student_id', $studentId)
                    ->where('status', 'confirmed'),
            );
        });
    }

    /** Scheduled future classes plus any class that is currently live. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where(function (Builder $live): void {
                $live->where('status', self::STATUS_LIVE)
                    ->whereNotNull('started_at')
                    ->where('started_at', '>=', now()->subHours(self::LIVE_WINDOW_HOURS));
            })
                ->orWhere(function (Builder $scheduled): void {
                    $scheduled->where('status', self::STATUS_SCHEDULED)
                        ->where('scheduled_at', '>=', now());
                });
        });
    }
}
