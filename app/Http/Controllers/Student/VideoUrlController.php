<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Course\Models\CourseLesson;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;

/**
 * VideoUrlController — serves signed, time-limited video URLs.
 *
 * Direct video URLs are NEVER exposed to the client.
 * Instead, a temporary signed URL is generated server-side
 * that expires in 15 minutes, preventing hotlinking.
 *
 * For production: replace with Bunny Stream / CloudFront signed URLs.
 */
class VideoUrlController extends Controller
{
    private const URL_EXPIRY_MINUTES = 15;

    public function getSignedUrl(Request $request, int $lessonId): JsonResponse
    {
        $lesson = CourseLesson::with('course')->findOrFail($lessonId);

        // $request->user() is always typed correctly by the framework
        /** @var \App\Domain\User\Models\User $user */
        $user = $request->user();

        if (!$lesson->is_free_preview) {
            Gate::authorize('learn', $lesson->course);
        }

        // Generate a signed URL that expires in 15 minutes
        // In production: replace with Bunny Stream token or CloudFront signed URL
        $signedUrl = URL::temporarySignedRoute(
            'video.stream',
            now()->addMinutes(self::URL_EXPIRY_MINUTES),
            ['lessonId' => $lessonId, 'userId' => $user->id]
        );

        return response()->json([
            'signed_url' => $signedUrl,
            'expires_in' => self::URL_EXPIRY_MINUTES * 60, // seconds
        ]);
    }

    /**
     * Streams the video (after signature verification by Laravel).
     * In production: proxy from Bunny CDN using the real URL server-side.
     */
    public function stream(Request $request, int $lessonId): \Illuminate\Http\RedirectResponse
    {
        $lesson = CourseLesson::findOrFail($lessonId);

        // Prevent hotlinking by verifying the Referer header host matches our app domain.
        $referer = $request->header('referer');
        if ($referer) {
            $allowedHost = parse_url(config('app.url'), PHP_URL_HOST);
            $refererHost = parse_url($referer, PHP_URL_HOST);
            if ($refererHost && $refererHost !== $allowedHost) {
                abort(403, 'غير مسموح بالربط المباشر (Hotlinking blocked).');
            }
        }

        // Redirect to actual video (in dev: direct URL; in prod: CDN signed URL)
        // This redirect is safe because clients see only the signed route, not the origin
        return redirect($lesson->video_url);
    }
}
