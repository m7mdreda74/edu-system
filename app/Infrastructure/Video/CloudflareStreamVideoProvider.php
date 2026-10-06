<?php

declare(strict_types=1);

namespace App\Infrastructure\Video;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class CloudflareStreamVideoProvider implements VideoProviderInterface
{
    private const BASIC_UPLOAD_LIMIT = 200 * 1024 * 1024;

    public function name(): string
    {
        return 'cloudflare_stream';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.cloudflare_stream.account_id'))
            && filled(config('services.cloudflare_stream.api_token'))
            && filled(config('services.cloudflare_stream.playback_host'));
    }

    public function createUpload(array $options): array
    {
        $this->assertConfigured();

        $fileSize = (int) ($options['file_size'] ?? 0);
        $expiresAt = now()->addSeconds((int) config('services.cloudflare_stream.upload_ttl', 3600));
        $maxDuration = (int) config('services.cloudflare_stream.max_duration_seconds', 14400);
        $metadata = [
            'name' => Str::limit((string) ($options['file_name'] ?? 'lesson-video'), 180, ''),
            'lesson_id' => (string) ($options['lesson_id'] ?? ''),
            'lesson_video_id' => (string) ($options['lesson_video_id'] ?? ''),
        ];

        if ($fileSize <= self::BASIC_UPLOAD_LIMIT) {
            $response = $this->api()->post('stream/direct_upload', [
                'maxDurationSeconds' => $maxDuration,
                'creator' => (string) ($options['teacher_id'] ?? ''),
                'expiry' => $expiresAt->toIso8601String(),
                'meta' => $metadata,
                'requireSignedURLs' => true,
            ])->throw()->json();

            $result = $response['result'] ?? [];

            if (! filled($result['uploadURL'] ?? null) || ! filled($result['uid'] ?? null)) {
                throw new RuntimeException('Cloudflare did not return a direct upload endpoint.');
            }

            return [
                'upload_url' => (string) $result['uploadURL'],
                'upload_id' => (string) $result['uid'],
                'protocol' => 'basic',
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        }

        // Cloudflare requires TUS for files over 200 MB. The upload URL is
        // returned in Location and is safe to expose only to this one browser.
        $uploadMetadata = implode(',', [
            'maxDurationSeconds '.base64_encode((string) $maxDuration),
            'requiresignedurls',
            'expiry '.base64_encode($expiresAt->toIso8601String()),
            'name '.base64_encode((string) ($options['file_name'] ?? 'lesson-video')),
        ]);

        $response = $this->api()->withHeaders([
            'Tus-Resumable' => '1.0.0',
            'Upload-Length' => (string) $fileSize,
            'Upload-Metadata' => $uploadMetadata,
        ])->send('POST', 'stream?direct_user=true');

        $response->throw();
        $uploadUrl = (string) $response->header('Location');
        $uploadId = trim((string) parse_url($uploadUrl, PHP_URL_PATH), '/');
        $uploadId = (string) str($uploadId)->afterLast('/');

        if ($uploadUrl === '' || $uploadId === '') {
            throw new RuntimeException('Cloudflare did not return a resumable upload endpoint.');
        }

        return [
            'upload_url' => $uploadUrl,
            'upload_id' => $uploadId,
            'protocol' => 'tus',
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function videoDetails(string $identifier): array
    {
        $this->assertConfigured();

        return $this->api()->get('stream/'.rawurlencode($identifier))->throw()->json('result', []);
    }

    public function createPlaybackAuthorization(string $identifier, int $expiresAt): array
    {
        $this->assertConfigured();

        $token = $this->api()->post('stream/'.rawurlencode($identifier).'/token', [
            'exp' => $expiresAt,
            'downloadable' => false,
        ])->throw()->json('result.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Cloudflare did not return a playback token.');
        }

        return [
            'url' => rtrim((string) config('services.cloudflare_stream.playback_host'), '/')
                .'/'.$token.'/manifest/video.m3u8',
            'expires_at' => now()->setTimestamp($expiresAt)->toIso8601String(),
        ];
    }

    public function deleteVideo(string $identifier): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $this->api()->delete('stream/'.rawurlencode($identifier))->throw();
    }

    public function verifyWebhook(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.cloudflare_stream.webhook_secret');
        if ($secret === '' || ! is_string($signature)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[$key] = $value;
            }
        }

        $timestamp = (int) ($parts['time'] ?? 0);
        $provided = (string) ($parts['sig1'] ?? '');
        $tolerance = (int) config('services.cloudflare_stream.webhook_tolerance', 300);

        if ($timestamp < 1 || $provided === '' || abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $provided);
    }

    public function normalizeWebhook(array $payload): array
    {
        $status = is_array($payload['status'] ?? null) ? $payload['status'] : [];
        $state = (string) ($status['state'] ?? '');
        $readyToStream = (bool) ($payload['readyToStream'] ?? false);
        $normalizedStatus = match (true) {
            $state === 'error' => 'failed',
            $state === 'ready' && $readyToStream => 'ready',
            in_array($state, ['pendingupload'], true) => 'pending_upload',
            default => 'processing',
        };

        return [
            'provider' => $this->name(),
            'provider_upload_id' => (string) ($payload['uid'] ?? ''),
            'provider_asset_id' => (string) ($payload['uid'] ?? ''),
            'status' => $normalizedStatus,
            'duration_seconds' => isset($payload['duration']) && is_numeric($payload['duration'])
                ? max(0, (int) round((float) $payload['duration']))
                : null,
            'thumbnail_reference' => is_string($payload['thumbnail'] ?? null) ? $payload['thumbnail'] : null,
            'playback_reference' => $this->playbackReference((string) ($payload['uid'] ?? '')),
            'failure_code' => is_string($status['errReasonCode'] ?? null) ? Str::limit($status['errReasonCode'], 100, '') : null,
            'failure_message' => is_string($status['errReasonText'] ?? null) ? Str::limit($status['errReasonText'], 1000) : null,
        ];
    }

    private function playbackReference(string $identifier): ?string
    {
        return $identifier === ''
            ? null
            : rtrim((string) config('services.cloudflare_stream.playback_host'), '/').'/'.$identifier.'/manifest/video.m3u8';
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudflare Stream is not configured.');
        }
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.cloudflare_stream.api_base_url'), '/'))
            ->withToken((string) config('services.cloudflare_stream.api_token'))
            ->acceptJson()
            ->timeout((int) config('services.cloudflare_stream.timeout', 20));
    }
}
