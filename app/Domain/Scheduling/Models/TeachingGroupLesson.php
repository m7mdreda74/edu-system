<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Learning\Models\LiveSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingGroupLesson extends Model
{
    protected $fillable = ['teaching_group_id', 'position', 'title', 'description', 'live_session_id', 'status'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }
}
