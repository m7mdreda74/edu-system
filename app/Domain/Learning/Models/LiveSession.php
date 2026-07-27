<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A live WebRTC classroom. It belongs either to a weekly group or to a booked
 * private slot — never to both.
 */
class LiveSession extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE      = 'live';
    public const STATUS_ENDED     = 'ended';

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
        'room_id',
        'recording_url',
        'is_published_as_lesson',
        'lesson_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'           => 'datetime',
            'started_at'             => 'datetime',
            'ended_at'               => 'datetime',
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

    // ─── Domain Helpers ────────────────────────────────────────────

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isPrivate(): bool
    {
        return $this->private_session_slot_id !== null;
    }
}
