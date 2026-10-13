<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentStudentLink extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'parent_student_links';

    protected $fillable = [
        'parent_user_id',
        'student_user_id',
        'relationship',
        'verified_at',
        'status',
        'requested_by_user_id',
        'expires_at',
        'responded_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isApproved(): bool
    {
        return $this->verified_at !== null;
    }

    public function isPending(): bool
    {
        return $this->verified_at === null
            && $this->status === self::STATUS_PENDING
            && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function effectiveStatus(): string
    {
        if ($this->isApproved()) {
            return self::STATUS_APPROVED;
        }

        if ($this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status ?: self::STATUS_PENDING;
    }
}
