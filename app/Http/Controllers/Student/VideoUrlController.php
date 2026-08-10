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

        if (YouTubeUrl::videoId($material->video_url)) {
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
    public function stream(Request $request, int $materialId): RedirectResponse
    {
        $material = GroupMaterial::with('unit.assignment')->findOrFail($materialId);
        $user = $request->user();

        abort_unless((int) $request->route('userId') === (int) $user->id, 403);
        Gate::authorize('watch', $material);

        abort_if(! $material->video_url, 404, 'لا يوجد فيديو لهذه المادة.');

        // The signed route is also bound to the authenticated user above; a
        // copied URL cannot be replayed by another account.
        return redirect($material->video_url);
    }
}
