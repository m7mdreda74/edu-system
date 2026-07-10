<?php

declare(strict_types=1);

namespace App\Domain\Course\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSession extends Model
{
    protected $fillable = [
        'course_id',
        'teacher_id',
        'title',
        'description',
        'scheduled_at',
        'started_at',
        'ended_at',
        'status', // scheduled, live, ended
        'room_id', // Zoom/Meet/External link
        'recording_url',
        'is_published_as_lesson',
        'lesson_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_published_as_lesson' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }
}
