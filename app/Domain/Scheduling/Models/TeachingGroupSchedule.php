<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingGroupSchedule extends Model
{
    protected $fillable = ['teaching_group_id', 'day_of_week', 'start_time', 'end_time', 'duration_minutes'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'duration_minutes' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }
}
