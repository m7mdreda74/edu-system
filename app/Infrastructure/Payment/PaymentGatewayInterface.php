<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment;

/**
 * PaymentGatewayInterface — Abstraction over payment providers.
 * Swap Stripe ↔ Fatora ↔ SkipCash by changing the DI binding only.
 * (Dependency Inversion + Strategy Pattern)
 */
interface PaymentGatewayInterface
{
    /**
     * Create a payment intent/session.
     * Returns gateway-specific data needed to redirect/render payment form.
     *
     * @param int    $amountInSmallestUnit  In halala (1 QAR = 100 halala)
     * @param string $currency              ISO 4217 currency code
     * @param array  $metadata             Arbitrary metadata stored on gateway
     * @return array{ gateway_ref: string, redirect_url: string }
     */
    public function createPaymentIntent(
        int $amountInSmallestUnit,
        string $currency,
        array $metadata = []
    ): array;

    /**
     * Verify a webhook event came from the gateway (signature check).
     * MUST be called before processing any webhook.
     *
     * @param string $payload   Raw request body
     * @param string $signature Gateway-provided signature header
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool;

    /**
     * Parse a verified webhook payload into a normalized event.
     *
     * @return array{ event_type: string, gateway_ref: string, status: string }
     */
    public function parseWebhookEvent(string $payload): array;

    public function getGatewayName(): string;
}
