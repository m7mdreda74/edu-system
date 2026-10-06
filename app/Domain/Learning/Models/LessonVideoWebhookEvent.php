<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonVideoWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_key',
        'lesson_video_id',
        'event_type',
        'provider_asset_id',
        'processed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function lessonVideo(): BelongsTo
    {
        return $this->belongsTo(LessonVideo::class);
    }
}
