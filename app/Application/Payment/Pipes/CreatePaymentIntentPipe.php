<?php

declare(strict_types=1);

namespace App\Application\Payment\Pipes;

use App\Infrastructure\Payment\PaymentGatewayInterface;
use Closure;

/**
 * Pipe 3: Calls the payment gateway to create a checkout session.
 * Skips gateway entirely for free courses (finalAmount === 0).
 */
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
