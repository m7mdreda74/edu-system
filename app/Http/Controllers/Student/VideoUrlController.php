<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Support\YouTubeUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * VideoUrlController — serves signed, time-limited video URLs.
 *
 * Direct video URLs are NEVER exposed to the client. Instead a temporary signed
 * URL is generated server-side that expires in 15 minutes, preventing
 * hotlinking. YouTube videos are proxied through our own server via
 * YoutubeProxyController to eliminate ads and enable full Plyr control.
 */
class VideoUrlController extends Controller
{
    private const URL_EXPIRY_MINUTES = 30;

    public function getSignedUrl(Request $request, int $materialId): JsonResponse
    {
        $material = GroupMaterial::with('unit.assignment')->findOrFail($materialId);

        /** @var User $user */
        $user = $request->user();

        Gate::authorize('watch', $material);

        if (! filled($material->video_path) && YouTubeUrl::videoId($material->video_url)) {
            // Build a signed proxy URL so the browser streams through our
            // server — no YouTube iframe, no ads, full Plyr control.
            $signedUrl = URL::temporarySignedRoute(
                'youtube.proxy.stream',
                now()->addMinutes(self::URL_EXPIRY_MINUTES),
                ['materialId' => $materialId, 'userId' => $user->id],
            );

            return response()->json([
                'provider'   => 'youtube_proxy',
                'signed_url' => $signedUrl,
            ]);
        }

        $signedUrl = URL::temporarySignedRoute(
            'video.stream',
            now()->addMinutes(self::URL_EXPIRY_MINUTES),
            ['materialId' => $materialId, 'userId' => $user->id],
        );

        return response()->json([
            'provider'   => 'file',
            'signed_url' => $signedUrl,
            'expires_in' => self::URL_EXPIRY_MINUTES * 60, // seconds
        ]);
    }

    /**
     * Streams the video (after signature verification by Laravel).
     * In production: proxy from the CDN using the real URL server-side.
     */
    public function stream(Request $request, int $materialId): Response
    {
        $material = GroupMaterial::with('unit.assignment')->findOrFail($materialId);
        $user = $request->user();

        abort_unless((int) $request->route('userId') === (int) $user->id, 403);
        Gate::authorize('watch', $material);

        abort_if(! filled($material->video_path) && ! filled($material->video_url), 404, 'لا يوجد فيديو لهذه المادة.');

        // The signed route is also bound to the authenticated user above; a
        // copied URL cannot be replayed by another account.
        if (filled($material->video_path)) {
            if (str_starts_with($material->video_path, 'https://')) {
                return redirect($this->blobUploads->downloadUrlFor($material->video_path, (int) $user->id));
            }

            return $this->serveLocalVideo($material->video_path);
        }

        return redirect($material->video_url);
    }

    private function serveLocalVideo(string $storedPath): Response
    {
        abort_unless(str_starts_with($storedPath, 'private://'), 404);

        $disk = Storage::disk('local');
        $relativePath = substr($storedPath, strlen('private://'));
        abort_unless($relativePath !== '' && $disk->exists($relativePath), 404);

        $root = realpath($disk->path(''));
        $file = realpath($disk->path($relativePath));

        abort_unless(
            $root
                && $file
                && is_file($file)
                && ($file === $root || str_starts_with($file, $root.DIRECTORY_SEPARATOR)),
            404,
        );

        $detectedMime = mime_content_type($file) ?: '';
        $extensionMime = match (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION))) {
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'm4v' => 'video/x-m4v',
            default => null,
        };
        $mime = str_starts_with($detectedMime, 'video/') ? $detectedMime : $extensionMime;
        abort_unless(is_string($mime), 404);

        $filename = preg_replace('/[^\pL\pN._ -]+/u', '_', basename($relativePath));
        $filename = is_string($filename) && $filename !== '' ? $filename : 'video';

        $response = response()->file($file, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
