<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use App\Domain\Subscription\Models\Subscription;
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
        'subscription_id',
        'coupon_id',
        'amount',
        'commission_percent',
        'platform_commission_amount',
        'teacher_earnings',
        'original_amount',
        'currency',
        'gateway',
        'gateway_ref',
        'status',
        'receipt_path',
        'paid_at',
        'purchase_request_id',
        'teacher_payout_id',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'integer', // in smallest unit (halala/cent) — no floats!
            'original_amount' => 'integer',
            'commission_percent' => 'integer',
            'platform_commission_amount' => 'integer',
            'teacher_earnings' => 'integer',
            'paid_at'         => 'datetime',
            'reviewed_at'     => 'datetime',
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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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
