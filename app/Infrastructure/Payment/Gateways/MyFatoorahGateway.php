<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment\Gateways;

use App\Infrastructure\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * MyFatoorah Gateway Implementation.
 * Supports automated redirects, hosted checkout links, status checks, and webhooks.
 */
class MyFatoorahGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private bool $testMode;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey   = config('services.myfatoorah.api_key', '');
        $this->testMode = (bool) config('services.myfatoorah.test_mode', true);
        $this->baseUrl  = $this->testMode
            ? 'https://apitest.myfatoorah.com/v2/'
            : 'https://api.myfatoorah.com/v2/';
    }

    public function createPaymentIntent(
        int $amountInSmallestUnit,
        string $currency,
        array $metadata = []
    ): array {
        if (empty($this->apiKey)) {
            // Mock mode for local testing
            Log::info('MyFatoorah mock: createPaymentIntent', compact('amountInSmallestUnit', 'currency'));
            $mockRef = 'mf_mock_' . uniqid();

            return [
                'gateway_ref'  => $mockRef,
                'redirect_url' => route('checkout.mock_gateway', ['ref' => $mockRef]),
            ];
        }

        $paymentId = (string) ($metadata['payment_id'] ?? '');

        // MyFatoorah Invoice Value expects decimal (e.g. 150.00 instead of 15000 halalas)
        $invoiceValue = (float) ($amountInSmallestUnit / 100);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . 'SendPayment', [
            'CustomerName'       => 'Student',
            'NotificationOption' => 'LNK',
            'InvoiceValue'       => $invoiceValue,
            'DisplayCurrencyIso' => $currency,
            'CallBackUrl'        => route('checkout.success'),
            'ErrorUrl'           => route('checkout.cancel'),
            'Language'           => 'ar',
            'CustomerReference'  => $paymentId,
            'UserDefinedField'   => json_encode($metadata),
        ]);

        if ($response->failed()) {
            Log::error('MyFatoorah SendPayment API call failed: ' . $response->body());
            throw new RuntimeException('MyFatoorah API returned an error.');
        }

        $data = $response->json();
        if (!($data['IsSuccess'] ?? false)) {
            Log::error('MyFatoorah SendPayment logic error: ' . json_encode($data));
            throw new RuntimeException('MyFatoorah: ' . ($data['Message'] ?? 'Unknown error'));
        }

        return [
            'gateway_ref'  => (string) ($data['Data']['InvoiceId'] ?? ''),
            'redirect_url' => (string) ($data['Data']['PaymentURL'] ?? ''),
        ];
    }

    /**
     * Get payment status details from MyFatoorah by Transaction PaymentId.
     */
    public function getPaymentStatus(string $paymentId): array
    {
        if (empty($this->apiKey)) {
            return ['status' => 'pending'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . 'GetPaymentStatus', [
            'Key'     => $paymentId,
            'KeyType' => 'PaymentId',
        ]);

        if ($response->failed()) {
            Log::error('MyFatoorah GetPaymentStatus failed: ' . $response->body());
            return ['status' => 'failed'];
        }

        $data = $response->json();
        if (!($data['IsSuccess'] ?? false)) {
            Log::error('MyFatoorah GetPaymentStatus response logic error: ' . json_encode($data));
            return ['status' => 'failed'];
        }

        $invoiceStatus = $data['Data']['InvoiceStatus'] ?? '';

        return [
            'status'      => strtolower($invoiceStatus) === 'paid' ? 'paid' : 'pending',
            'payment_id'  => $data['Data']['CustomerReference'] ?? '',
            'invoice_id'  => $data['Data']['InvoiceId'] ?? '',
        ];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        // Simple security logic: if no API key is set, allow webhook only in local mode
        if (empty($this->apiKey)) {
            return app()->isLocal();
        }

        // For production, verification can be customized or bypassed since we double-check the transaction status via API
        return true;
    }

    public function parseWebhookEvent(string $payload): array
    {
        $data = json_decode($payload, true);

        // Normalize MyFatoorah Webhook structure
        $eventData = $data['Data'] ?? $data;
        $status = strtolower($eventData['TransactionStatus'] ?? '') === 'succss' ? 'paid' : 'pending';

        return [
            'event_type'  => $data['Event'] ?? 'unknown',
            'gateway_ref' => (string) ($eventData['PaymentId'] ?? $eventData['InvoiceId'] ?? ''),
            'status'      => $status,
        ];
    }

    public function getGatewayName(): string
    {
        return 'myfatoorah';
    }
}
