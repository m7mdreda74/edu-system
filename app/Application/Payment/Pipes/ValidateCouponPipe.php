<?php

declare(strict_types=1);

namespace App\Application\Payment\Pipes;

use App\Domain\Payment\Models\Coupon;
use Closure;
use InvalidArgumentException;

/**
 * Pipe 1: Validates and resolves the coupon code.
 * Uses lockForUpdate to prevent race conditions on used_count.
 */
final class ValidateCouponPipe
{
    public function handle(CheckoutContext $ctx, Closure $next): mixed
    {
        if (! $ctx->couponCode) {
            return $next($ctx);
        }

        // lockForUpdate prevents race conditions on used_count
        $coupon = Coupon::where('code', strtoupper($ctx->couponCode))
            ->lockForUpdate()
            ->first();

        if (! $coupon || ! $coupon->isUsable()) {
            throw new InvalidArgumentException('الكوبون غير صالح أو منتهي الصلاحية.');
        }

        $ctx->appliedCoupon = $coupon;

        return $next($ctx);
    }
}
