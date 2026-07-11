<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment\Gateways;

use App\Infrastructure\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fatora Gateway Implementation (Qatar).
 * Handles payments using Fatora.io v1 Checkout API.
 */
class FatoraGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('services.fatora.api_key', '');
        $this->baseUrl = 'https://api.fatora.io/v1';
    }

    public function createPaymentIntent(
        int $amountInSmallestUnit,
        string $currency,
        array $metadata = []
    ): array {
        $paymentId = (string) ($metadata['payment_id'] ?? '');
        $orderId   = 'ORD-' . $paymentId . '-' . uniqid();

        if (empty($this->apiKey)) {
            // Mock mode for local testing
            Log::info('Fatora mock: createPaymentIntent', compact('amountInSmallestUnit', 'currency'));
            $mockRef = 'fatora_mock_' . uniqid();

            return [
                'gateway_ref'  => $mockRef,
                'redirect_url' => route('checkout.mock_gateway', ['ref' => $mockRef]),
            ];
        }

        // Convert smallest unit to decimal (e.g. 15000 halala -> 150.00 QAR)
        $amount = (float) ($amountInSmallestUnit / 100);

        // Call Fatora v1 Checkout API
        $http = Http::withHeaders([
            'Content-Type' => 'application/json',
            'api_key'      => $this->apiKey,
        ]);

        if (app()->isLocal()) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($this->baseUrl . '/payments/checkout', [
            'amount'       => $amount,
            'currency'     => $currency,
            'order_id'     => $orderId,
            'client'       => [
                'name'  => 'Student',
                'phone' => '00000000',
                'email' => 'student@altafawwuq.com',
            ],
            'language'     => 'ar',
            'success_url'  => route('checkout.success', ['payment_id' => $paymentId]),
            'failure_url'  => route('checkout.cancel'),
            'note'         => 'Payment ID: ' . $paymentId,
        ]);

        if ($response->failed()) {
            Log::error('Fatora checkout API call failed: ' . $response->body());
            throw new RuntimeException('Fatora API returned an error.');
        }

        $data = $response->json();
        if (strtolower($data['status'] ?? '') !== 'success') {
            Log::error('Fatora checkout logic error: ' . json_encode($data));
            throw new RuntimeException('Fatora: ' . ($data['message'] ?? 'Unknown error'));
        }

        return [
            'gateway_ref'  => $orderId,
            'redirect_url' => (string) ($data['result']['checkout_url'] ?? ''),
        ];
    }

    /**
     * Get payment status details from Fatora.
     */
    public function getPaymentStatus(string $transactionId): array
    {
        if (empty($this->apiKey)) {
            return ['status' => 'pending'];
        }

        $http = Http::withHeaders([
            'Content-Type' => 'application/json',
            'api_key'      => $this->apiKey,
        ]);

        if (app()->isLocal()) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($this->baseUrl . '/payments/verify', [
            'transaction_id' => $transactionId,
        ]);

        if ($response->failed()) {
            Log::error('Fatora verify API call failed: ' . $response->body());
            return ['status' => 'failed'];
        }

        $data = $response->json();
        if (strtolower($data['status'] ?? '') !== 'success') {
            Log::error('Fatora verify logic error: ' . json_encode($data));
            return ['status' => 'failed'];
        }

        $paymentStatus = $data['result']['payment_status'] ?? '';

        return [
            'status'      => strtoupper($paymentStatus) === 'SUCCESS' ? 'paid' : 'pending',
            'payment_id'  => '',
            'invoice_id'  => $transactionId,
        ];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->apiKey)) {
            return app()->isLocal();
        }
        return true;
    }

    public function parseWebhookEvent(string $payload): array
    {
        $data = json_decode($payload, true);

        // Normalize Fatora Webhook structure
        $status = strtoupper($data['response_code'] ?? '') === '000' ? 'paid' : 'pending';

        return [
            'event_type'  => $data['event'] ?? 'unknown',
            'gateway_ref' => (string) ($data['order_id'] ?? ''),
            'status'      => $status,
        ];
    }

    public function getGatewayName(): string
    {
        return 'fatora';
    }
}
