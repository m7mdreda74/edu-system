<?php

declare(strict_types=1);

namespace App\Infrastructure\Video;

interface VideoProviderInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    /** @return array{upload_url:string, upload_id:string, protocol:string, expires_at:string} */
    public function createUpload(array $options): array;

    /** @return array<string, mixed> */
    public function videoDetails(string $identifier): array;

    /** @return array{url:string, expires_at:string} */
    public function createPlaybackAuthorization(string $identifier, int $expiresAt): array;

    public function deleteVideo(string $identifier): void;

    public function verifyWebhook(string $rawBody, ?string $signature): bool;

    /** @return array<string, mixed> */
    public function normalizeWebhook(array $payload): array;
}
