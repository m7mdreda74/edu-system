<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTOs;

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
