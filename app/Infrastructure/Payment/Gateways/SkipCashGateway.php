<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment\Gateways;

use App\Infrastructure\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SkipCash Gateway Implementation (Qatar).
 * Handles payments using SkipCash Qatar API (Apple Pay, QPay Debit, Visa/Mastercard).
 */
class SkipCashGateway implements PaymentGatewayInterface
{
    private string $clientId;
    private string $keyId;
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId  = (string) config('services.skipcash.client_id', '');
        $this->keyId     = (string) config('services.skipcash.key_id', '');
        $this->secretKey = (string) config('services.skipcash.secret_key', '');
        $isLive          = (bool) config('services.skipcash.live', false);

        $this->baseUrl = $isLive
            ? 'https://api.skipcash.app/api/v1'
            : 'https://skipcashtest.azurewebsites.net/api/v1';
    }

    public function createPaymentIntent(
        int $amountInSmallestUnit,
        string $currency,
        array $metadata = []
    ): array {
        $paymentId = (string) ($metadata['payment_id'] ?? '');
        $transactionId = 'SKP-' . $paymentId . '-' . Str::random(8);

        // Convert smallest unit (halala/dirhams) to decimal QAR
        $amount = number_format($amountInSmallestUnit / 100, 2, '.', '');

        if (empty($this->keyId) || empty($this->secretKey)) {
            // Mock mode for local testing / demoing when API credentials are not yet configured
            Log::info('SkipCash mock: createPaymentIntent', compact('amountInSmallestUnit', 'currency', 'transactionId'));
            $mockRef = 'skipcash_mock_' . uniqid();

            return [
                'gateway_ref'  => $mockRef,
                'redirect_url' => route('checkout.show', ['subscription' => $metadata['subscription_id'] ?? 1]),
            ];
        }

        $studentName  = $metadata['student_name'] ?? 'Student';
        $studentPhone = $metadata['student_phone'] ?? '+97400000000';
        $studentEmail = $metadata['student_email'] ?? 'student@almagd.edu';

        $payload = [
            'Uid'             => Str::uuid()->toString(),
            'KeyId'           => $this->keyId,
            'Amount'          => $amount,
            'FirstName'       => $studentName,
            'LastName'        => 'Al-Majd',
            'Phone'           => $studentPhone,
            'Email'           => $studentEmail,
            'TransactionId'   => $transactionId,
            'ReturnUrl'       => route('checkout.success', ['payment_id' => $paymentId]),
        ];

        // Generate SkipCash HMAC-SHA256 signature
        $dataToSign = "Uid={$payload['Uid']},KeyId={$this->keyId},Amount={$amount},FirstName={$payload['FirstName']},LastName={$payload['LastName']},Phone={$studentPhone},Email={$studentEmail},TransactionId={$transactionId}";
        $signature = base64_encode(hash_hmac('sha256', $dataToSign, $this->secretKey, true));

        $http = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Authorization' => $signature,
        ]);

        if (app()->isLocal()) {
            $http = $http->withoutVerifying();
        }

        $response = $http->post($this->baseUrl . '/payments', $payload);

        if ($response->failed()) {
            Log::error('SkipCash payment creation failed.', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new RuntimeException('تعذر إنشاء رابط الدفع عبر بوابة SkipCash القطرية.');
        }

        $result = $response->json();
        $payUrl = $result['resultObj']['payUrl'] ?? $result['payUrl'] ?? null;

        if (! $payUrl) {
            Log::error('SkipCash payment URL missing in response.', ['response' => $result]);
            throw new RuntimeException('فشل الحصول على رابط الدفع من بوابة SkipCash.');
        }

        return [
            'gateway_ref'  => $transactionId,
            'redirect_url' => (string) $payUrl,
        ];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->secretKey) || empty($signature)) {
            return false;
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return false;
        }

        $paymentId = $data['PaymentId'] ?? '';
        $amount    = $data['Amount'] ?? '';
        $statusId  = $data['StatusId'] ?? '';
        $transId   = $data['TransactionId'] ?? '';

        $stringToHash = "PaymentId={$paymentId},Amount={$amount},StatusId={$statusId},TransactionId={$transId}";
        $expectedSignature = base64_encode(hash_hmac('sha256', $stringToHash, $this->secretKey, true));

        return hash_equals($expectedSignature, $signature);
    }

    public function parseWebhookEvent(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];

        // In SkipCash, StatusId == 2 represents 'Paid'
        $statusId = (int) ($data['StatusId'] ?? 0);
        $status = ($statusId === 2) ? 'paid' : 'pending';

        return [
            'event_type'  => 'skipcash.payment',
            'gateway_ref' => (string) ($data['TransactionId'] ?? $data['PaymentId'] ?? ''),
            'status'      => $status,
        ];
    }

    public function getGatewayName(): string
    {
        return 'skipcash';
    }
}
