<?php

declare(strict_types=1);

use App\Services\CurriculumBlobUpload;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config()->set([
        'app.key' => 'base64:test-signing-key',
        'services.vercel_blob.enabled' => true,
        'services.vercel_blob.store_id' => '1sxstfwepd7zn41q',
    ]);

    CarbonImmutable::setTestNow('2026-07-28 12:00:00 UTC');

    $this->uploads = new CurriculumBlobUpload;
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('is controlled by the configured enabled flag', function () {
    expect($this->uploads->enabled())->toBeTrue();

    config()->set('services.vercel_blob.enabled', 'false');

    expect($this->uploads->enabled())->toBeFalse();
});

it('builds a scoped prefix for every supported curriculum upload kind', function (string $kind) {
    expect($this->uploads->expectedPrefix(42, $kind, 99))
        ->toBe("curriculum/42/{$kind}/99/");
})->with([
    CurriculumBlobUpload::KIND_BOOKLET,
    CurriculumBlobUpload::KIND_HOMEWORK,
    CurriculumBlobUpload::KIND_EXAM,
]);

it('rejects unsupported upload kinds and invalid identifiers', function () {
    expect(fn () => $this->uploads->expectedPrefix(42, 'avatar', 99))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->uploads->expectedPrefix(0, CurriculumBlobUpload::KIND_BOOKLET, 99))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->uploads->expectedPrefix(42, CurriculumBlobUpload::KIND_BOOKLET, -1))
        ->toThrow(InvalidArgumentException::class);
});

it('issues a short lived hmac authorization with the complete upload scope', function () {
    $pathname = 'curriculum/42/booklet/99/lesson-notes.pdf';

    $issued = $this->uploads->issueAuthorization(
        42,
        CurriculumBlobUpload::KIND_BOOKLET,
        99,
        $pathname,
    );

    [$encodedPayload, $signature] = explode('.', $issued['authorization'], 2);
    $json = base64_decode(strtr($encodedPayload, '-_', '+/'), true);

    expect($json)->not->toBeFalse();

    $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($signature)
        ->toMatch('/^[a-f0-9]{64}$/')
        ->toBe(hash_hmac('sha256', $encodedPayload, 'base64:test-signing-key'))
        ->and($payload)->toBe([
            'pathname' => $pathname,
            'teacher_id' => 42,
            'kind' => CurriculumBlobUpload::KIND_BOOKLET,
            'target_id' => 99,
            'max_bytes' => 25 * 1024 * 1024,
            'expires_at_ms' => 1785240300000,
        ])
        ->and($issued)->toMatchArray($payload);
});

it('will not authorize a pathname outside the exact target prefix', function (string $pathname) {
    $authorize = fn () => $this->uploads->issueAuthorization(
        42,
        CurriculumBlobUpload::KIND_BOOKLET,
        99,
        $pathname,
    );

    expect($authorize)->toThrow(InvalidArgumentException::class);
})->with([
    'another teacher' => 'curriculum/41/booklet/99/notes.pdf',
    'another kind' => 'curriculum/42/homework/99/notes.pdf',
    'another target' => 'curriculum/42/booklet/100/notes.pdf',
    'empty suffix' => 'curriculum/42/booklet/99/',
    'parent segment' => 'curriculum/42/booklet/99/../notes.pdf',
    'empty segment' => 'curriculum/42/booklet/99/folder//notes.pdf',
]);

it('requires uploads to be enabled and app key to be present', function () {
    config()->set('services.vercel_blob.enabled', false);

    expect(fn () => $this->uploads->issueAuthorization(
        42,
        CurriculumBlobUpload::KIND_BOOKLET,
        99,
        'curriculum/42/booklet/99/notes.pdf',
    ))->toThrow(RuntimeException::class, 'disabled');

    config()->set([
        'services.vercel_blob.enabled' => true,
        'app.key' => '',
    ]);

    expect(fn () => $this->uploads->issueAuthorization(
        42,
        CurriculumBlobUpload::KIND_BOOKLET,
        99,
        'curriculum/42/booklet/99/notes.pdf',
    ))->toThrow(RuntimeException::class, 'APP_KEY');
});

it('accepts a completed public blob from the configured store and exact prefix', function () {
    $pathname = 'curriculum/42/booklet/99/lesson-notes-oYnXSVczoLa9.pdf';
    $url = "https://1sxstfwepd7zn41q.public.blob.vercel-storage.com/{$pathname}";

    expect($this->uploads->validateCompleted(
        $url,
        $pathname,
        42,
        CurriculumBlobUpload::KIND_BOOKLET,
        99,
    ))->toBe($url);
});

it('normalizes the store prefix used by OIDC project connections', function () {
    config()->set('services.vercel_blob.store_id', 'store_1sxstfwepd7zn41q');

    $pathname = 'curriculum/42/booklet/99/lesson-notes.pdf';
    $url = "https://1sxstfwepd7zn41q.public.blob.vercel-storage.com/{$pathname}";

    expect($this->uploads->validateCompleted(
        $url,
        $pathname,
        42,
        CurriculumBlobUpload::KIND_BOOKLET,
        99,
    ))->toBe($url);
});

it('requires a known store identity before accepting a completed upload', function () {
    config()->set([
        'services.vercel_blob.store_id' => null,
        'services.vercel_blob.token' => null,
    ]);

    $pathname = 'curriculum/42/exam/99/paper-oYnXSVczoLa9.pdf';
    $url = "https://another-store.public.blob.vercel-storage.com/{$pathname}";

    expect(fn () => $this->uploads->validateCompleted(
        $url,
        $pathname,
        42,
        CurriculumBlobUpload::KIND_EXAM,
        99,
    ))->toThrow(RuntimeException::class, 'store ID');
});

it('pins completed uploads to the store encoded in the read-write token', function () {
    config()->set([
        'services.vercel_blob.store_id' => null,
        'services.vercel_blob.token' => 'vercel_blob_rw_token-store_secret',
    ]);

    $pathname = 'curriculum/42/booklet/99/notes.pdf';
    $url = "https://token-store.public.blob.vercel-storage.com/{$pathname}";

    expect($this->uploads->validateCompleted(
        $url,
        $pathname,
        42,
        CurriculumBlobUpload::KIND_BOOKLET,
        99,
    ))->toBe($url);
});

it('rejects an untrusted or mismatched completed blob', function (
    string $url,
    string $pathname,
) {
    expect(fn () => $this->uploads->validateCompleted(
        $url,
        $pathname,
        42,
        CurriculumBlobUpload::KIND_BOOKLET,
        99,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'wrong store' => [
        'https://wrong-store.public.blob.vercel-storage.com/curriculum/42/booklet/99/notes.pdf',
        'curriculum/42/booklet/99/notes.pdf',
    ],
    'private blob' => [
        'https://1sxstfwepd7zn41q.private.blob.vercel-storage.com/curriculum/42/booklet/99/notes.pdf',
        'curriculum/42/booklet/99/notes.pdf',
    ],
    'lookalike host' => [
        'https://1sxstfwepd7zn41q.public.blob.vercel-storage.com.attacker.test/curriculum/42/booklet/99/notes.pdf',
        'curriculum/42/booklet/99/notes.pdf',
    ],
    'plain http' => [
        'http://1sxstfwepd7zn41q.public.blob.vercel-storage.com/curriculum/42/booklet/99/notes.pdf',
        'curriculum/42/booklet/99/notes.pdf',
    ],
    'url path mismatch' => [
        'https://1sxstfwepd7zn41q.public.blob.vercel-storage.com/curriculum/42/booklet/99/other.pdf',
        'curriculum/42/booklet/99/notes.pdf',
    ],
    'wrong target prefix' => [
        'https://1sxstfwepd7zn41q.public.blob.vercel-storage.com/curriculum/42/booklet/100/notes.pdf',
        'curriculum/42/booklet/100/notes.pdf',
    ],
    'double slash path' => [
        'https://1sxstfwepd7zn41q.public.blob.vercel-storage.com//curriculum/42/booklet/99/notes.pdf',
        'curriculum/42/booklet/99/notes.pdf',
    ],
]);

it('accepts a public Blob download URL whose query does not change its pathname', function () {
    $pathname = 'curriculum/42/booklet/99/notes.pdf';
    $url = "https://1sxstfwepd7zn41q.public.blob.vercel-storage.com/{$pathname}?download=1";

    expect($this->uploads->validateCompleted(
        $url,
        $pathname,
        42,
        CurriculumBlobUpload::KIND_BOOKLET,
        99,
    ))->toBe($url);
});
