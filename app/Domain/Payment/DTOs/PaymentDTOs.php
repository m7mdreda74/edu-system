<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTOs;

/**
 * Represents the result of creating a payment intent.
 * Decouples gateway responses from our application code.
 */
final readonly class PaymentIntentResult
{
    public function __construct(
        public string  $gatewayRef,      // e.g., pi_xxxxx from Stripe
        public string  $checkoutUrl,     // Redirect URL for customer
        public int     $amountInSmallestUnit,
        public string  $currency,
        public ?string $sessionId = null, // Stripe Session ID if applicable
    ) {}
}

/**
 * Represents verification result from a gateway webhook.
 */
final readonly class PaymentVerificationResult
{
    public function __construct(
        public string $gatewayRef,
        public bool   $isPaid,
        public int    $amountInSmallestUnit,
        public string $currency,
    ) {}
}
