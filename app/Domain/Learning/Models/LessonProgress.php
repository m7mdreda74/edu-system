<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How far a student got through one piece of material.
 *
 * Keyed to the student rather than a subscription so a month's lapse does not
 * wipe their history when they resubscribe.
 */
class LessonProgress extends Model
{
    use HasFactory;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'student_id',
        'lesson_id',
        'watched_seconds',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'watched_seconds' => 'integer',
            'is_completed'    => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(GroupMaterial::class, 'lesson_id');
    }
}
