<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment\Gateways;

use App\Infrastructure\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

/**
 * Stripe Gateway Implementation.
 * Uses Stripe Checkout Sessions (hosted page) — safest approach:
 *  - No card data touches our server
 *  - PCI-DSS compliance handled by Stripe
 *  - Supports 3D Secure, Apple Pay, Google Pay automatically
 *
 * Required env vars:
 *   STRIPE_KEY=pk_test_...
 *   STRIPE_SECRET=sk_test_...
 *   STRIPE_WEBHOOK_SECRET=whsec_...
 *
 * Required package: composer require stripe/stripe-php
 */
class StripeGateway implements PaymentGatewayInterface
{
    private string $publishableKey;
    private string $secretKey;
    private string $webhookSecret;

    public function __construct()
    {
        $this->publishableKey = config('services.stripe.key', '');
        $this->secretKey      = config('services.stripe.secret', '');
        $this->webhookSecret  = config('services.stripe.webhook_secret', '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public Interface Methods
    // ─────────────────────────────────────────────────────────────────────────

    public function createPaymentIntent(
        int $amountInSmallestUnit,
        string $currency,
        array $metadata = []
    ): array {
        // Mock mode fallback when no secret key configured
        if (empty($this->secretKey)) {
            return $this->createMockPaymentIntent($amountInSmallestUnit, $currency);
        }

        $paymentId = (string) ($metadata['payment_id'] ?? '');

        // Amount: Stripe accepts amounts in smallest currency unit (halala/cents)
        // For QAR: 150 QAR = 15000 halala — passed as-is
        Stripe::setApiKey($this->secretKey);

        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'line_items'           => [
                [
                    'price_data' => [
                        'currency'     => strtolower($currency),
                        'unit_amount'  => $amountInSmallestUnit,
                        'product_data' => [
                            'name' => 'منصة التفوق — كورس تعليمي',
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'payment_id' => $paymentId,
                'user_id'    => (string) ($metadata['user_id'] ?? ''),
                'course_id'  => (string) ($metadata['course_id'] ?? ''),
            ],
            'success_url' => route('checkout.success', ['payment_id' => $paymentId]),
            'cancel_url'  => route('checkout.cancel'),
        ]);

        Log::info('Stripe Checkout Session created', [
            'session_id' => $session->id,
            'payment_id' => $paymentId,
        ]);

        return [
            'gateway_ref'  => $session->payment_intent ?? $session->id,
            'redirect_url' => $session->url,
        ];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('Stripe webhook secret not configured — allowing only in local env');
            return app()->isLocal();
        }

        try {
            Stripe::setApiKey($this->secretKey);
            Webhook::constructEvent($payload, $signature, $this->webhookSecret);
            return true;
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return false;
        }
    }

    public function parseWebhookEvent(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];

        // Map Stripe event types → normalized internal status
        $statusMap = [
            'checkout.session.completed'    => 'paid',
            'payment_intent.succeeded'      => 'paid',
            'charge.succeeded'              => 'paid',
            'charge.refunded'               => 'refunded',
            'payment_intent.payment_failed' => 'failed',
            'checkout.session.expired'      => 'failed',
        ];

        $eventType  = $data['type'] ?? 'unknown';
        $dataObject = $data['data']['object'] ?? [];

        // Extract gateway_ref: prefer payment_intent, fallback to session id
        $gatewayRef = $dataObject['payment_intent']
            ?? $dataObject['id']
            ?? '';

        return [
            'event_type'  => $eventType,
            'gateway_ref' => (string) $gatewayRef,
            'status'      => $statusMap[$eventType] ?? 'pending',
        ];
    }

    public function getGatewayName(): string
    {
        return 'stripe';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retrieve publishable key for frontend use (e.g., Stripe.js).
     */
    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    /**
     * Mock fallback for local/test environments without Stripe credentials.
     *
     * @return array{ gateway_ref: string, redirect_url: string }
     */
    private function createMockPaymentIntent(int $amountInSmallestUnit, string $currency): array
    {
        Log::info('Stripe mock: createPaymentIntent', compact('amountInSmallestUnit', 'currency'));
        $mockRef = 'pi_mock_' . uniqid();

        return [
            'gateway_ref'  => $mockRef,
            'redirect_url' => route('checkout.mock_gateway', ['ref' => $mockRef]),
        ];
    }
}
