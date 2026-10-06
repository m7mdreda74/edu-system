<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonVideo extends Model
{
    public const STATUS_PENDING_UPLOAD = 'pending_upload';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'group_material_id',
        'provider',
        'idempotency_key',
        'provider_upload_id',
        'provider_asset_id',
        'status',
        'generation',
        'duration_seconds',
        'thumbnail_reference',
        'playback_reference',
        'failure_code',
        'failure_message',
        'provider_updated_at',
        'ready_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'duration_seconds' => 'integer',
            'provider_updated_at' => 'datetime',
            'ready_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(GroupMaterial::class, 'group_material_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
