<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Payment\CouponFactory
    {
        return new \Database\Factories\Domain\Payment\CouponFactory();
    }

    protected $fillable = [
        'code',
        'discount_percent',
        'expires_at',
        'usage_limit',
        'used_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'integer',
            'usage_limit'      => 'integer',
            'used_count'       => 'integer',
            'expires_at'       => 'datetime',
            'is_active'        => 'boolean',
        ];
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    public function isExpired(): bool
    {
        // The admin form accepts a calendar date, so the coupon remains valid
        // through the selected day and expires at its end rather than at
        // midnight when the timestamp was cast from YYYY-MM-DD.
        return $this->expires_at !== null && $this->expires_at->copy()->endOfDay()->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null
            && $this->used_count >= $this->usage_limit;
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && ! $this->isExpired()
            && ! $this->isExhausted();
    }

    /**
     * Apply discount to an amount (in smallest unit).
     * Uses integer arithmetic to avoid float precision issues.
     */
    public function applyDiscount(int $amountInSmallestUnit): int
    {
        $discountAmount = (int) round($amountInSmallestUnit * $this->discount_percent / 100);

        return max(0, $amountInSmallestUnit - $discountAmount);
    }
}
