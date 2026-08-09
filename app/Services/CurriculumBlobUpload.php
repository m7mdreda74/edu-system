<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Issues upload authorizations and validates completed private Vercel Blob uploads.
 *
 * The authorization format is intentionally language-neutral:
 *
 *     base64url(JSON payload).hex(HMAC-SHA256(base64url payload, APP_KEY))
 *
 * This lets the JavaScript upload function verify the authorization without
 * exposing any Vercel Blob credentials to the browser.
 */
final class CurriculumBlobUpload
{
    public const KIND_BOOKLET = 'booklet';

    public const KIND_HOMEWORK = 'homework';

    public const KIND_EXAM = 'exam';

    public const MAX_BYTES = 25 * 1024 * 1024;

    public const AUTHORIZATION_TTL_SECONDS = 300;

    public const DOWNLOAD_TTL_SECONDS = 900;

    private const PUBLIC_BLOB_HOST_SUFFIX = '.public.blob.vercel-storage.com';

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = [
        'doc',
        'docx',
        'jpeg',
        'jpg',
        'odt',
        'pdf',
        'png',
        'pptx',
        'zip',
    ];

    /** @var list<string> */
    private const ALLOWED_CONTENT_TYPES = [
        'application/msword',
        'application/pdf',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'image/jpeg',
        'image/png',
    ];

    /** @var list<string> */
    private const KINDS = [
        self::KIND_BOOKLET,
        self::KIND_HOMEWORK,
        self::KIND_EXAM,
    ];

    public function enabled(): bool
    {
        return filter_var(
            config('services.vercel_blob.enabled', false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function expectedPrefix(int $teacherId, string $kind, int $targetId): string
    {
        $this->assertPositiveId($teacherId, 'teacher');
        $this->assertKind($kind);
        $this->assertPositiveId($targetId, 'target');

        return "curriculum/{$teacherId}/{$kind}/{$targetId}/";
    }

    /**
     * @return array{
     *     authorization: string,
     *     pathname: string,
     *     teacher_id: int,
     *     kind: string,
     *     target_id: int,
     *     max_bytes: int,
     *     expires_at_ms: int
     * }
     */
    public function issueAuthorization(
        int $teacherId,
        string $kind,
        int $targetId,
        string $pathname,
    ): array {
        $this->assertEnabled();
        $this->assertExpectedPathname($pathname, $teacherId, $kind, $targetId);

        $payload = [
            'pathname' => $pathname,
            'teacher_id' => $teacherId,
            'kind' => $kind,
            'target_id' => $targetId,
            'max_bytes' => self::MAX_BYTES,
            'allowed_content_types' => self::ALLOWED_CONTENT_TYPES,
            'expires_at_ms' => now()->getTimestampMs() + (self::AUTHORIZATION_TTL_SECONDS * 1000),
        ];

        $encodedPayload = $this->base64UrlEncode(json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
        $signature = hash_hmac('sha256', $encodedPayload, $this->signingKey());

        return [
            'authorization' => "{$encodedPayload}.{$signature}",
            ...$payload,
        ];
    }

    /**
     * Validate the immutable Blob URL returned after a successful client upload.
     *
     * The returned string is safe to persist as the curriculum attachment URL.
     */
    public function validateCompleted(
        string $url,
        string $pathname,
        int $teacherId,
        string $kind,
        int $targetId,
    ): string {
        $this->assertEnabled();
        $this->assertExpectedPathname($pathname, $teacherId, $kind, $targetId);

        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'], $parts['path'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            throw new InvalidArgumentException('The completed Blob URL is invalid.');
        }

        $host = strtolower($parts['host']);
        $this->assertPublicBlobHost($host);

        if (! str_starts_with($parts['path'], '/') || str_starts_with($parts['path'], '//')) {
            throw new InvalidArgumentException('The completed Blob URL has an invalid path.');
        }

        $urlPathname = rawurldecode(substr($parts['path'], 1));

        if ($urlPathname !== $pathname) {
            throw new InvalidArgumentException('The completed Blob URL does not match its pathname.');
        }

        return $url;
    }

    /**
     * Turn a stored private Blob URL into a short-lived application URL. The
     * Blob itself is never exposed to the browser, and the Node handler only
     * accepts a token signed with the server APP_KEY.
     */
    public function downloadUrlFor(string $url, int $userId): string
    {
        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'], $parts['path'])
        ) {
            throw new InvalidArgumentException('The stored Blob URL is invalid.');
        }

        $this->assertPublicBlobHost(strtolower($parts['host']));
        $pathname = rawurldecode(ltrim((string) $parts['path'], '/'));
        if (! str_starts_with($pathname, 'curriculum/')) {
            throw new InvalidArgumentException('The stored Blob path is outside curriculum storage.');
        }
        $this->assertSafePathname($pathname);

        $payload = [
            'pathname' => $pathname,
            'user_id' => $userId,
            'expires_at_ms' => now()->getTimestampMs() + (self::DOWNLOAD_TTL_SECONDS * 1000),
        ];
        $encodedPayload = $this->base64UrlEncode(json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
        $signature = hash_hmac('sha256', $encodedPayload, $this->signingKey());
        $token = "{$encodedPayload}.{$signature}";
        $handle = (string) config('services.vercel_blob.download_handle_url', '/api/blob-download');

        return rtrim($handle, '?').'?' . http_build_query(['token' => $token], '', '&', PHP_QUERY_RFC3986);
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Vercel Blob curriculum uploads are disabled.');
        }
    }

    private function assertExpectedPathname(
        string $pathname,
        int $teacherId,
        string $kind,
        int $targetId,
    ): void {
        $prefix = $this->expectedPrefix($teacherId, $kind, $targetId);

        if (
            ! str_starts_with($pathname, $prefix)
            || $pathname === $prefix
            || strlen($pathname) > 950
        ) {
            throw new InvalidArgumentException('The Blob pathname is outside the expected curriculum prefix.');
        }

        $this->assertSafePathname($pathname);
    }

    private function assertSafePathname(string $pathname): void
    {
        if (
            str_starts_with($pathname, '/')
            || str_ends_with($pathname, '/')
            || str_contains($pathname, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $pathname) === 1
        ) {
            throw new InvalidArgumentException('The Blob pathname is invalid.');
        }

        foreach (explode('/', $pathname) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('The Blob pathname contains an invalid segment.');
            }
        }

        $extension = strtolower((string) pathinfo($pathname, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('The Blob file type is not allowed.');
        }
    }

    private function assertPublicBlobHost(string $host): void
    {
        $storeId = $this->configuredStoreId();

        if ($storeId === '') {
            throw new RuntimeException('A Vercel Blob store ID is required to validate completed uploads.');
        }

        $storeId = preg_replace('/^store_/', '', $storeId) ?? $storeId;

        if (preg_match('/^[a-z0-9-]+$/i', $storeId) !== 1) {
            throw new RuntimeException('The configured Vercel Blob store ID is invalid.');
        }

        if ($host !== strtolower($storeId).self::PUBLIC_BLOB_HOST_SUFFIX) {
            throw new InvalidArgumentException('The completed Blob URL belongs to another store.');
        }
    }

    private function configuredStoreId(): string
    {
        $configured = trim((string) config('services.vercel_blob.store_id', ''));

        if ($configured !== '') {
            return $configured;
        }

        // Read-write tokens encode the store ID as the fourth underscore-
        // separated segment (`vercel_blob_rw_<store-id>_...`).
        $token = trim((string) config('services.vercel_blob.token', ''));
        $segments = explode('_', $token);

        return isset($segments[3]) && $segments[3] !== '' ? $segments[3] : '';
    }

    private function assertKind(string $kind): void
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException('The curriculum upload kind is invalid.');
        }
    }

    private function assertPositiveId(int $id, string $name): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException("The {$name} ID must be positive.");
        }
    }

    private function signingKey(): string
    {
        $key = config('app.key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('APP_KEY is required to authorize curriculum uploads.');
        }

        // Sign with the literal APP_KEY so a Node verifier can use process.env.APP_KEY.
        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
