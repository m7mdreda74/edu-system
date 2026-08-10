<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Domain\Learning\Models\GroupMaterial;
use App\Http\Controllers\Controller;
use App\Support\YouTubeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use YouTube\YouTubeDownloader;
use YouTube\Exception\YouTubeException;

class YoutubeProxyController extends Controller
{
    /**
     * Proxy a YouTube video stream through our server.
     *
     * This allows playback inside the platform's own HTML5 player (Plyr)
     * without ads and with full playback control. The stream is fetched
     * server-side and piped to the student in 128 KB chunks so that the
     * browser can seek normally via HTTP Range requests.
     *
     * NOTE: This endpoint is guarded by auth + active middleware and the
     * student must pass Gate::authorize('watch', $material). The actual
     * YouTube video URL is never exposed to the client.
     */
    public function stream(Request $request, int $materialId): StreamedResponse
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = $request->user();

        $material = GroupMaterial::with('unit.assignment')->findOrFail($materialId);

        Gate::authorize('watch', $material);

        $videoId = YouTubeUrl::videoId($material->video_url);
        abort_unless($videoId, 404, 'هذه المادة لا تحتوي على فيديو يوتيوب.');

        try {
            $downloader = new YouTubeDownloader();
            $info       = $downloader->getDownloadLinks('https://www.youtube.com/watch?v=' . $videoId);

            // Pick the best combined (audio + video) stream available
            $streams = $info->getCombinedFormats();
            abort_if(empty($streams), 503, 'لا يمكن استخراج رابط الفيديو حالياً. حاول مرة أخرى.');

            // Prefer highest quality (first is usually best)
            $stream = $streams[0];
            $url    = $stream->url;
        } catch (YouTubeException $e) {
            abort(503, 'تعذّر الاتصال بيوتيوب: ' . $e->getMessage());
        }

        // ── Stream the video with Range support ─────────────────────────────
        $headers = [
            'Content-Type'              => $stream->mimeType ?? 'video/mp4',
            'Accept-Ranges'             => 'bytes',
            'Cache-Control'             => 'private, no-store',
            'X-Content-Type-Options'    => 'nosniff',
        ];

        $rangeHeader = $request->header('Range');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HEADER         => false,
        ]);

        if ($rangeHeader) {
            curl_setopt($ch, CURLOPT_RANGE, str_replace('bytes=', '', $rangeHeader));
        }

        $httpStatus = 200;
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers, &$httpStatus) {
            $len    = strlen($header);
            $header = trim($header);

            if (str_starts_with($header, 'HTTP/')) {
                preg_match('/HTTP\/\d+\.?\d*\s+(\d+)/', $header, $m);
                $httpStatus = isset($m[1]) ? (int) $m[1] : 200;
                return $len;
            }

            if (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $name  = strtolower(trim($name));
                $value = trim($value);

                if (in_array($name, ['content-length', 'content-range', 'content-type'], true)) {
                    $mapped = match ($name) {
                        'content-length' => 'Content-Length',
                        'content-range'  => 'Content-Range',
                        'content-type'   => 'Content-Type',
                    };
                    $headers[$mapped] = $value;
                }
            }

            return $len;
        });

        return new StreamedResponse(function () use ($ch) {
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) {
                echo $data;
                ob_flush();
                flush();
                return strlen($data);
            });
            curl_exec($ch);
            curl_close($ch);
        }, $httpStatus, $headers);
    }

    /**
     * Resolve the direct stream URL and return it as JSON so the
     * frontend can build a signed Proxy URL pointing to this endpoint.
     *
     * The frontend should use the /youtube-proxy/{materialId} route
     * instead of the raw YouTube video URL or the iframe embed.
     */
    public function resolveStreamUrl(Request $request, int $materialId): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = $request->user();

        $material = GroupMaterial::with('unit.assignment')->findOrFail($materialId);
        Gate::authorize('watch', $material);

        $videoId = YouTubeUrl::videoId($material->video_url);
        abort_unless($videoId, 404);

        // Build a signed URL that expires in 30 minutes for the proxy endpoint
        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'youtube.proxy.stream',
            now()->addMinutes(30),
            ['materialId' => $materialId, 'userId' => $user->id],
        );

        return response()->json([
            'provider'   => 'youtube_proxy',
            'stream_url' => $signedUrl,
        ]);
    }
}
