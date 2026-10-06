<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Video\VideoProviderInterface;
use App\Services\LessonVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VideoWebhookController extends Controller
{
    public function __construct(
        private readonly VideoProviderInterface $provider,
        private readonly LessonVideoService $videos,
    ) {}

    public function cloudflare(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        abort_unless(
            $this->provider->verifyWebhook($rawBody, $request->header('Webhook-Signature')),
            401,
            'توقيع Webhook غير صحيح.',
        );

        $payload = json_decode($rawBody, true);

        abort_unless(is_array($payload), 422, 'بيانات Webhook غير صالحة.');

        return response()->json([
            'received' => true,
            'processed' => $this->videos->handleWebhook($rawBody, $payload),
        ]);
    }
}
