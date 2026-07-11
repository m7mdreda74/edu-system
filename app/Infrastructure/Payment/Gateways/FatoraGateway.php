<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment\Gateways;

use App\Infrastructure\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fatora Gateway Implementation (Qatar).
 * Handles payments using Fatora.io / Fatora.me APIs.
 */
class FatoraGateway implements PaymentGatewayInterface
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.fatora.api_key', '');
    }

    public function createPaymentIntent(
        int $amountInSmallestUnit,
        string $currency,
        array $metadata = []
    ): array {
        $paymentId = (string) ($metadata['payment_id'] ?? '');

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

        // Call Fatora.io API to generate invoice
        $response = Http::withHeaders([
            'api_key' => $this->apiKey,
        ])->post('https://fatora.io/api/v2/new-invoice', [
            'amount'       => $amount,
            'currency'     => $currency,
            'client_name'  => 'Student',
            'client_email' => 'student@altafawwuq.com',
            'success_url'  => route('checkout.success', ['payment_id' => $paymentId]),
            'failure_url'  => route('checkout.cancel'),
            'note'         => $paymentId,
        ]);

        if ($response->failed()) {
            Log::error('Fatora new-invoice API call failed: ' . $response->body());
            throw new RuntimeException('Fatora API returned an error.');
        }

        $data = $response->json();
        if (($data['status'] ?? '') !== 'success') {
            Log::error('Fatora new-invoice logic error: ' . json_encode($data));
            throw new RuntimeException('Fatora: ' . ($data['message'] ?? 'Unknown error'));
        }

        return [
            'gateway_ref'  => (string) ($data['invoice_key'] ?? ''),
            'redirect_url' => (string) ($data['payment_url'] ?? ''),
        ];
    }

    /**
     * Get payment status details from Fatora.
     */
    public function getPaymentStatus(string $invoiceKey): array
    {
        if (empty($this->apiKey)) {
            return ['status' => 'pending'];
        }

        $response = Http::withHeaders([
            'api_key' => $this->apiKey,
        ])->post('https://fatora.io/api/v2/get-invoice-status', [
            'invoice_key' => $invoiceKey,
        ]);

        if ($response->failed()) {
            Log::error('Fatora get-invoice-status failed: ' . $response->body());
            return ['status' => 'failed'];
        }

        $data = $response->json();
        if (($data['status'] ?? '') !== 'success') {
            Log::error('Fatora get-invoice-status logic error: ' . json_encode($data));
            return ['status' => 'failed'];
        }

        $invoiceStatus = $data['invoice_status'] ?? '';

        return [
            'status'      => strtolower($invoiceStatus) === 'paid' ? 'paid' : 'pending',
            'payment_id'  => $data['note'] ?? '',
            'invoice_id'  => $invoiceKey,
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
        $status = strtolower($data['invoice_status'] ?? '') === 'paid' ? 'paid' : 'pending';

        return [
            'event_type'  => $data['event'] ?? 'unknown',
            'gateway_ref' => (string) ($data['invoice_key'] ?? ''),
            'status'      => $status,
        ];
    }

    public function getGatewayName(): string
    {
        return 'fatora';
    }
}
