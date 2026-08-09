<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve a locally stored file only after resolving it inside its disk root.
 *
 * Database paths are application data, not trusted filesystem paths. Keeping
 * this check in one place prevents an accidental ../ path from becoming a
 * file disclosure when an admin views an uploaded receipt.
 */
final class SecureStoredFileResponse
{
    public function fromLocal(?string $storedPath): Response
    {
        return $this->serve(Storage::disk('local'), $storedPath);
    }

    public function fromPublicStoragePath(?string $storedPath): Response
    {
        abort_unless(is_string($storedPath) && str_starts_with($storedPath, '/storage/'), 404);

        return $this->serve(
            Storage::disk('public'),
            substr($storedPath, strlen('/storage/')),
        );
    }

    private function serve(FilesystemAdapter $disk, ?string $relativePath): Response
    {
        abort_unless(is_string($relativePath) && $relativePath !== '', 404);

        $root = realpath($disk->path(''));
        $file = realpath($disk->path($relativePath));

        abort_unless(
            $root
                && $file
                && is_file($file)
                && ($file === $root || str_starts_with($file, $root.DIRECTORY_SEPARATOR)),
            404,
        );

        $mime = mime_content_type($file) ?: 'application/octet-stream';
        $filename = $this->safeFilename($relativePath);
        $headers = [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (in_array($mime, ['image/gif', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            $headers['Content-Disposition'] = 'inline; filename="'.$filename.'"';
            $response = response()->file($file, $headers);
        } else {
            $response = response()->download($file, $filename, $headers);
        }

        // Symfony normalizes the order of cache directives when preparing a
        // BinaryFileResponse, so set the final private value after creation.
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function safeFilename(string $path): string
    {
        $filename = preg_replace('/[^\pL\pN._ -]+/u', '_', basename($path));

        return is_string($filename) && $filename !== '' ? $filename : 'download';
    }
}
