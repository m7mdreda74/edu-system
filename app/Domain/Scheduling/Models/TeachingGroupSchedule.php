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

    /**
     * Converts 24-hour time string ("19:00", "07:30", etc.) to 12-hour format in Arabic.
     * e.g. "19:00" → "7 مساءً", "07:30" → "7:30 صباحاً"
     */
    public static function formatTime12(?string $time): string
    {
        if (empty($time)) {
            return '';
        }

        $trimmed = trim($time);
        if (str_contains($trimmed, 'صباحاً') || str_contains($trimmed, 'مساءً')) {
            return $trimmed;
        }

        $parts = explode(':', $trimmed);
        $hour = (int) ($parts[0] ?? 0);
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;

        $period = $hour >= 12 ? 'مساءً' : 'صباحاً';
        $hour12 = $hour % 12 === 0 ? 12 : $hour % 12;
        $minStr = $minute > 0 ? ':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT) : '';

        return "{$hour12}{$minStr} {$period}";
    }
}
