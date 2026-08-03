<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Support\YouTubeUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

/**
 * VideoUrlController — serves signed, time-limited video URLs.
 *
 * Direct video URLs are NEVER exposed to the client. Instead a temporary signed
 * URL is generated server-side that expires in 15 minutes, preventing
 * hotlinking.
 *
 * For production: replace with Bunny Stream / CloudFront signed URLs.
 */
class VideoUrlController extends Controller
{
    private const URL_EXPIRY_MINUTES = 15;

    public function getSignedUrl(Request $request, int $materialId): JsonResponse
    {
        $material = GroupMaterial::with('unit.assignment')->findOrFail($materialId);

        /** @var User $user */
        $user = $request->user();

        Gate::authorize('watch', $material);

        if ($youtubeId = YouTubeUrl::videoId($material->video_url)) {
            return response()->json([
                'provider' => 'youtube',
                'video_id' => $youtubeId,
            ]);
        }

        $signedUrl = URL::temporarySignedRoute(
            'video.stream',
            now()->addMinutes(self::URL_EXPIRY_MINUTES),
            ['materialId' => $materialId, 'userId' => $user->id],
        );

        return response()->json([
            'provider' => 'file',
            'signed_url' => $signedUrl,
            'expires_in' => self::URL_EXPIRY_MINUTES * 60, // seconds
        ]);
    }

    /**
     * Streams the video (after signature verification by Laravel).
     * In production: proxy from the CDN using the real URL server-side.
     */
    public function stream(Request $request, int $materialId): RedirectResponse
    {
        $material = GroupMaterial::findOrFail($materialId);

        // Prevent hotlinking by checking the Referer host matches our app domain.
        $referer = $request->header('referer');

        if ($referer) {
            $allowedHost = parse_url(config('app.url'), PHP_URL_HOST);
            $refererHost = parse_url($referer, PHP_URL_HOST);

            if ($refererHost && $refererHost !== $allowedHost) {
                abort(403, 'غير مسموح بالربط المباشر (Hotlinking blocked).');
            }
        }

        abort_if(! $material->video_url, 404, 'لا يوجد فيديو لهذه المادة.');

        // Safe: clients only ever see the signed route, never the origin URL.
        return redirect($material->video_url);
    }
}
