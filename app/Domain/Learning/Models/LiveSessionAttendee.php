<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's (or teacher's) attendance window in a live class.
 *
 * Heartbeats keep the row alive while the room is open; the leave endpoint
 * closes it explicitly. A crashed browser is closed by the next heartbeat
 * cleanup, so the report never depends on a perfect unload event.
 */
class LiveSessionAttendee extends Model
{
    protected $table = 'live_session_attendees';

    protected $fillable = [
        'live_session_id',
        'user_id',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at'   => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function durationSeconds(): int
    {
        if ($this->joined_at === null) {
            return 0;
        }

        return max(0, (int) $this->joined_at->diffInSeconds($this->left_at ?? now()));
    }
}
