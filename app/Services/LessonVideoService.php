<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonVideo;
use App\Domain\Learning\Models\LessonVideoWebhookEvent;
use App\Infrastructure\Video\VideoProviderInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class LessonVideoService
{
    private const STATUS_RANK = [
        'pending_upload' => 0,
        'uploading' => 1,
        'processing' => 2,
        'ready' => 3,
        'failed' => 4,
        'cancelled' => 5,
    ];

    public function __construct(
        private readonly VideoProviderInterface $provider,
    ) {}

    public function enabled(): bool
    {
        return config('services.video_streaming.provider') === $this->provider->name()
            && $this->provider->isConfigured();
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }

    /** @return array<string, mixed> */
    public function beginUpload(GroupMaterial $lesson, int $teacherId, array $data): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('The managed video provider is not configured.');
        }

        $idempotencyKey = (string) $data['idempotency_key'];
        $existing = LessonVideo::where('group_material_id', $lesson->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $this->uploadResponse($existing, null, null, true);
        }

        $video = LessonVideo::create([
            'group_material_id' => $lesson->id,
            'provider' => $this->provider->name(),
            'idempotency_key' => $idempotencyKey,
            'provider_upload_id' => 'pending-'.Str::uuid(),
            'status' => LessonVideo::STATUS_PENDING_UPLOAD,
            'generation' => ((int) LessonVideo::where('group_material_id', $lesson->id)->max('generation')) + 1,
        ]);

        try {
            $authorization = $this->provider->createUpload([
                'teacher_id' => $teacherId,
                'lesson_id' => $lesson->id,
                'lesson_video_id' => $video->id,
                'file_size' => (int) $data['file_size'],
                'file_name' => (string) $data['file_name'],
                'mime_type' => (string) $data['mime_type'],
            ]);

            $video->update([
                'provider_upload_id' => $authorization['upload_id'],
                'status' => LessonVideo::STATUS_PENDING_UPLOAD,
            ]);

            return $this->uploadResponse(
                $video->fresh(),
                $authorization['upload_url'],
                $authorization['protocol'],
                false,
                $authorization['expires_at'] ?? null,
            );
        } catch (\Throwable $exception) {
            $video->update([
                'status' => LessonVideo::STATUS_FAILED,
                'failure_code' => 'upload_authorization_failed',
                'failure_message' => 'تعذر إنشاء جلسة الرفع لدى مزود الفيديو.',
            ]);

            Log::warning('Managed lesson video upload authorization failed.', [
                'lesson_video_id' => $video->id,
                'provider' => $this->provider->name(),
                'exception' => $exception::class,
            ]);

            throw new RuntimeException('تعذر إنشاء جلسة رفع الفيديو. حاول مرة أخرى.');
        }
    }

    /** @return array<string, mixed> */
    public function reconcile(LessonVideo $video): LessonVideo
    {
        if (! $video->provider_asset_id && ! $video->provider_upload_id) {
            return $video;
        }

        try {
            $details = $this->provider->videoDetails(
                (string) ($video->provider_asset_id ?: $video->provider_upload_id),
            );

            return $this->applyProviderDetails($video, $this->provider->normalizeWebhook($details));
        } catch (\Throwable $exception) {
            Log::notice('Managed lesson video reconciliation failed.', [
                'lesson_video_id' => $video->id,
                'provider' => $video->provider,
                'exception' => $exception::class,
            ]);

            return $video;
        }
    }

    public function handleWebhook(string $rawBody, array $payload): bool
    {
        $details = $this->provider->normalizeWebhook($payload);
        $assetId = (string) ($details['provider_asset_id'] ?? $details['provider_upload_id'] ?? '');

        if ($assetId === '') {
            return false;
        }

        $eventKey = hash('sha256', $this->provider->name().'|'.$rawBody);
        $eventType = (string) ($details['status'] ?? 'unknown');

        try {
            $event = LessonVideoWebhookEvent::firstOrCreate(
                ['provider' => $this->provider->name(), 'event_key' => $eventKey],
                [
                    'event_type' => $eventType,
                    'provider_asset_id' => $assetId,
                ],
            );
        } catch (QueryException $exception) {
            // A concurrent duplicate webhook has already claimed the key.
            if (LessonVideoWebhookEvent::where('provider', $this->provider->name())
                ->where('event_key', $eventKey)->exists()) {
                return false;
            }

            throw $exception;
        }

        if ($event->processed_at) {
            return false;
        }

        $video = LessonVideo::where('provider', $this->provider->name())
            ->where(function ($query) use ($assetId): void {
                $query->where('provider_asset_id', $assetId)
                    ->orWhere('provider_upload_id', $assetId);
            })
            ->first();

        if (! $video) {
            $event->update(['processed_at' => now()]);

            return false;
        }

        try {
            DB::transaction(function () use ($video, $details, $event): void {
                $locked = LessonVideo::lockForUpdate()->findOrFail($video->id);
                $this->applyProviderDetails($locked, $details);
                $event->update([
                    'lesson_video_id' => $locked->id,
                    'processed_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            $event->update(['failed_at' => now()]);
            Log::warning('Managed lesson video webhook processing failed.', [
                'lesson_video_id' => $video->id,
                'provider' => $this->provider->name(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        return true;
    }

    public function cancel(LessonVideo $video): LessonVideo
    {
        if ($video->provider_asset_id || $video->provider_upload_id) {
            try {
                $this->provider->deleteVideo((string) ($video->provider_asset_id ?: $video->provider_upload_id));
            } catch (\Throwable $exception) {
                Log::notice('Managed lesson video cancellation cleanup failed.', [
                    'lesson_video_id' => $video->id,
                    'provider' => $video->provider,
                    'exception' => $exception::class,
                ]);
            }
        }

        if ($video->status !== LessonVideo::STATUS_READY || (int) $video->lesson?->active_lesson_video_id !== $video->id) {
            $video->update([
                'status' => LessonVideo::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        }

        return $video->fresh();
    }

    /** @return array{url:string, expires_at:string} */
    public function playbackAuthorization(LessonVideo $video): array
    {
        if (! $video->isReady() || ! $video->provider_asset_id) {
            throw new RuntimeException('الفيديو لم يصبح جاهزًا للتشغيل بعد.');
        }

        $expiresAt = now()->addSeconds((int) config('services.video_streaming.playback_ttl', 900))->timestamp;

        return $this->provider->createPlaybackAuthorization($video->provider_asset_id, $expiresAt);
    }

    public function reconcilePending(): int
    {
        $count = 0;

        LessonVideo::query()
            ->whereIn('status', [
                LessonVideo::STATUS_PENDING_UPLOAD,
                LessonVideo::STATUS_UPLOADING,
                LessonVideo::STATUS_PROCESSING,
            ])
            ->where('provider', $this->provider->name())
            ->orderBy('id')
            ->each(function (LessonVideo $video) use (&$count): void {
                $this->reconcile($video);
                $count++;
            });

        return $count;
    }

    /** @return array<string, mixed> */
    public function statusPayload(LessonVideo $video): array
    {
        return [
            'id' => $video->id,
            'status' => $video->status,
            'generation' => $video->generation,
            'duration_seconds' => $video->duration_seconds,
            'failure_code' => $video->failure_code,
            'failure_message' => $video->failure_message,
            'is_active' => (int) $video->lesson?->active_lesson_video_id === (int) $video->id,
            'ready_at' => $video->ready_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function uploadResponse(
        LessonVideo $video,
        ?string $uploadUrl,
        ?string $protocol,
        bool $reused = false,
        ?string $expiresAt = null,
    ): array
    {
        return [
            'video_id' => $video->id,
            'status' => $video->status,
            'upload_url' => $uploadUrl,
            'upload_id' => $video->provider_upload_id,
            'protocol' => $protocol,
            'reused' => $reused,
            'expires_at' => $expiresAt,
        ];
    }

    /** @param array<string, mixed> $details */
    private function applyProviderDetails(LessonVideo $video, array $details): LessonVideo
    {
        $incomingStatus = (string) ($details['status'] ?? LessonVideo::STATUS_PROCESSING);
        $currentStatus = (string) $video->status;

        if ($currentStatus === LessonVideo::STATUS_CANCELLED
            || ($currentStatus === LessonVideo::STATUS_READY && $incomingStatus !== LessonVideo::STATUS_READY)
            || ($currentStatus === LessonVideo::STATUS_FAILED && $incomingStatus === LessonVideo::STATUS_PROCESSING)) {
            return $video;
        }

        $data = [
            'provider_asset_id' => $details['provider_asset_id'] ?? $video->provider_asset_id,
            'status' => $incomingStatus,
            'duration_seconds' => $details['duration_seconds'] ?? $video->duration_seconds,
            'thumbnail_reference' => $details['thumbnail_reference'] ?? $video->thumbnail_reference,
            'playback_reference' => $details['playback_reference'] ?? $video->playback_reference,
            'failure_code' => $incomingStatus === LessonVideo::STATUS_FAILED ? ($details['failure_code'] ?? 'provider_error') : null,
            'failure_message' => $incomingStatus === LessonVideo::STATUS_FAILED ? ($details['failure_message'] ?? 'تعذر تجهيز الفيديو لدى مزود الفيديو.') : null,
            'provider_updated_at' => now(),
        ];

        if ($incomingStatus === LessonVideo::STATUS_READY) {
            $data['ready_at'] = $video->ready_at ?? now();
        }

        $video->update($data);

        if ($incomingStatus === LessonVideo::STATUS_READY) {
            $newerPending = LessonVideo::where('group_material_id', $video->group_material_id)
                ->where('generation', '>', $video->generation)
                ->whereNotIn('status', [LessonVideo::STATUS_FAILED, LessonVideo::STATUS_CANCELLED])
                ->exists();

            if (! $newerPending) {
                GroupMaterial::whereKey($video->group_material_id)->update([
                    'active_lesson_video_id' => $video->id,
                    'video_path' => null,
                    'video_url' => null,
                    'duration_seconds' => $video->duration_seconds ?? 0,
                ]);
            }
        }

        return $video->fresh();
    }
}
