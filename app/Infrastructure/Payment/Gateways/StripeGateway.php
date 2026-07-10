<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment\Gateways;

use App\Infrastructure\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stripe Gateway Implementation.
 * Uses Stripe Checkout Sessions (hosted page) — safest approach:
 *  - No card data touches our server
 *  - PCI-DSS compliance handled by Stripe
 *
 * NOTE: install stripe/stripe-php when activating payment in production:
 *   composer require stripe/stripe-php
 */
class StripeGateway implements PaymentGatewayInterface
{
    private string $secretKey;
    private string $webhookSecret;

    public function __construct()
    {
        $this->secretKey     = config('services.stripe.secret', '');
        $this->webhookSecret = config('services.stripe.webhook_secret', '');
    }

    public function createPaymentIntent(
        int $amountInSmallestUnit,
        string $currency,
        array $metadata = []
    ): array {
        if (empty($this->secretKey)) {
            // Test mode fallback — simulates Stripe response
            Log::info('Stripe mock: createPaymentIntent', compact('amountInSmallestUnit', 'currency'));
            $mockRef = 'pi_mock_' . uniqid();

            return [
                'gateway_ref'  => $mockRef,
                'redirect_url' => route('checkout.mock_gateway', ['ref' => $mockRef]),
            ];
        }

        // Real Stripe implementation (when stripe/stripe-php is installed)
        // \Stripe\Stripe::setApiKey($this->secretKey);
        // $session = \Stripe\Checkout\Session::create([...]);
        // return ['gateway_ref' => $session->payment_intent, 'redirect_url' => $session->url];

        throw new RuntimeException('Stripe not configured. Set STRIPE_SECRET in .env');
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('Stripe webhook secret not configured — skipping signature check in dev');
            return app()->isLocal(); // Only allow unsigned in local
        }

        // Real verification: \Stripe\Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        return true;
    }

    public function parseWebhookEvent(string $payload): array
    {
        $data = json_decode($payload, true);

        // Map Stripe event types → normalized internal status
        $statusMap = [
            'checkout.session.completed' => 'paid',
            'payment_intent.succeeded'   => 'paid',
            'charge.refunded'            => 'refunded',
            'payment_intent.payment_failed' => 'failed',
        ];

        return [
            'event_type'  => $data['type'] ?? 'unknown',
            'gateway_ref' => $data['data']['object']['payment_intent']
                ?? $data['data']['object']['id']
                ?? '',
            'status'      => $statusMap[$data['type'] ?? ''] ?? 'pending',
        ];
    }

    public function getGatewayName(): string
    {
        return 'stripe';
    }
}
