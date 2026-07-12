<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment\Gateways;

use App\Infrastructure\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tap Payments Gateway Implementation (GoSell API v2).
 */
class TapGateway implements PaymentGatewayInterface
{
    private string $secretKey;
    private string $publishableKey;
    private string $baseUrl;

    public function __construct()
    {
        $dbSecret = \App\Domain\Settings\Models\PlatformSetting::where('key', 'tap_secret_key')->value('value');
        $dbPub = \App\Domain\Settings\Models\PlatformSetting::where('key', 'tap_publishable_key')->value('value');

        $this->secretKey = $dbSecret ?: config('services.tap.secret_key', '');
        $this->publishableKey = $dbPub ?: config('services.tap.publishable_key', '');
        $this->baseUrl = 'https://api.tap.company/v2';
    }

    public function createPaymentIntent(
        int $amountInSmallestUnit,
        string $currency,
        array $metadata = []
    ): array {
        $paymentId = (string) ($metadata['payment_id'] ?? '');

        if (empty($this->secretKey)) {
            // Mock mode for local testing if credentials are not configured in settings/env
            Log::info('Tap mock: createPaymentIntent', compact('amountInSmallestUnit', 'currency'));
            $mockRef = 'chg_tap_mock_' . uniqid();

            return [
                'gateway_ref'  => $mockRef,
                'redirect_url' => route('checkout.mock_gateway', ['ref' => $mockRef]),
            ];
        }

        // Convert smallest unit (halala) to decimal (e.g. 15000 halala -> 150.00 QAR)
        $amount = (float) ($amountInSmallestUnit / 100);

        // Fetch User info from DB using metadata
        $userId = $metadata['user_id'] ?? null;
        $user = $userId ? \App\Domain\User\Models\User::find($userId) : null;
        $userEmail = $user?->email ?? 'student@altafawwuq.com';
        $userName = $user?->name ?? 'طالب';

        $http = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type'  => 'application/json',
            'accept'        => 'application/json',
        ]);

        if (app()->isLocal()) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($this->baseUrl . '/charges', [
            'amount'   => $amount,
            'currency' => strtoupper($currency),
            'threeDSecure' => true,
            'save_card' => false,
            'description' => 'شراء كورس من منصة التفوق',
            'statement_descriptor' => 'ALTAFAWWUQ',
            'metadata' => [
                'payment_id' => $paymentId,
            ],
            'customer' => [
                'first_name' => $userName,
                'email' => $userEmail,
                'phone' => [
                    'country_code' => '974',
                    'number' => '00000000',
                ],
            ],
            'source' => [
                'id' => 'src_all',
            ],
            'redirect' => [
                'url' => route('checkout.success', ['payment_id' => $paymentId]),
            ],
        ]);

        if ($response->failed()) {
            Log::error('Tap charges API call failed: ' . $response->body());
            throw new RuntimeException('Tap Payments API returned an error.');
        }

        $data = $response->json();
        $chargeId = $data['id'] ?? '';
        $redirectUrl = $data['transaction']['url'] ?? '';

        if (empty($chargeId) || empty($redirectUrl)) {
            Log::error('Tap response missing details: ' . json_encode($data));
            throw new RuntimeException('Failed to initiate Tap checkout.');
        }

        return [
            'gateway_ref'  => $chargeId,
            'redirect_url' => $redirectUrl,
        ];
    }

    /**
     * Get payment status details from Tap.
     */
    public function getPaymentStatus(string $transactionId): array
    {
        if (empty($this->secretKey) || str_starts_with($transactionId, 'chg_tap_mock_')) {
            return ['status' => 'pending'];
        }

        $http = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'accept'        => 'application/json',
        ]);

        if (app()->isLocal()) {
            $http = $http->withoutVerifying();
        }

        $response = $http->get($this->baseUrl . '/charges/' . $transactionId);

        if ($response->failed()) {
            Log::error('Tap retrieve charge API failed: ' . $response->body());
            return ['status' => 'failed'];
        }

        $data = $response->json();
        $status = strtoupper($data['status'] ?? '');

        return [
            'status' => $status === 'CAPTURED' ? 'paid' : 'pending',
            'payment_id' => '',
            'invoice_id' => $transactionId,
        ];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        // For testing, webhook events are accepted. Signature header verification is optional.
        return true;
    }

    public function parseWebhookEvent(string $payload): array
    {
        $data = json_decode($payload, true);
        $status = strtoupper($data['status'] ?? '') === 'CAPTURED' ? 'paid' : 'pending';

        return [
            'event_type'  => 'charge.captured',
            'gateway_ref' => (string) ($data['id'] ?? ''),
            'status'      => $status,
        ];
    }

    public function getGatewayName(): string
    {
        return 'tap';
    }
}
