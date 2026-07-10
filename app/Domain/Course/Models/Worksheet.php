<?php

declare(strict_types=1);

namespace App\Domain\Course\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worksheet extends Model
{
    protected $fillable = [
        'course_id',
        'lesson_id',
        'title',
        'file_path',
        'type',
        'requires_submission',
        'due_date',
        'max_score',
    ];

    protected $casts = [
        'requires_submission' => 'boolean',
        'due_date'            => 'date',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WorksheetSubmission::class);
    }
}
