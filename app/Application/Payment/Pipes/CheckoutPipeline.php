<?php

declare(strict_types=1);

namespace App\Application\Payment\Pipes;

use App\Domain\Course\Models\Course;
use App\Domain\Payment\Models\Coupon;
use App\Domain\User\Models\User;
use App\Infrastructure\Payment\PaymentGatewayInterface;
use Closure;
use InvalidArgumentException;

/**
 * CheckoutContext — passes through the checkout pipeline.
 * Mutable during the pipeline, frozen after.
 */
final class CheckoutContext
{
    public int     $finalAmount;
    public ?Coupon $appliedCoupon = null;
    public ?string $checkoutUrl   = null;
    public ?string $gatewayRef    = null;

    public function __construct(
        public readonly User    $user,
        public readonly Course  $course,
        public readonly ?string $couponCode,
    ) {
        $this->finalAmount = $course->getEffectivePrice();
    }
}

// ─── Pipe 1: Validate Coupon ─────────────────────────────────────────────────

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

// ─── Pipe 2: Apply Discount ───────────────────────────────────────────────────

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

// ─── Pipe 3: Create Payment Intent ──────────────────────────────────────────

final class CreatePaymentIntentPipe
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    public function handle(CheckoutContext $ctx, Closure $next): mixed
    {
        if ($ctx->finalAmount === 0) {
            // Free course — skip gateway entirely
            return $next($ctx);
        }

        /** @var array{gateway_ref: string, redirect_url: string} $result */
        $result = $this->gateway->createPaymentIntent(
            $ctx->finalAmount,
            'QAR',
            [
                'user_id'   => $ctx->user->id,
                'course_id' => $ctx->course->id,
                'coupon_id' => $ctx->appliedCoupon?->id,
            ]
        );

        // Gateway returns array — use array access (not object property access)
        $ctx->checkoutUrl = $result['redirect_url'];
        $ctx->gatewayRef  = $result['gateway_ref'];

        return $next($ctx);
    }
}
