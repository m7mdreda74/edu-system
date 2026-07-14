<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use App\Domain\Course\Models\Course;
use App\Domain\User\Models\User;
use App\Infrastructure\Observers\PaymentObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Payment\PaymentFactory
    {
        return new \Database\Factories\Domain\Payment\PaymentFactory();
    }

    // Payment status constants — no magic strings
    const STATUS_PENDING              = 'pending';
    const STATUS_PENDING_VERIFICATION = 'pending_verification';
    const STATUS_PAID                 = 'paid';
    const STATUS_FAILED               = 'failed';
    const STATUS_REFUNDED             = 'refunded';

    protected $fillable = [
        'user_id',
        'course_id',
        'coupon_id',
        'amount',
        'original_amount',
        'currency',
        'gateway',
        'gateway_ref',
        'status',
        'receipt_path',
        'paid_at',
        'purchase_request_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'integer', // in smallest unit (halala/cent) — no floats!
            'original_amount' => 'integer',
            'paid_at'         => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::observe(PaymentObserver::class);
    }

    // ─── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Returns amount in main currency unit (e.g. QAR not halala) */
    public function getAmountInMainUnit(): float
    {
        return $this->amount / 100;
    }

    /** Returns discount amount in smallest unit */
    public function getDiscountAmount(): int
    {
        return $this->original_amount - $this->amount;
    }
}
