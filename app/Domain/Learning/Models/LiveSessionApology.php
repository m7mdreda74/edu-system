<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Payment\Models\TeacherPayout;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSessionApology extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_MAKEUP_SCHEDULED = 'makeup_scheduled';

    public const STATUS_DEDUCTED = 'deducted';

    protected $fillable = [
        'live_session_id',
        'teacher_id',
        'reason',
        'status',
        'makeup_session_id',
        'makeup_scheduled_at',
        'deduction_amount',
        'admin_note',
        'resolved_by',
        'resolved_at',
        'teacher_payout_id',
    ];

    protected function casts(): array
    {
        return [
            'makeup_scheduled_at' => 'datetime',
            'resolved_at' => 'datetime',
            'deduction_amount' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function makeupSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'makeup_session_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(TeacherPayout::class, 'teacher_payout_id');
    }
}
