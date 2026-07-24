<?php

declare(strict_types=1);

namespace App\Application\Payment\Pipes;

use Closure;

/**
 * Pipe 2: Applies coupon discount to the final amount.
 * Uses integer arithmetic only — no float precision errors.
 */
final class ApplyDiscountPipe
{
    public function handle(CheckoutContext $ctx, Closure $next): mixed
    {
        if ($ctx->appliedCoupon) {
            // Integer arithmetic only — no float precision errors
            $ctx->finalAmount = $ctx->appliedCoupon->applyDiscount($ctx->finalAmount);
        }

        return $next($ctx);
    }
}
