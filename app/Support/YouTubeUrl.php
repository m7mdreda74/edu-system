<?php

declare(strict_types=1);

namespace App\Support;

final class YouTubeUrl
{
    /** Return the canonical eleven-character video id, or null for non-YouTube URLs. */
    public static function videoId(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $candidate = null;

        if ($host === 'youtu.be') {
            $candidate = explode('/', $path)[0] ?? null;
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
            if ($path === 'watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $candidate = $query['v'] ?? null;
            } elseif (preg_match('#^(?:embed|shorts|live)/([^/]+)#', $path, $matches) === 1) {
                $candidate = $matches[1];
            }
        }

        return is_string($candidate) && preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1
            ? $candidate
            : null;
    }

    public static function isValid(?string $url): bool
    {
        return self::videoId($url) !== null;
    }
}
