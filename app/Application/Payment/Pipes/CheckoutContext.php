<?php

declare(strict_types=1);

namespace App\Application\Payment\Pipes;

use App\Domain\Course\Models\Course;
use App\Domain\Payment\Models\Coupon;
use App\Domain\User\Models\User;

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
