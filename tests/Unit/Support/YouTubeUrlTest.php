<?php

declare(strict_types=1);

use App\Support\YouTubeUrl;

it('extracts a canonical id from supported YouTube URL forms', function (string $url): void {
    expect(YouTubeUrl::videoId($url))->toBe('dQw4w9WgXcQ');
})->with([
    'https://youtu.be/dQw4w9WgXcQ',
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=10s',
    'https://youtube-nocookie.com/embed/dQw4w9WgXcQ',
    'https://www.youtube.com/shorts/dQw4w9WgXcQ',
    'https://www.youtube.com/live/dQw4w9WgXcQ',
]);

it('rejects malformed, non-YouTube, and unsafe video URLs', function (?string $url): void {
    expect(YouTubeUrl::videoId($url))->toBeNull()
        ->and(YouTubeUrl::isValid($url))->toBeFalse();
})->with([
    null,
    '',
    'not a url',
    'https://example.com/watch?v=dQw4w9WgXcQ',
    'https://www.youtube.com/watch?v=too-short',
    'https://www.youtube.com/watch?v=dQw4w9WgC$',
    'https://www.youtube.com/embed/dQw4w9WgXcQ/extra',
    'https://youtu.be/dQw4w9WgXcQ/extra',
    'javascript://youtube.com/watch?v=dQw4w9WgXcQ',
]);
